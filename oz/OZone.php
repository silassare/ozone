<?php

/**
 * Copyright (c) 2017-present, Emile Silas Sare
 *
 * This file is part of OZone package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace OZONE\OZ;

use Gobl\CRUD\CRUD;
use OZONE\OZ\App\Interfaces\AppInterface;
use OZONE\OZ\Core\Configs;
use OZONE\OZ\Core\Context;
use OZONE\OZ\Core\CRUDHandlerProvider;
use OZONE\OZ\Core\DbManager;
use OZONE\OZ\Core\Interfaces\TableCollectionsProviderInterface;
use OZONE\OZ\Core\Interfaces\TableRelationsProviderInterface;
use OZONE\OZ\Exceptions\BaseException;
use OZONE\OZ\Exceptions\RuntimeException;
use OZONE\OZ\Hooks\Events\FinishHook;
use OZONE\OZ\Hooks\Events\InitHook;
use OZONE\OZ\Hooks\Interfaces\BootHookReceiverInterface;
use OZONE\OZ\Http\Environment;
use OZONE\OZ\Router\Interfaces\RouteProviderInterface;
use OZONE\OZ\Router\Router;
use OZONE\OZ\Utils\Utils;
use PHPUtils\Events\Event;

/**
 * Class OZone.
 */
final class OZone
{
	public const INTERNAL_PATH_PREFIX = '/oz:';

	/**
	 * @var null|\OZONE\OZ\Router\Router
	 */
	private static ?Router $api_router;

	/**
	 * @var null|\OZONE\OZ\Router\Router
	 */
	private static ?Router $web_router;

	/**
	 * The current running app.
	 *
	 * @var null|AppInterface
	 */
	private static ?AppInterface $current_app = null;

	/**
	 * Gets current running app.
	 *
	 * @return null|\OZONE\OZ\App\Interfaces\AppInterface
	 */
	public static function getRunningApp(): null|AppInterface
	{
		return self::$current_app;
	}

	/**
	 * OZone main entry point.
	 *
	 * @param \OZONE\OZ\App\Interfaces\AppInterface $app
	 */
	public static function run(AppInterface $app): void
	{
		if (!empty(self::$current_app)) {
			\trigger_error('The app is already running.', \E_USER_NOTICE);

			return;
		}

		self::$current_app = $app;

		DbManager::init();

		self::registerCustomRelations();
		self::registerCustomCollections();

		$app->boot();

		self::notifyBootHookReceivers();

		$is_web = \defined('OZ_OZONE_IS_WEB_CONTEXT');
		$is_api = !$is_web;

		if ($is_web && !\defined('OZ_OZONE_DEFAULT_API_KEY')) {
			throw new RuntimeException('OZ_DEFAULT_API_KEY_NOT_DEFINED');
		}

		$env     = new Environment($_SERVER);
		$context = new Context($env, null, false, $is_api);

		// The current user access level will be used for CRUD validation
		CRUD::setHandlerProvider(new CRUDHandlerProvider($context));

		Event::trigger(new InitHook($context));

		$context->handle()->respond();

		// Finish the request
		if (\function_exists('fastcgi_finish_request')) {
			fastcgi_finish_request();
		} elseif (!\in_array(\PHP_SAPI, ['cli', 'phpdbg'], true)) {
			Utils::closeOutputBuffers(0, true);
		}

		Event::trigger(new FinishHook($context->getRequest(), $context->getResponse()));

		exit;
	}

	/**
	 * Returns the router with all API routes registered.
	 *
	 * @return \OZONE\OZ\Router\Router
	 */
	public static function getApiRouter(): Router
	{
		if (!isset(self::$api_router)) {
			self::$api_router = new Router();

			$a      = Configs::load('oz.routes');
			$b      = Configs::load('oz.routes.api');
			$routes = Configs::merge($a, $b);

			self::registerRoutes(self::$api_router, $routes);
		}

		return self::$api_router;
	}

	/**
	 * Returns the router with all WEB routes registered.
	 *
	 * @return \OZONE\OZ\Router\Router
	 */
	public static function getWebRouter(): Router
	{
		if (!isset(self::$web_router)) {
			self::$web_router = new Router();

			$a      = Configs::load('oz.routes');
			$b      = Configs::load('oz.routes.web');
			$routes = Configs::merge($a, $b);

			self::registerRoutes(self::$web_router, $routes);
		}

		return self::$web_router;
	}

	/**
	 * Register all route provider.
	 *
	 * @param \OZONE\OZ\Router\Router $router
	 * @param array                   $routes
	 */
	private static function registerRoutes(Router $router, array $routes): void
	{
		foreach ($routes as $provider => $enabled) {
			if ($enabled) {
				if (!\is_subclass_of($provider, RouteProviderInterface::class)) {
					throw new RuntimeException(\sprintf(
						'Route provider "%s" should implements "%s".',
						$provider,
						RouteProviderInterface::class
					));
				}

				/* @var RouteProviderInterface $provider */
				$provider::registerRoutes($router);
			}
		}
	}

	/**
	 * Notify all boot hook receivers.
	 */
	private static function notifyBootHookReceivers(): void
	{
		$hook_receivers = Configs::load('oz.boot');

		foreach ($hook_receivers as $receiver => $enabled) {
			if ($enabled) {
				if (!\is_subclass_of($receiver, BootHookReceiverInterface::class)) {
					throw new RuntimeException(\sprintf(
						'Boot hook receiver "%s" should implements "%s".',
						$receiver,
						BootHookReceiverInterface::class
					));
				}

				/* @var \OZONE\OZ\Hooks\Interfaces\BootHookReceiverInterface $receiver */
				$receiver::boot();
			}
		}
	}

	/**
	 * Register custom relations.
	 */
	private static function registerCustomRelations(): void
	{
		$relations_settings = Configs::load('oz.db.relations');

		foreach ($relations_settings as $provider => $enabled) {
			if ($enabled) {
				if (!\is_subclass_of($provider, TableRelationsProviderInterface::class)) {
					throw new RuntimeException(\sprintf(
						'Custom relations provider "%s" should implements "%s".',
						$provider,
						TableRelationsProviderInterface::class
					));
				}

				/* @var TableRelationsProviderInterface $provider */
				$provider::defineRelations();
			}
		}
	}

	/**
	 * Register custom collections.
	 */
	private static function registerCustomCollections(): void
	{
		$collections_settings = Configs::load('oz.db.collections');

		foreach ($collections_settings as $provider => $enabled) {
			if ($enabled) {
				if (!\is_subclass_of($provider, TableCollectionsProviderInterface::class)) {
					throw new RuntimeException(\sprintf(
						'Custom collections provider "%s" should implements "%s".',
						$provider,
						TableCollectionsProviderInterface::class
					));
				}

				/* @var TableCollectionsProviderInterface $provider */
				$provider::defineCollections();
			}
		}
	}

}
