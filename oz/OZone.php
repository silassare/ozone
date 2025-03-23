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

use OZONE\Core\App\Context;
use OZONE\Core\App\Db;
use OZONE\Core\App\Interfaces\AppInterface;
use OZONE\Core\App\Settings;
use OZONE\Core\Auth\Auth;
use OZONE\Core\Auth\AuthUsers;
use OZONE\Core\Auth\Enums\AuthMethodType;
use OZONE\Core\CRUD\TableCRUD;
use OZONE\Core\Db\OZRolesQuery;
use OZONE\Core\Exceptions\RuntimeException;
use OZONE\Core\Hooks\Events\FinishHook;
use OZONE\Core\Hooks\Events\InitHook;
use OZONE\Core\Hooks\Interfaces\BootHookReceiverInterface;
use OZONE\Core\Http\HTTPEnvironment;
use OZONE\Core\Migrations\Enums\MigrationsState;
use OZONE\Core\Migrations\Migrations;
use OZONE\Core\Plugins\Plugins;
use OZONE\Core\Router\Events\RouterCreated;
use OZONE\Core\Router\Interfaces\RouteProviderInterface;
use OZONE\Core\Router\Router;
use OZONE\Core\Utils\Utils;
use PDOException;

/**
 * Class OZone.
 */
final class OZone
{
	public const INTERNAL_PATH_PREFIX = '/~ozone-internal~/';

	/**
	 * @var null|Router
	 */
	private static ?Router $api_router;

	/**
	 * @var null|Router
	 */
	private static ?Router $web_router;

	/**
	 * The current running app.
	 *
	 * @var null|AppInterface
	 */
	private static ?AppInterface $current_app         = null;
	private static bool $boot_hook_receivers_notified = false;

	/**
	 * Gets current running app.
	 *
	 * @return AppInterface
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
	 * Make sure that the boot hook receivers are notified.
	 *
	 * @param string $message the message to display if the boot hook receivers are not notified
	 */
	public static function dieIfBootHookReceiversAreNotNotified(string $message): void
	{
		if (!self::$boot_hook_receivers_notified) {
			// this is to make sure that the dev will be notified by all means
			// and look at the log file to fix the issue
			oz_trace($message);

			exit('Boot hook receivers not notified. If you are an admin, please review the log file and correct it!' . \PHP_EOL);
		}
	}

	/**
	 * OZone main entry point.
	 *
	 * @param AppInterface $app
	 */
	public static function run(AppInterface $app): void
	{
		if (null !== self::$current_app) {
			\trigger_error('The app is already running.');

			return;
		}

		self::$current_app = $app;

		$app->boot();

		Plugins::boot();

		self::notifyBootHookReceivers();

		Db::init();

		$context = self::getMainContext();

		// The current user access level will be used for CRUD validation
		TableCRUD::registerListeners($context);

		(new InitHook($context))->dispatch();

		if (self::isWebMode()) {
			$context->handle()
				->respond();

			// Finish the request
			if (\function_exists('fastcgi_finish_request')) {
				fastcgi_finish_request();
			} elseif (!\in_array(\PHP_SAPI, ['cli', 'phpdbg'], true)) {
				Utils::closeOutputBuffers(0, true);
			}

			(new FinishHook($context->getRequest(), $context->getResponse()))->dispatch();

			exit;
		}
	}

	/**
	 * Returns the API routes providers.
	 *
	 * @return array<class-string, bool>
	 */
	public static function getApiRoutesProviders(): array
	{
		static $results = null;

		if (null === $results) {
			$a       = Settings::load('oz.routes');
			$b       = Settings::load('oz.routes.api');
			$results = Settings::merge($a, $b);
		}

		return $results;
	}

	/**
	 * Returns the WEB routes providers.
	 *
	 * @return array<class-string, bool>
	 */
	public static function getWebRoutesProviders(): array
	{
		static $results = null;

		if (null === $results) {
			$a       = Settings::load('oz.routes');
			$b       = Settings::load('oz.routes.web');
			$results = Settings::merge($a, $b);
		}

		return $results;
	}

	/**
	 * Returns the router with all API routes registered.
	 *
	 * @return Router
	 */
	public static function getApiRouter(): Router
	{
		if (!isset(self::$api_router)) {
			$router = self::$api_router = new Router();
			$group  = $router->group('/', static function () {
				self::registerRoutes(self::$api_router, self::getApiRoutesProviders());
			})->auths(...Auth::apiAuthMethods());

			(new RouterCreated($router, $group, true))->dispatch();
		}

		return self::$api_router;
	}

	/**
	 * Returns the router with all WEB routes registered.
	 *
	 * @return Router
	 */
	public static function getWebRouter(): Router
	{
		if (!isset(self::$web_router)) {
			$router = self::$web_router = new Router();

			$group = $router->group('/', static function () {
				self::registerRoutes(self::$web_router, self::getWebRoutesProviders());
			})->auths(AuthMethodType::SESSION);

			(new RouterCreated($router, $group, false))->dispatch();
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
		return self::hasDbAccess() && self::hasDbInstalled() && self::hasSuperAdmin();
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
			} catch (PDOException) {
				$has_db_access = false;
			}
		}

		return $has_db_access;
	}

	/**
	 * Check if we have database installed.
	 *
	 * @return bool
	 */
	public static function hasDbInstalled(): bool
	{
		return self::hasDbAccess() && MigrationsState::NOT_INSTALLED !== Migrations::getState();
	}

	/**
	 * Check if we have a super admin.
	 *
	 * @return bool
	 */
	public static function hasSuperAdmin(): bool
	{
		if (!self::hasDbInstalled()) {
			return false;
		}

		static $has_super_admin = null;

		if (null === $has_super_admin) {
			$roles_qb = new OZRolesQuery();
			$results  = $roles_qb->whereNameIs(AuthUsers::SUPER_ADMIN)
				->whereIsValid()
				->find(1);

			$has_super_admin = (bool) $results->count();
		}

		return $has_super_admin;
	}

	/**
	 * Returns the main context.
	 *
	 * @return Context
	 */
	public static function getMainContext(): Context
	{
		static $main_context = null;
		if (null === $main_context) {
			$is_cli_mode    = self::isCliMode();
			$is_web_context = \defined('OZ_OZONE_IS_WEB_CONTEXT');
			$is_api_context = !$is_web_context;

			if ($is_cli_mode) {
				$http_env = HTTPEnvironment::mock();
			} else {
				$http_env = new HTTPEnvironment($_SERVER);
			}

			$main_context = new Context($http_env, null, null, $is_api_context);
		}

		return $main_context;
	}

	/**
	 * Register all route provider.
	 *
	 * @param Router                    $router
	 * @param array<class-string, bool> $routes
	 */
	private static function registerRoutes(Router $router, array $routes): void
	{
		foreach ($routes as $provider => $enabled) {
			if ($enabled) {
				if (!\is_subclass_of($provider, RouteProviderInterface::class)) {
					throw new RuntimeException(
						\sprintf(
							'Route provider "%s" should implements "%s".',
							$provider,
							RouteProviderInterface::class
						)
					);
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
					throw new RuntimeException(
						\sprintf(
							'Boot hook receiver "%s" should implements "%s".',
							$receiver,
							BootHookReceiverInterface::class
						)
					);
				}

				/* @var \OZONE\Core\Hooks\Interfaces\BootHookReceiverInterface $receiver */
				$receiver::boot();
			}
		}

		self::$boot_hook_receivers_notified = true;
	}
}
