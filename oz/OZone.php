<?php

/**
 * Copyright (c) 2017-present, Emile Silas Sare
 *
 * This file is part of OZone (O'Zone) package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace OZONE\OZ;

use Exception;
use Gobl\CRUD\CRUD;
use OZONE\OZ\App\AppInterface;
use OZONE\OZ\Core\Context;
use OZONE\OZ\Core\DbManager;
use OZONE\OZ\Core\Interfaces\TableCollectionsProviderInterface;
use OZONE\OZ\Core\Interfaces\TableRelationsProviderInterface;
use OZONE\OZ\Core\SettingsManager;
use OZONE\OZ\Event\EventManager;
use OZONE\OZ\Exceptions\BaseException;
use OZONE\OZ\Hooks\Interfaces\HookReceiverInterface;
use OZONE\OZ\Hooks\MainHookProvider;
use OZONE\OZ\Http\Environment;
use OZONE\OZ\Lang\Polyglot;
use OZONE\OZ\Loader\ClassLoader;
use OZONE\OZ\Router\RouteProviderInterface;
use OZONE\OZ\Router\Router;
use ReflectionClass;
use ReflectionException;
use RuntimeException;
use Throwable;

include_once OZ_OZONE_DIR . 'oz_default' . DS . 'oz_config.php';

include_once OZ_OZONE_DIR . 'oz_default' . DS . 'oz_define.php';

include_once OZ_OZONE_DIR . 'oz_default' . DS . 'oz_func.php';

final class OZone
{
	const API_KEY_REG = '~^[A-Z0-9]{8}-[A-Z0-9]{8}-[A-Z0-9]{8}-[A-Z0-9]{8}$~';

	const INTERNAL_PATH_PREFIX = '/oz:';

	/**
	 * @var \OZONE\OZ\Router\Router
	 */
	private static $api_router;

	/**
	 * @var \OZONE\OZ\Router\Router
	 */
	private static $web_router;

	/**
	 * The current running app
	 *
	 * @var AppInterface
	 */
	private static $current_app = null;

	/**
	 * Gets event manager instance.
	 *
	 * @return \OZONE\OZ\Event\EventManager
	 */
	public static function getEventManager()
	{
		return EventManager::getInstance();
	}

	/**
	 * Gets current running app.
	 *
	 * @return \OZONE\OZ\App\AppInterface
	 */
	public static function getRunningApp()
	{
		return self::$current_app;
	}

	/**
	 * OZone main entry point.
	 *
	 * @param \OZONE\OZ\App\AppInterface $app
	 *
	 * @throws \Throwable
	 */
	public static function run(AppInterface $app)
	{
		if (!empty(self::$current_app)) {
			\trigger_error('The app is already running.', \E_USER_NOTICE);

			return;
		}

		self::$current_app = $app;

		$env = new Environment($_SERVER);
		Polyglot::init($env);
		DbManager::init();

		self::registerCustomRelations();
		self::registerCustomCollections();
		self::registerHookReceivers();

		$is_api  = !\defined('OZ_OZONE_IS_WEB_CONTEXT');
		$context = new Context($env, null, false, $is_api);

		// [!IMPORTANT] The CRUD handler is instantiated with the first context
		// So if we have a session,
		// the current user access level will be used for CRUD validation
		CRUD::setHandlerProvider(function ($table_name) use ($context) {
			return DbManager::instantiateCRUDHandler($context, $table_name);
		});

		try {
			MainHookProvider::getInstance()
							->triggerInit($context);
			$context->handle()
					->respond();
		} catch (Exception $e) {
			$e = BaseException::tryConvert($e);

			$e->informClient($context);
		} catch (Throwable $e) {
			$e = BaseException::tryConvert($e);

			$e->informClient($context);
		}
	}

	/**
	 * Returns the router with all API routes registered.
	 *
	 * @return \OZONE\OZ\Router\Router
	 */
	public static function getApiRouter()
	{
		if (!isset(self::$api_router)) {
			self::$api_router = new Router();

			$a      = SettingsManager::get('oz.routes');
			$b      = SettingsManager::get('oz.routes.api');
			$routes = SettingsManager::merge($a, $b);

			self::registerRoutes(self::$api_router, $routes);
		}

		return self::$api_router;
	}

	/**
	 * Returns the router with all WEB routes registered.
	 *
	 * @return \OZONE\OZ\Router\Router
	 */
	public static function getWebRouter()
	{
		if (!isset(self::$web_router)) {
			self::$web_router = new Router();

			$a      = SettingsManager::get('oz.routes');
			$b      = SettingsManager::get('oz.routes.web');
			$routes = SettingsManager::merge($a, $b);

			self::registerRoutes(self::$web_router, $routes);
		}

		return self::$web_router;
	}

	/**
	 * Creates instance of class for a given class name and arguments.
	 *
	 * @param string $class_name the full qualified class name to instantiate
	 *
	 * @throws \ReflectionException
	 *
	 * @return object
	 */
	public static function createInstance($class_name)
	{
		$c_args = \func_get_args();

		\array_shift($c_args);

		return ClassLoader::instantiateClass($class_name, $c_args);
	}

	/**
	 * @param \OZONE\OZ\Router\Router $router
	 * @param array                   $routes
	 */
	private static function registerRoutes(Router $router, $routes)
	{
		foreach ($routes as $provider => $enabled) {
			if ($enabled) {
				try {
					$rc = new ReflectionClass($provider);

					if (!$rc->implementsInterface(RouteProviderInterface::class)) {
						throw new RuntimeException(\sprintf(
							'Route provider "%s" should implements "%s".',
							$provider,
							RouteProviderInterface::class
						));
					}

					/* @var RouteProviderInterface $provider */
					$provider::registerRoutes($router);
				} catch (ReflectionException $e) {
					throw new RuntimeException(\sprintf('Unable to register route provider: %s.', $provider), null, $e);
				}
			}
		}
	}

	/**
	 * Register all hook receivers.
	 */
	private static function registerHookReceivers()
	{
		$hook_receivers = SettingsManager::get('oz.hooks');

		foreach ($hook_receivers as $receiver => $enabled) {
			if ($enabled) {
				try {
					$rc = new ReflectionClass($receiver);

					if (!$rc->implementsInterface(HookReceiverInterface::class)) {
						throw new RuntimeException(\sprintf(
							'Hook receiver "%s" should implements "%s".',
							$receiver,
							HookReceiverInterface::class
						));
					}
					/* @var \OZONE\OZ\Hooks\Interfaces\HookReceiverInterface $receiver */
					$receiver::register();
				} catch (ReflectionException $e) {
					throw new RuntimeException(\sprintf('Unable to register hook receiver "%s".', $receiver), null, $e);
				}
			}
		}
	}

	/**
	 * Register custom relations.
	 *
	 * @throws \Exception
	 */
	private static function registerCustomRelations()
	{
		$relations_settings = SettingsManager::get('oz.db.relations');
		$db                 = DbManager::getDb();

		foreach ($relations_settings as $provider => $enabled) {
			if ($enabled) {
				try {
					$rc = new ReflectionClass($provider);

					if (!$rc->implementsInterface(TableRelationsProviderInterface::class)) {
						throw new RuntimeException(\sprintf(
							'Custom relations provider "%s" should implements "%s".',
							$provider,
							TableRelationsProviderInterface::class
						));
					}
				} catch (ReflectionException $e) {
					throw new RuntimeException(\sprintf(
						'Unable to register custom relations provider "%s".',
						$provider
					), null, $e);
				}

				/* @var TableRelationsProviderInterface $provider */
				$def_list = $provider::getRelationsDefinition();

				foreach ($def_list as $table_name => $relations) {
					$table = $db->getTable($table_name);

					foreach ($relations as $relation_name => $callable) {
						if (\is_callable($callable)) {
							$table->defineVR($relation_name, $callable);
						} else {
							throw new RuntimeException(
								\sprintf(
									'Custom relation "%s" defined in "%s" for table "%s"'
									. ' expected to be "callable" not "%s". %s',
									$relation_name,
									$provider,
									$table_name,
									\gettype($callable),
									'Maybe the class method is private or protected.'
								)
							);
						}
					}
				}
			}
		}
	}

	/**
	 * Register custom collections.
	 *
	 * @throws \Exception
	 */
	private static function registerCustomCollections()
	{
		$collections_settings = SettingsManager::get('oz.db.collections');
		$db                   = DbManager::getDb();

		foreach ($collections_settings as $provider => $enabled) {
			if ($enabled) {
				try {
					$rc = new ReflectionClass($provider);

					if (!$rc->implementsInterface(TableCollectionsProviderInterface::class)) {
						throw new RuntimeException(\sprintf(
							'Custom collections provider "%s" should implements "%s".',
							$provider,
							TableCollectionsProviderInterface::class
						));
					}
				} catch (ReflectionException $e) {
					throw new RuntimeException(\sprintf(
						'Unable to register custom collections provider "%s".',
						$provider
					), null, $e);
				}

				/* @var TableCollectionsProviderInterface $provider */
				$def_list = $provider::getCollectionsDefinition();

				foreach ($def_list as $table_name => $relations) {
					$table = $db->getTable($table_name);

					foreach ($relations as $collection_name => $callable) {
						if (\is_callable($callable)) {
							$table->defineCollection($collection_name, $callable);
						} else {
							throw new RuntimeException(\sprintf(
								'Custom collection "%s" defined in "%s" for table "%s"'
								. ' expected to be "callable" not "%s".',
								$collection_name,
								$provider,
								$table_name,
								\gettype($callable)
							));
						}
					}
				}
			}
		}
	}
}
