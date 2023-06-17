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

namespace OZONE\Core;

use Gobl\CRUD\CRUD;
use OZONE\Core\App\Context;
use OZONE\Core\App\Db;
use OZONE\Core\App\Interfaces\AppInterface;
use OZONE\Core\App\Settings;
use OZONE\Core\CRUD\TableCRUDHandlerProvider;
use OZONE\Core\Db\OZRolesQuery;
use OZONE\Core\Exceptions\RuntimeException;
use OZONE\Core\Hooks\Events\FinishHook;
use OZONE\Core\Hooks\Events\InitHook;
use OZONE\Core\Hooks\Interfaces\BootHookReceiverInterface;
use OZONE\Core\Http\HTTPEnvironment;
use OZONE\Core\Router\Interfaces\RouteProviderInterface;
use OZONE\Core\Router\Router;
use OZONE\Core\Users\Users;
use OZONE\Core\Utils\Utils;
use PHPUtils\Events\Event;

/**
 * Class OZone.
 */
final class OZone
{
	public const INTERNAL_PATH_PREFIX = '/~ozone-internal~/';

	/**
	 * @var null|\OZONE\Core\Router\Router
	 */
	private static ?Router $api_router;

	/**
	 * @var null|\OZONE\Core\Router\Router
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
	 * @var null|\OZONE\Core\App\Context
	 */
	private static ?Context $main_context = null;

	/**
	 * Gets current running app.
	 *
	 * @return \OZONE\Core\App\Interfaces\AppInterface
	 */
	public static function app(): AppInterface
	{
		if (null === self::$current_app) {
			throw new RuntimeException('No app is running.');
		}

		return self::$current_app;
	}

	/**
	 * Checks if a given path is an internal path.
	 *
	 * @param string $path
	 *
	 * @return bool
	 */
	public static function isInternalPath(string $path): bool
	{
		return \str_starts_with($path, self::INTERNAL_PATH_PREFIX);
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
	 * @param \OZONE\Core\App\Interfaces\AppInterface $app
	 */
	public static function run(AppInterface $app): void
	{
		if (null !== self::$current_app) {
			\trigger_error('The app is already running.', \E_USER_NOTICE);

			return;
		}

		self::$current_app = $app;

		$app->boot();

		self::notifyBootHookReceivers();

		Db::init();

		$context = self::createMainContext();

		// The current user access level will be used for CRUD validation
		CRUD::setHandlerProvider(new TableCRUDHandlerProvider($context));

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
	 * @return \OZONE\Core\Router\Router
	 */
	public static function getApiRouter(): Router
	{
		if (!isset(self::$api_router)) {
			self::$api_router = new Router();

			$a      = Settings::load('oz.routes');
			$b      = Settings::load('oz.routes.api');
			$routes = Settings::merge($a, $b);

			self::registerRoutes(self::$api_router, $routes);
		}

		return self::$api_router;
	}

	/**
	 * Returns the router with all WEB routes registered.
	 *
	 * @return \OZONE\Core\Router\Router
	 */
	public static function getWebRouter(): Router
	{
		if (!isset(self::$web_router)) {
			self::$web_router = new Router();

			$a      = Settings::load('oz.routes');
			$b      = Settings::load('oz.routes.web');
			$routes = Settings::merge($a, $b);

			self::registerRoutes(self::$web_router, $routes);
		}

		return self::$web_router;
	}

	/**
	 * Check if we already completed installation process.
	 *
	 * @return bool
	 */
	public static function isInstalled(): bool
	{
		return self::hasDbAccess() && self::hasSuperAdmin();
	}

	/**
	 * Check if we have database access.
	 *
	 * @return bool
	 */
	public static function hasDbAccess(): bool
	{
		static $has_db_access = null;

		if (null === $has_db_access) {
			try {
				db()->getConnection();

				$has_db_access = true;
			} catch (\Throwable) {
				$has_db_access = false;
			}
		}

		return $has_db_access;
	}

	/**
	 * Check if we have a super admin.
	 *
	 * @return bool
	 */
	public static function hasSuperAdmin(): bool
	{
		if (!self::hasDbAccess()) {
			return false;
		}

		static $has_super_admin = null;

		if (null === $has_super_admin) {
			try {
				$roles_qb = new OZRolesQuery();
				$results  = $roles_qb->whereNameIs(Users::SUPER_ADMIN)
					->whereIsValid()
					->find(1);

				$has_super_admin = (bool) $results->count();
			} catch (\Throwable) {
				$has_super_admin = false;
			}
		}

		return $has_super_admin;
	}

	/**
	 * Creates the main context.
	 *
	 * @return \OZONE\Core\App\Context
	 */
	private static function createMainContext(): Context
	{
		if (null === self::$main_context) {
			$is_cli_mode    = self::isCliMode();
			$is_web_context = \defined('OZ_OZONE_IS_WEB_CONTEXT');
			$is_api_context = !$is_web_context;

			if ($is_cli_mode) {
				$api_key_name = Settings::get('oz.config', 'OZ_API_KEY_HEADER_NAME');
				$api_key_env  = \sprintf('HTTP_%s', \strtoupper(\str_replace('-', '_', $api_key_name)));

				$http_env = HTTPEnvironment::mock([
					$api_key_env => '',
				]);
			} else {
				$http_env = new HTTPEnvironment($_SERVER);
			}

			self::$main_context = new Context($http_env, null, null, $is_api_context);
		}

		return self::$main_context;
	}

	/**
	 * Register all route provider.
	 *
	 * @param \OZONE\Core\Router\Router $router
	 * @param array                     $routes
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
		$hook_receivers = Settings::load('oz.boot');

		foreach ($hook_receivers as $receiver => $enabled) {
			if ($enabled) {
				if (!\is_subclass_of($receiver, BootHookReceiverInterface::class)) {
					throw new RuntimeException(\sprintf(
						'Boot hook receiver "%s" should implements "%s".',
						$receiver,
						BootHookReceiverInterface::class
					));
				}

				/* @var \OZONE\Core\Hooks\Interfaces\BootHookReceiverInterface $receiver */
				$receiver::boot();
			}
		}
	}
}
