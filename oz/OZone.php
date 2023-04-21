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
	 * The main context.
	 *
	 * @var null|\OZONE\OZ\Core\Context
	 */
	private static ?Context $main_context = null;

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
	 * Gets main context.
	 *
	 * @return null|\OZONE\OZ\Core\Context
	 */
	public static function getMainContext(): ?Context
	{
		return self::$main_context;
	}

	/**
	 * Checks if the app is running in cli mode.
	 *
	 * @return bool
	 */
	public static function isCliMode(): bool
	{
		return \defined('OZ_OZONE_IS_CLI') && OZ_OZONE_IS_CLI;
	}

	/**
	 * Checks if the app is running in web mode.
	 *
	 * @return bool
	 */
	public static function isWebMode(): bool
	{
		return !self::isCliMode();
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

		$app->boot();

		self::notifyBootHookReceivers();

		DbManager::init();

		$context = self::createMainContext();

		// The current user access level will be used for CRUD validation
		CRUD::setHandlerProvider(new CRUDHandlerProvider($context));

		Event::trigger(new InitHook($context));

		if (self::isWebMode()) {
			$context->handle()
				->respond();

			// Finish the request
			if (\function_exists('fastcgi_finish_request')) {
				fastcgi_finish_request();
			} elseif (!\in_array(\PHP_SAPI, ['cli', 'phpdbg'], true)) {
				Utils::closeOutputBuffers(0, true);
			}

			Event::trigger(new FinishHook($context->getRequest(), $context->getResponse()));

			exit;
		}
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
	 * Creates the main context.
	 *
	 * @return \OZONE\OZ\Core\Context
	 */
	private static function createMainContext(): Context
	{
		if (null === self::$main_context) {
			$is_cli_mode    = self::isCliMode();
			$is_web_context = \defined('OZ_OZONE_IS_WEB_CONTEXT');
			$is_api_context = !$is_web_context;

			if ($is_cli_mode) {
				$api_key_name = Configs::get('oz.config', 'OZ_API_KEY_HEADER_NAME');
				$api_key_env  = \sprintf('HTTP_%s', \strtoupper(\str_replace('-', '_', $api_key_name)));

				$env = Environment::mock([
					$api_key_env => '',
				]);
			} else {
				if ($is_web_context && !\defined('OZ_OZONE_DEFAULT_API_KEY')) {
					throw new RuntimeException('OZ_DEFAULT_API_KEY_NOT_DEFINED');
				}

				$env = new Environment($_SERVER);
			}

			self::$main_context = new Context($env, null, false, $is_api_context);
		}

		return self::$main_context;
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
}
