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

use Gobl\ORM\ORMOptions;
use OZONE\Core\App\Context;
use OZONE\Core\App\Db;
use OZONE\Core\App\Interfaces\AppInterface;
use OZONE\Core\App\Settings;
use OZONE\Core\Auth\Auth;
use OZONE\Core\Cli\Cmd\DoctorCmd;
use OZONE\Core\CRUD\TableCRUD;
use OZONE\Core\Db\OZRolesQuery;
use OZONE\Core\Exceptions\RuntimeException;
use OZONE\Core\Exceptions\Utils\ErrorUtils;
use OZONE\Core\Hooks\Events\DbReadyHook;
use OZONE\Core\Hooks\Events\EndRequestHook;
use OZONE\Core\Hooks\Events\InitHook;
use OZONE\Core\Hooks\Interfaces\BootHookReceiverInterface;
use OZONE\Core\Hooks\MainBootHookReceiver;
use OZONE\Core\Http\HTTPEnvironment;
use OZONE\Core\Http\Request;
use OZONE\Core\Loader\ClassLoader;
use OZONE\Core\Migrations\Enums\MigrationsState;
use OZONE\Core\Migrations\Migrations;
use OZONE\Core\Plugins\Plugins;
use OZONE\Core\Roles\Enums\Role;
use OZONE\Core\Router\Events\RouterCreated;
use OZONE\Core\Router\Router;
use OZONE\Core\Router\RouteTable;
use OZONE\Core\Runtime\Exceptions\RequestFinished;
use OZONE\Core\Runtime\Interfaces\ResponseSinkInterface;
use OZONE\Core\Runtime\Runtime;
use OZONE\Core\Scopes\StateLayout;
use OZONE\Core\Stores\CacheRegistry;
use Throwable;

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
	 * The running app.
	 *
	 * @var null|AppInterface
	 */
	private static ?AppInterface $app_instance                = null;
	private static bool $boot_hook_receivers_notified         = false;

	/**
	 * The failure of {@see Db::init()} at bootstrap, in CLI mode.
	 *
	 * @see self::getDbInitError()
	 */
	private static ?Throwable $db_init_error = null;

	/**
	 * Positive answers of {@see self::hasDbAccess()} and {@see self::hasSuperAdmin()}, kept for the
	 * process: neither goes back to false in practice, so only a "no" is asked again.
	 */
	private static bool $has_db_access = false;

	private static bool $has_super_admin = false;

	/**
	 * Gets running app.
	 *
	 * @return AppInterface
	 */
	public static function app(): AppInterface
	{
		if (null === self::$app_instance) {
			throw new RuntimeException('No app is running.');
		}

		return self::$app_instance;
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
	 * Means "there is no HTTP client to answer", which is the question every caller actually asks.
	 * It used to be read from `OZ_OZONE_IS_CLI` (`PHP_SAPI === 'cli'`), and so was also true of a
	 * RoadRunner or Swoole worker serving real requests: the first 404 printed itself to the
	 * terminal and exited the worker. {@see Runtime} answers it now.
	 *
	 * @return bool
	 */
	public static function isCliMode(): bool
	{
		return Runtime::isConsole();
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

			exit('Boot hook receivers not notified. If you are an admin, please review the log file and correct it!'
				. \PHP_EOL);
		}
	}

	/**
	 * Checks if the app is running.
	 *
	 * @return bool
	 */
	public static function isRunning(): bool
	{
		return null !== self::$app_instance;
	}

	/**
	 * Run the app.
	 *
	 * This method is the entry point of the app.
	 * It will bootstrap the app and handle the request.
	 *
	 * @param AppInterface $app
	 */
	public static function run(AppInterface $app): never
	{
		$context = self::bootstrap($app);

		$context->handle();
		$context->respond();
	}

	/**
	 * Handles one request, in a process that serves more than one.
	 *
	 * This is what a worker loop calls, once per request, after `bootstrap()` has run once:
	 *
	 * ```php
	 * $app = require OZ_APP_DIR . 'app.php';
	 *
	 * OZone::bootstrap($app);
	 *
	 * // FrankenPHP worker mode
	 * while (\frankenphp_handle_request(static fn () => OZone::handleRequest())) {
	 *     \gc_collect_cycles();
	 * }
	 * ```
	 *
	 * It gives the request its own context tree, answers it, and releases everything that belongs to
	 * it -- whatever happened, including an error the framework turned into a response. What the
	 * process keeps is the configuration: the routers, the settings, the database connection.
	 *
	 * `RequestFinished` is the normal end here: `Context::respond()` raises it where PHP-FPM would
	 * `exit`, so nothing after a response runs under either runtime.
	 *
	 * A server that hands requests over as objects (RoadRunner, Swoole) passes the `Request` it
	 * built ({@see HTTPEnvironment::fromParts()}, {@see Request::createFromHTTPEnvironment()}) and a
	 * sink: the finished `Response` object is then given to the sink instead of being written to
	 * PHP's output. `Runtime\Bridges\` has one bridge per supported server.
	 *
	 * @param null|HTTPEnvironment|Request $request the request, or its environment; the process
	 *                                              environment by default
	 * @param null|ResponseSinkInterface   $sink    where the response goes; PHP's output by default
	 */
	public static function handleRequest(
		HTTPEnvironment|Request|null $request = null,
		?ResponseSinkInterface $sink = null
	): void {
		// Each request owns its context tree, including the first: the one `bootstrap()` built holds
		// no request yet, and releasing it here keeps this method the only place a request starts.
		self::endRequest();

		try {
			if ($request instanceof Request) {
				$env = new HTTPEnvironment($request->getServerParams());
			} else {
				$env     = $request ?? new HTTPEnvironment($_SERVER);
				$request = null;
			}

			$context = new Context($env, $request, null, !\defined('OZ_OZONE_IS_WEB_CONTEXT'), $sink);

			$context->handle()
				->respond();
		} catch (RequestFinished) {
			// The request was answered.
		} catch (Throwable $t) {
			// Nothing above could turn this into a response -- `Context::handle()` converts what
			// reaches it, so this is the response itself failing, or the context failing to build.
			oz_logger($t);

			// One bad request must not take a worker down with it; under any other runtime the
			// process was ending anyway, and the error handlers are the ones that report it.
			if (!Runtime::isPersistent()) {
				throw $t;
			}
		} finally {
			self::endRequest();
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
			$results = Settings::applyMergeStrategy($a, $b);
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
			$results = Settings::applyMergeStrategy($a, $b);
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
			self::createRouter(self::$api_router = new Router(), true);
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
			self::createRouter(self::$web_router = new Router(), false);
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
	 * Releases everything that belongs to one request.
	 *
	 * OZone serves one request per process: `Context::release()` is never needed because the request
	 * ends in `exit`, and a second root context is refused while the first is alive. A persistent
	 * worker (RoadRunner, Swoole, FrankenPHP worker mode) is the case this exists for -- it calls
	 * this between requests, keeping the process-wide state that is *meant* to live on: the routers,
	 * the settings, the database, the job stores, the cron tasks, the registries, all of which are
	 * configuration and cost nothing to keep.
	 *
	 * Anything else kept outside the `Context` releases itself on {@see EndRequestHook}, with a
	 * listener registered once in `boot()` -- the framework's own (the OpenAPI spec object, the
	 * "an error was already handled" flag, the runtime cache) in
	 * {@see MainBootHookReceiver::boot()}, an application's in its boot hook
	 * receivers. The context tree is released last, whatever the listeners did, since it is also
	 * what lets the next request own a root.
	 *
	 * {@see self::handleRequest()} calls it before and after each request.
	 */
	public static function endRequest(): void
	{
		try {
			(new EndRequestHook(Context::hasRoot() ? Context::root() : null))->dispatch();
		} catch (Throwable $t) {
			// A failing cleanup must not keep the next request from starting.
			oz_logger($t);
		} finally {
			Context::release();
		}
	}

	/**
	 * The failure of {@see Db::init()} at bootstrap, when there was one.
	 *
	 * Only ever set in CLI mode: a web request fails at bootstrap instead. Anything touching the
	 * database afterwards throws the same failure again, so this is for reporting it
	 * ({@see DoctorCmd}), not for deciding whether to go on.
	 *
	 * @return null|Throwable
	 */
	public static function getDbInitError(): ?Throwable
	{
		return self::$db_init_error;
	}

	/**
	 * Check if we have database access.
	 *
	 * @return bool
	 */
	public static function hasDbAccess(): bool
	{
		// Once true, true for the process (the connection is kept); a "no" is asked again, at most
		// once per request, since a worker can outlive the database coming up.
		if (self::$has_db_access) {
			return true;
		}

		return self::$has_db_access = CacheRegistry::runtime(__METHOD__)->remember(
			'value',
			static function (): bool {
				try {
					db()->getConnection();

					return true;
				} catch (Throwable) {
					// Not only a PDOException: the schema itself may have failed to load, and this
					// method answers a yes/no question in both cases.
					return false;
				}
			}
		);
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

		// Once true, true for the process: installing is one-way, so steady state costs no query. A
		// "no" is asked again, at most once per request, or a worker running while the project is
		// installed would report no super admin until it restarts.
		if (self::$has_super_admin) {
			return true;
		}

		return self::$has_super_admin = CacheRegistry::runtime(__METHOD__)->remember(
			'value',
			static function (): bool {
				$roles_qb = new OZRolesQuery();
				$results  = $roles_qb->whereRoleIs(Role::SUPER_ADMIN)
					->whereIsValid()
					->find(ORMOptions::makePaginated(1));

				return (bool) $results->count();
			}
		);
	}

	/**
	 * Bootstrap the app.
	 *
	 * This bootstraps the app and returns the {@see Context} for web and cli mode.
	 */
	public static function bootstrap(AppInterface $app): Context
	{
		if (null !== self::$app_instance) {
			\trigger_error('The app is already running.');

			return Context::root();
		}

		self::$app_instance = $app;

		ErrorUtils::registerHandlers();

		// Building the current scope adds its stateful settings as a source: here, before any group
		// loads, rather than when something first asks for the scope (a log line, a temp file), which
		// left every group loaded before that without the scope's overrides.
		$app->getScope();

		// Production, outside the console: classes found through the map `oz project build` wrote.
		if (!self::isCliMode() && self::inProductionMode() && \is_file($class_map = self::classMapFile())) {
			ClassLoader::useClassMap(include $class_map);
		}

		$app->boot();

		Plugins::boot();

		self::notifyBootHookReceivers();

		// Once per process, when the database is first initialized: listeners attach to the ORM
		// classes, and read each request's context when an event fires.
		DbReadyHook::listen(static function (): void {
			TableCRUD::registerListeners();
		});

		Db::registerTypes();

		// After the boot hook receivers: init() refuses to run before them.
		Db::initOnFirstUse();

		// A request initializes the database when it first uses it (`db()`): one that does not --
		// most anonymous API calls, static pages -- never builds the schema. The command line
		// initializes it here, so a schema that cannot be prepared -- a migration version with no
		// file, a malformed `oz.db.schema`, a plugin whose tables fail to load -- is recorded rather
		// than fatal: `oz doctor` and `oz migrations rollback` are how that gets diagnosed and fixed.
		// Whatever touches the database afterwards throws it again, since Db::$db stays unset.
		if (self::isCliMode()) {
			try {
				Db::init();
			} catch (Throwable $t) {
				self::$db_init_error = $t;
			}
		}

		$is_cli_mode    = self::isCliMode();
		$is_web_context = \defined('OZ_OZONE_IS_WEB_CONTEXT');
		$is_api_context = !$is_web_context;

		// A worker bootstraps before its first request, so the process environment holds no request
		// to read (no host, no URI). The boot context is released by the first handleRequest().
		if ($is_cli_mode || Runtime::isPersistent()) {
			$http_env = HTTPEnvironment::mock();
		} else {
			$http_env = new HTTPEnvironment($_SERVER);
		}

		$context = new Context($http_env, null, null, $is_api_context);

		(new InitHook($context))->dispatch();

		return $context;
	}

	/**
	 * Check if we are in dev mode.
	 *
	 * @return bool
	 */
	public static function inDevMode(): bool
	{
		return 'production' !== env('ENV_MODE', 'development');
	}

	/**
	 * Check if we are in production mode.
	 *
	 * @return bool
	 */
	public static function inProductionMode(): bool
	{
		return 'production' === env('ENV_MODE', 'development');
	}

	/**
	 * The class map of this release, written by `oz project build`: in the app's project directory,
	 * named after it (a deployment makes a new one) and OZone's version.
	 *
	 * @internal
	 */
	public static function classMapFile(): string
	{
		$root = \rtrim(app()->getProjectDir()->getRoot(), '/\\');

		return $root . DS . '.ozone' . DS . 'cache' . DS . 'classmap.'
			. \hash('xxh128', $root . "\0" . OZ_OZONE_VERSION) . '.php';
	}

	/**
	 * Registers a router's routes, then dispatches {@see RouterCreated}.
	 *
	 * In production, outside the console, requests are routed through a {@see RouteTable}: the first
	 * router of a release registers every provider and saves the table, the next ones register only
	 * the provider of the route each request needs.
	 *
	 * @param Router $router the router, already held by the static a provider may read it from
	 * @param bool   $api    true for the API router, false for the web one
	 */
	private static function createRouter(Router $router, bool $api): void
	{
		$providers = $api ? self::getApiRoutesProviders() : self::getWebRoutesProviders();
		$file      = self::routeTableFile($api, $providers);
		$table     = null === $file ? null : RouteTable::load($file);

		$group = $router->group('/', static function (Router $router) use ($providers, $table): void {
			$router->registerProviders($providers, $table);
		})->withAuthentication(...($api ? Auth::apiAuthMethods() : Auth::webAuthMethods()));

		(new RouterCreated($router, $group, $api))->dispatch();

		if (null !== $file && null === $table) {
			$router->compileTable()
				->save($file);
		}
	}

	/**
	 * The file of a router's route table, or null when routers register every provider: in the
	 * console, which has none of a scope's settings, and outside production, where the routes change
	 * with the code being written.
	 *
	 * The name says what the table was compiled from, so a file never describes other routes -- opcache
	 * may never check it again: the release (a deployment makes a new project directory), the providers,
	 * and the application's and the scope's stateful settings, which the providers may read
	 * (Settings::set() touches the directory it writes). Kept in the scope's cache directory.
	 *
	 * @param bool                $api       true for the API router, false for the web one
	 * @param array<string, bool> $providers the router's route providers
	 */
	private static function routeTableFile(bool $api, array $providers): ?string
	{
		if (self::isCliMode() || !self::inProductionMode()) {
			return null;
		}

		$app   = app();
		$scope = scope();
		$key   = \hash('xxh128', \serialize([
			OZ_PROJECT_DIR,
			OZ_OZONE_VERSION,
			$providers,
			@\filemtime(StateLayout::path($app, StateLayout::SETTINGS)),
			@\filemtime(StateLayout::path($scope, StateLayout::SETTINGS)),
		]));

		return \rtrim($scope->getCacheDir()->getRoot(), DS) . DS . 'routes' . DS
			. ($api ? 'api' : 'web') . '.' . $key . '.php';
	}

	/**
	 * Notify all boot hook receivers.
	 */
	private static function notifyBootHookReceivers(): void
	{
		$hook_receivers = Settings::load('oz.boot');

		foreach ($hook_receivers as $receiver => $enabled) {
			if (!$enabled) {
				continue;
			}

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

		self::$boot_hook_receivers_notified = true;
	}
}
