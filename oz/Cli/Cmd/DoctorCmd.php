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

namespace OZONE\Core\Cli\Cmd;

use Closure;
use Kli\KliArgs;
use Kli\KliStyle;
use Kli\Table\Interfaces\KliTableCellFormatterInterface;
use Kli\Table\KliTable;
use Kli\Table\KliTableHeader;
use Override;
use OZONE\Core\App\InstanceLayout;
use OZONE\Core\App\Settings;
use OZONE\Core\Auth\AuthUsersRepository;
use OZONE\Core\Cli\Build\ProjectBuilder;
use OZONE\Core\Cli\Command;
use OZONE\Core\Cli\Cron\CronRunner;
use OZONE\Core\Cli\Deploy\DeployInitializer;
use OZONE\Core\Cli\Process;
use OZONE\Core\Cli\Utils\Requirements;
use OZONE\Core\Cli\Utils\Utils;
use OZONE\Core\Exceptions\RuntimeException;
use OZONE\Core\Exceptions\Utils\ErrorUtils;
use OZONE\Core\FS\Drivers\PublicLocalStorage;
use OZONE\Core\Hooks\Events\DoctorCheckHook;
use OZONE\Core\Lang\Message\PluralRules;
use OZONE\Core\Lang\Polyglot;
use OZONE\Core\Loader\ClassLoader;
use OZONE\Core\Maintenance\Cli\MaintenanceCmd;
use OZONE\Core\Maintenance\Maintenance;
use OZONE\Core\Messaging\Messenger;
use OZONE\Core\Migrations\Enums\MigrationsState;
use OZONE\Core\Migrations\Migrations;
use OZONE\Core\OZone;
use OZONE\Core\Scopes\Interfaces\ScopeInterface;
use OZONE\Core\Scopes\StateLayout;
use OZONE\Core\Web\DefaultRobotsTxtProvider;
use ReflectionFunction;
use Throwable;

/**
 * Class DoctorCmd.
 *
 * Answers "can this machine run OZone, and is this project able to start?" in one place, so the
 * `install` script only has to check PHP itself and can point here for everything else.
 */
final class DoctorCmd extends Command
{
	public const string OK   = 'ok';
	public const string WARN = 'warn';
	public const string FAIL = 'fail';

	/**
	 * Runs every check and reports.
	 */
	public function check(KliArgs $args): void
	{
		$checks = \array_merge($this->environmentChecks(), $this->projectChecks(), self::pluginChecks());
		$failed = \array_filter($checks, static fn (array $c): bool => self::FAIL === $c['status']);

		$cli = $this->getCli();

		if ($args->get('json')) {
			$cli->writeJson(['checks' => $checks], empty($failed), empty($failed) ? 0 : 1);
		}

		$this->report($checks);

		if (!empty($failed)) {
			$cli->error(\sprintf(
				'%d check(s) failed. Fix them, then run "oz doctor check" again.',
				\count($failed)
			), true, 1);
		}
	}

	/**
	 * Whether the default auth user type (`OZ_AUTH_USER_DEFAULT_TYPE`) is one a repository serves,
	 * with a table made by `makeAuthUserTable()`: what sign-up, `oz auth-users` and the email and
	 * phone columns use where no type is named. A project without `oz_users` names one of its own.
	 *
	 * @return list<array{name: string, status: string, detail: string, fix: string}>
	 */
	public static function defaultAuthUserTypeChecks(): array
	{
		$fix = 'Set OZ_AUTH_USER_DEFAULT_TYPE (oz.auth.users) to an auth user type of'
			. ' oz.auth.users.repositories whose table makeAuthUserTable() made.';

		try {
			$type  = AuthUsersRepository::defaultType();
			$table = db()->getTableByMorphType($type);
		} catch (Throwable $t) {
			return [self::row('Default auth user type', self::FAIL, self::rootCause($t), $fix)];
		}

		if (!$table || !AuthUsersRepository::isTableSupported($table)) {
			return [self::row(
				'Default auth user type',
				self::FAIL,
				\sprintf('"%s" has no table made by makeAuthUserTable()', $type),
				$fix
			)];
		}

		return [self::row('Default auth user type', self::OK, \sprintf('"%s" (%s)', $type, $table->getName()), '')];
	}

	/**
	 * Whether two scopes of the project declare the same class in their own directories.
	 *
	 * The project namespace reads a scope's `scopes/{name}/` directory too, and a process that calls
	 * into another scope reads that scope's as well, for good: PHP cannot unload a class. Of two
	 * scopes declaring one class, the first to load it wins, so the other scope runs the wrong one.
	 *
	 * @return list<array{name: string, status: string, detail: string, fix: string}>
	 */
	public static function scopeClassesChecks(): array
	{
		$prefix = \trim((string) Settings::get('oz.config', 'OZ_PROJECT_NAMESPACE'), '\\') . '\\';
		$root   = app()->getProjectDir()->resolve('scopes');
		$owners = [];

		foreach (\glob($root . DS . '*', \GLOB_ONLYDIR) ?: [] as $dir) {
			foreach (ClassLoader::classesUnder($prefix, $dir) as $class => $_) {
				$first = \strstr(\substr($class, \strlen($prefix)), '\\', true);

				// The scope's settings and templates are no classes, whatever their names.
				if ('settings' !== $first && 'templates' !== $first) {
					$owners[$class][] = \basename($dir);
				}
			}
		}

		$rows = [];

		foreach ($owners as $class => $scopes) {
			if (\count($scopes) > 1) {
				$rows[] = self::row(
					'Scope classes',
					self::WARN,
					\sprintf('%s is declared by the scopes %s', $class, \implode(', ', $scopes)),
					'Rename it in all but one of them, or move it to app/ if they share it: a process'
						. ' calling from one into another runs whichever it loaded first.'
				);
			}
		}

		return [] === $rows ? [self::row('Scope classes', self::OK, 'no class declared by two scopes', '')] : $rows;
	}

	/**
	 * Where direct links to public files point: a failure when direct access is on and
	 * `OZ_PUBLIC_FILES_BASE_URL` is missing or invalid, since every link would then throw.
	 *
	 * Where `oz deploy init --target=bare` wrote the root scope's vhost, the fix names the URL it
	 * serves the public files directory on: its document root is `public/`, which links that
	 * directory.
	 *
	 * @return list<array{name: string, status: string, detail: string, fix: string}>
	 */
	public static function publicFilesChecks(): array
	{
		$name = 'Public files';

		if (!Settings::get('oz.files', 'OZ_PUBLIC_URI_DIRECT_ACCESS_ENABLED')) {
			return [self::row($name, self::OK, 'served through the file route (direct access off)', '')];
		}

		try {
			$base = PublicLocalStorage::baseUrl();
		} catch (RuntimeException $e) {
			return [self::row($name, self::FAIL, $e->getMessage(), self::publicFilesFix())];
		}

		if (null === $base) {
			return [self::row(
				$name,
				self::FAIL,
				'direct access is on and OZ_PUBLIC_FILES_BASE_URL (oz.files) is not set',
				self::publicFilesFix()
			)];
		}

		return [self::row($name, self::OK, 'linked at ' . $base, '')];
	}

	#[Override]
	protected function describe(): void
	{
		$this->description('Check that this machine and project can run OZone.');

		$check = $this->action('check', 'Check PHP, the required extensions, the tools and the project.');
		$check->option('json', 'j', [], 1)
			->description('Output the checks as JSON, for CI.')
			->bool()
			->def(false);
		$check->handler($this->check(...));
	}

	/**
	 * What the plugins check, through {@see DoctorCheckHook}.
	 *
	 * Each listener run on its own: one that throws is a failed check naming it, and the others
	 * still report, since a report one broken plugin could empty is a report nobody can trust.
	 *
	 * @return list<array{name: string, status: string, detail: string, fix: string}>
	 */
	private static function pluginChecks(): array
	{
		$hook = new DoctorCheckHook();

		$hook->dispatch(static function (callable $listener, DoctorCheckHook $hook): void {
			try {
				$listener($hook);
			} catch (Throwable $t) {
				$hook->add(
					self::nameOf($listener),
					self::FAIL,
					self::rootCause($t),
					'Fix the check, or the plugin that adds it.'
				);
			}
		});

		return $hook->getRows();
	}

	/**
	 * What a listener is called in a report: its class and method where it has them.
	 */
	private static function nameOf(callable $listener): string
	{
		if (\is_array($listener)) {
			return (\is_object($listener[0]) ? $listener[0]::class : $listener[0]) . '::' . $listener[1];
		}

		if ($listener instanceof Closure) {
			$function = new ReflectionFunction($listener);
			$class    = $function->getClosureScopeClass()?->getName();

			return null === $class ? $function->getName() : $class . '::' . $function->getName();
		}

		return \is_string($listener) ? $listener : 'a doctor check';
	}

	/**
	 * Checks that do not need a project.
	 *
	 * @return list<array{name: string, status: string, detail: string, fix: string}>
	 */
	private function environmentChecks(): array
	{
		$satisfied = Requirements::phpVersionSatisfied();
		$checks    = [
			self::row(
				'PHP version',
				$satisfied ? self::OK : self::FAIL,
				\PHP_VERSION,
				$satisfied ? '' : \sprintf('OZone requires PHP %s.', Requirements::phpConstraint())
			),
		];

		foreach (Requirements::extensions() as $name) {
			$loaded = \extension_loaded($name);

			$checks[] = self::row(
				'ext-' . $name,
				$loaded ? self::OK : self::FAIL,
				$loaded ? 'loaded' : 'missing',
				$loaded ? '' : \sprintf('Install the PHP "%s" extension.', $name)
			);
		}

		foreach (['composer' => 'Composer', 'git' => 'git'] as $bin => $label) {
			$found = Process::which($bin);

			$checks[] = self::row(
				$label,
				null !== $found ? self::OK : self::WARN,
				$found ?? 'not on PATH',
				null !== $found ? '' : \sprintf('Install %s and make sure it is on PATH.', $label)
			);
		}

		$checks[] = self::phpCliCheck();

		return $checks;
	}

	/**
	 * The PHP binary this process starts a PHP process with ({@see Process::phpBinary()}). On the
	 * command line it is this one unless `OZ_PHP_CLI_BINARY` says otherwise, so what fails here is
	 * mostly the setting, which a request would have refused the same way.
	 *
	 * @return array{name: string, status: string, detail: string, fix: string}
	 */
	private static function phpCliCheck(): array
	{
		try {
			$binary = Process::phpBinary();
		} catch (RuntimeException $e) {
			return self::row('PHP CLI', self::FAIL, $e->getMessage(), \sprintf(
				'Set %s (%s, or the .env) to the absolute path of the PHP command-line binary.',
				Process::PHP_CLI_SETTING,
				Process::PHP_CLI_SETTING_GROUP
			));
		}

		$detail = null === Process::configuredPhpBinary() ? $binary : $binary . ' (' . Process::PHP_CLI_SETTING . ')';

		return self::row('PHP CLI', self::OK, $detail, '');
	}

	/**
	 * Checks that need a loaded project. Empty outside one.
	 *
	 * @return list<array{name: string, status: string, detail: string, fix: string}>
	 */
	private function projectChecks(): array
	{
		if (!Utils::isProjectLoaded()) {
			return [
				self::row(
					'Project',
					self::WARN,
					'none in ' . \getcwd(),
					'Run "oz project create" here, or change to a project directory for the project checks.'
				),
			];
		}

		$app    = app();
		$checks = [
			self::row('Project', self::OK, OZ_PROJECT_DIR, ''),
		];

		// .env, and the two keys nothing works without.
		$env_file = OZ_PROJECT_DIR . '.env';

		if (!\is_file($env_file) || !\is_readable($env_file)) {
			$checks[] = self::row('.env', self::FAIL, 'missing or unreadable', 'Create ' . $env_file . '.');
		} else {
			$checks[] = self::row('.env', self::OK, $env_file, '');

			foreach (['OZ_APP_SALT', 'OZ_APP_SECRET'] as $key) {
				$set = !empty($app->getEnv()->get($key));

				$checks[] = self::row(
					$key,
					$set ? self::OK : self::FAIL,
					$set ? 'set' : 'missing',
					$set ? '' : \sprintf('Set %s in .env (base64 of 64 random bytes).', $key)
				);
			}
		}

		// The state directories have to be writable by the process that serves requests.
		$state_dirs = [
			'data'              => $app->getDataDir(),
			InstanceLayout::DIR => $app->getProjectDir()->cd(InstanceLayout::DIR, true),
		];

		foreach ($state_dirs as $label => $fm) {
			$root     = $fm->getRoot();
			$writable = \is_dir($root) && \is_writable($root);

			$checks[] = self::row(
				$label . '/ writable',
				$writable ? self::OK : self::FAIL,
				$root,
				$writable ? '' : \sprintf('Make %s writable by the user running OZone.', $root)
			);
		}

		// Whether an error raised while the project boots is logged at all. The autoloader registers the
		// handlers when the boot file, the scope's index.php or `oz` has said a project is starting; a
		// boot file edited into requiring the autoloader first says nothing, and everything until
		// `bootstrap()` is then handled by PHP alone -- its own error log, never `.ozone/logs/`.
		$early = ErrorUtils::registeredByTheAutoloader();

		$checks[] = self::row(
			'Boot-time errors',
			$early ? self::OK : self::WARN,
			$early ? 'logged (handlers registered by the autoloader)' : 'logged by PHP only',
			$early ? '' : 'Define OZ_PROJECT_DIR in app/boot.php before it requires vendor/autoload.php,'
				. ' as the generated boot file does.'
		);

		return \array_merge(
			$checks,
			$this->maintenanceChecks(),
			$this->databaseChecks(),
			$this->buildChecks(),
			$this->cronChecks(),
			$this->messagingChecks(),
			$this->translationChecks(),
			self::publicFilesChecks(),
			$this->robotsTxtChecks(),
			self::scopeClassesChecks(),
			self::defaultAuthUserTypeChecks()
		);
	}

	/**
	 * Whether a maintenance is in force: since when, what it holds, whether the jobs wait. A warning
	 * while it lasts, which a release check reads past since a deploy may run under one; a failure
	 * when its record cannot be read, which holds every scope and pauses the jobs until someone
	 * looks.
	 *
	 * @return list<array{name: string, status: string, detail: string, fix: string}>
	 */
	private function maintenanceChecks(): array
	{
		try {
			$mode = Maintenance::current();
		} catch (Throwable $t) {
			return [self::row('Maintenance', self::WARN, 'not checked: ' . self::rootCause($t), '')];
		}

		if (null === $mode) {
			return [self::row('Maintenance', self::OK, 'off', '')];
		}

		$detail = MaintenanceCmd::summary($mode);

		if (!$mode->isReadable()) {
			return [self::row(
				'Maintenance',
				self::FAIL,
				$detail,
				'Run "oz maintenance off" to serve again, or "oz maintenance on" to write a new record.'
			)];
		}

		return [self::row('Maintenance', self::WARN, $detail, 'Run "oz maintenance off" when it is over.')];
	}

	/**
	 * Whether every enabled messaging channel sends: one whose transport only writes to the log, or
	 * is unavailable, sends no verification email and no two-factor code. A failure in production, so
	 * a release stops on it.
	 *
	 * @return list<array{name: string, status: string, detail: string, fix: string}>
	 */
	private function messagingChecks(): array
	{
		try {
			$not_sending = Messenger::channelsNotSending();
		} catch (Throwable $t) {
			return [self::row(
				'Messaging',
				self::FAIL,
				self::rootCause($t),
				'Fix OZ_MESSAGING_CHANNELS (oz.messaging).'
			)];
		}

		if (empty($not_sending)) {
			return [self::row('Messaging', self::OK, 'every enabled channel has a transport', '')];
		}

		$checks = [];

		foreach ($not_sending as $channel => $reason) {
			$checks[] = self::row(
				'Messaging: ' . $channel,
				OZone::inProductionMode() ? self::FAIL : self::WARN,
				$reason,
				\sprintf(
					'Give the channel "%s" a transport in OZ_MESSAGING_CHANNELS (oz.messaging), or disable it.',
					$channel
				)
			);
		}

		return $checks;
	}

	/**
	 * Whether every text of the enabled catalogs follows the message syntax, and whether the plural
	 * categories they rely on can be answered: only ext-intl knows them on the server.
	 *
	 * @return list<array{name: string, status: string, detail: string, fix: string}>
	 */
	private function translationChecks(): array
	{
		$check  = Polyglot::checkCatalogs();
		$errors = \count($check['errors']);
		$rows   = [
			self::row(
				'Translations',
				0 === $errors ? self::OK : self::FAIL,
				0 === $errors ? 'every text follows the message syntax' : \sprintf('%d text(s) do not parse', $errors),
				0 === $errors ? '' : 'Run "oz lang export" to see them, and fix the catalogs.'
			),
		];

		if (PluralRules::available()) {
			$rows[] = self::row('Plural rules', self::OK, 'ext-intl', '');
		} elseif ($check['categories']) {
			$rows[] = self::row(
				'Plural rules',
				self::WARN,
				'a catalog picks plurals by category (one, few...), and ext-intl is not loaded',
				'Install ext-intl, or write those plurals with =N, comparisons and other: without it the'
					. ' server always writes the other branch.'
			);
		}

		return $rows;
	}

	/**
	 * How to set `OZ_PUBLIC_FILES_BASE_URL`, with the URL of the root scope's vhost when there is one.
	 */
	private static function publicFilesFix(): string
	{
		$fix  = 'Set OZ_PUBLIC_FILES_BASE_URL (oz.files) to the URL the web server, a subdomain or a CDN'
			. ' serves the public files directory on, or turn OZ_PUBLIC_URI_DIRECT_ACCESS_ENABLED off.';
		$root = ScopeInterface::ROOT_SCOPE;

		if (\is_file(app()->getProjectDir()->resolve(DeployInitializer::nginxVhostPath($root)))) {
			$fix .= \sprintf(
				' The root vhost of "oz deploy init" serves it: https://%s/%s/',
				DeployInitializer::serverName($root),
				StateLayout::PUBLIC_LINK
			);
		}

		return $fix;
	}

	/**
	 * Whether `/robots.txt` reaches its route in every scope.
	 *
	 * A scope added before it was a route holds a `robots.txt` in its document root, and a `.htaccess`
	 * of the same age answering `/robots.txt` from that file: what the route answers, a provider
	 * included, is then never read. Nothing is removed for the project: under a web server that still
	 * answers from the file, removing it answers 404, which a crawler reads as everything allowed. So
	 * these are warnings, the file saying what it always said until the server sends the path on.
	 *
	 * @return list<array{name: string, status: string, detail: string, fix: string}>
	 */
	private function robotsTxtChecks(): array
	{
		$rows    = [];
		$default = self::robotsTxtRules(DefaultRobotsTxtProvider::CONTENT);
		$route   = 'Make the web server send /robots.txt to OZone (the .htaccess "oz scopes add" writes,'
			. ' or nginx: location = /robots.txt { rewrite ^ /index.php last; }), then delete the file:'
			. ' a server that answers it from a file answers 404 without it, which a crawler reads as'
			. ' everything allowed.';

		foreach (ProjectBuilder::scopes() as $scope) {
			$root     = OZ_PROJECT_DIR . 'public' . DS . $scope . DS;
			$file     = $root . 'robots.txt';
			$htaccess = \is_file($root . '.htaccess') ? (string) \file_get_contents($root . '.htaccess') : '';
			$details  = [];
			$fixes    = [];

			if (\is_file($file)) {
				if (self::robotsTxtRules((string) \file_get_contents($file)) === $default) {
					$details[] = $file . ' has the rules the route answers by default';
					$fixes[]   = $route;
				} else {
					$details[] = $file . ' has rules of its own, which the route does not answer';
					$fixes[]   = 'Answer them from a provider named in OZ_ROBOTS_TXT_PROVIDER (oz.request). ' . $route;
				}
			}

			// The condition every .htaccess written before carried, keeping the path from the route.
			if (\str_contains($htaccess, '!^/robots\.txt')) {
				$details[] = $root . '.htaccess answers /robots.txt from a file';
				$fixes[]   = 'Remove its "!^/robots\.txt" condition; a web scope also needs'
					. ' "RewriteRule ^robots\.txt$ index.php [L]" before its file conditions.';
			}

			if ([] !== $details) {
				$rows[] = self::row(
					'robots.txt: ' . $scope,
					self::WARN,
					\implode('; ', $details),
					\implode(' ', \array_unique($fixes))
				);
			}
		}

		return [] === $rows ? [self::row('robots.txt', self::OK, 'answered by its route', '')] : $rows;
	}

	/**
	 * The rules of a `robots.txt`: its lines without comments and blank lines, so two files that
	 * differ only in those say the same thing.
	 *
	 * @return list<string>
	 */
	private static function robotsTxtRules(string $text): array
	{
		$rules = [];

		foreach (\preg_split('~\R~', $text) ?: [] as $line) {
			$line = \trim(\explode('#', $line, 2)[0]);

			if ('' !== $line) {
				$rules[] = $line;
			}
		}

		return $rules;
	}

	/**
	 * When cron last ran, and whether a scheduler runs it.
	 *
	 * @return list<array{name: string, status: string, detail: string, fix: string}>
	 */
	private function cronChecks(): array
	{
		if (null !== OZone::getDbInitError()) {
			return [];
		}

		try {
			$last      = CronRunner::lastTick();
			$scheduler = CronRunner::lastSchedulerTick();
		} catch (Throwable $t) {
			return [self::row('Cron', self::WARN, 'not checked: ' . self::rootCause($t), '')];
		}

		$production = OZone::inProductionMode();
		$mode       = CronRunner::mode();
		$scheduled  = null !== $scheduler && (\time() - $scheduler) <= CronRunner::schedulerTimeout();
		$fix        = 'Run "oz cron run" every minute (crontab, a systemd timer, a host\'s cron panel), or'
			. ' "oz cron work" as a service.';

		if (null === $last) {
			return [self::row('Cron', $production ? self::WARN : self::OK, 'never ran', $production ? $fix : '')];
		}

		$detail = \sprintf('%s, %s ago', $last['runner'], self::ago(\time() - $last['at']));

		// In production without a scheduler, requests run the due tasks (auto), or nothing does.
		if ($production && !$scheduled && CronRunner::RUNNER_REQUESTS !== $mode) {
			if (CronRunner::RUNNER_AUTO === $mode) {
				$fix .= ' Until then, requests run the due tasks, and only when there are requests.';
			}

			return [self::row('Cron', self::WARN, $detail . ', no scheduler', $fix)];
		}

		return [self::row('Cron', self::OK, $detail, '')];
	}

	/**
	 * Database reachability and migration state.
	 *
	 * @return list<array{name: string, status: string, detail: string, fix: string}>
	 */
	private function databaseChecks(): array
	{
		// A schema that failed to load at bootstrap: recorded there rather than fatal, so that this
		// command can report it (OZone::getDbInitError()).
		if (null !== ($error = OZone::getDbInitError())) {
			return [
				self::row(
					'Database schema',
					self::FAIL,
					self::rootCause($error),
					'The schema could not be prepared: check oz.db.schema, the plugins it loads, and'
						. ' that OZ_MIGRATION_VERSION matches a migration file.'
				),
			];
		}

		try {
			db()->getConnection();
		} catch (Throwable $t) {
			return [
				self::row(
					'Database',
					self::FAIL,
					self::rootCause($t),
					'Check the OZ_DB_* values in .env, and that the server is reachable.'
				),
			];
		}

		$checks = [self::row('Database', self::OK, 'reachable', '')];

		try {
			$state       = Migrations::getState();
			$db_version  = Migrations::getCurrentDbVersion(true);
			$src_version = Migrations::getSourceCodeDbVersion();

			[$status, $detail, $fix] = match ($state) {
				MigrationsState::NOT_INSTALLED => [
					self::WARN,
					'not installed',
					0 === $src_version
						? 'Run "oz migrations create" then "oz migrations run".'
						: 'Run "oz migrations run".',
				],
				MigrationsState::INSTALLED => [self::OK, 'version ' . $db_version, ''],
				MigrationsState::PENDING   => [
					self::FAIL,
					\sprintf('database at %d, migrations up to %d', $db_version, $src_version),
					'Run "oz migrations run".',
				],
				MigrationsState::ROLLBACK => [
					self::FAIL,
					\sprintf('database at %d, migrations only up to %d', $db_version, $src_version),
					'The database is ahead of the code: deploy the matching version, or roll back.',
				],
			};

			$checks[] = self::row('Migrations', $status, $detail, $fix);

			// An installed project nobody can administer: no project needs a web installer to fix it.
			if (MigrationsState::INSTALLED === $state) {
				$has_super_admin = OZone::hasSuperAdmin();

				$checks[] = self::row(
					'Super admin',
					$has_super_admin ? self::OK : self::WARN,
					$has_super_admin ? 'present' : 'none',
					$has_super_admin
						? ''
						: 'Give the role to an auth user: "oz auth-users grant --user=<id|email> --role=super-admin".'
				);
			}
		} catch (Throwable $t) {
			$checks[] = self::row('Migrations', self::WARN, $t->getMessage(), '');
		}

		return $checks;
	}

	/**
	 * In production: whether what `oz project build` compiles is there, and still matches the code.
	 *
	 * Drift is a warning: requests compile what a build left out, the first ones paying for it. A
	 * stale preload list is worse -- PHP serves the code it preloaded until it restarts -- but only
	 * the PHP that serves requests knows what it preloaded. Two things fail: a build that came out
	 * short of what the project asked for ({@see self::buildResultCheck()}), and a production release
	 * with no build at all -- one never built, or one whose build did not finish. The second was a
	 * warning until a scope build was killed mid-deploy: it wrote nothing, so there was nothing short
	 * to find, and the check that exists to stop that release from shipping passed it.
	 *
	 * @return list<array{name: string, status: string, detail: string, fix: string}>
	 */
	private function buildChecks(): array
	{
		if (!OZone::inProductionMode()) {
			return [self::row('Production build', self::OK, 'not needed outside production', '')];
		}

		$rebuild = 'Run "oz project build".';

		if (null !== OZone::getDbInitError()) {
			// The generated ORM classes are mapped too, and reaching them needs the schema.
			$build = [self::WARN, 'not checked: the database schema failed to load', ''];
		} else {
			try {
				$drift = ProjectBuilder::classMapDrift();

				if (null === $drift) {
					// Not drift: there is nothing to have drifted. Every build writes the class map, so a
					// production release without one was never built or was built by a run that did not
					// reach the end, and both serve requests that compile the whole of it themselves.
					$build = [self::FAIL, 'not built for this release', $rebuild];
				} elseif ($drift > 0) {
					$build = [self::WARN, \sprintf('%d class(es) added, moved or removed since', $drift), $rebuild];
				} else {
					$build = [self::OK, 'up to date', ''];
				}
			} catch (Throwable $t) {
				$build = [self::WARN, 'not checked: ' . self::rootCause($t), ''];
			}
		}

		$checks = [self::row('Production build', ...$build)];

		if (null !== ($result = ProjectBuilder::result())) {
			$checks[] = self::buildResultCheck($result['scopes'], $rebuild);
		} else {
			// Said, rather than left out: a gate reads a row it cannot find as a row with no news, and
			// the release where this was wanted had no result file at all. A warning and not a failure
			// because a release built by an older OZone has none and is not wrong for it; one of this
			// OZone that wrote none did not finish, and the row above fails for it -- the result file is
			// written before the class map, so there is no build that wrote the second and not the first.
			$checks[] = self::row('Build result', self::WARN, 'not written for this release', $rebuild);
		}

		// Only once a build wrote the preload script: a project served in worker mode, or built
		// with --no-preload, has none.
		if (\is_file(ProjectBuilder::preloadFile())) {
			$drift   = ProjectBuilder::preloadDrift();
			$restart = $rebuild . ' Then restart PHP: preloaded code only changes when PHP restarts.';

			if (null === $drift) {
				$preload = [self::WARN, 'no preload list for this release', $restart];
			} elseif ($drift['missing'] + $drift['changed'] > 0) {
				$preload = [self::WARN, \sprintf(
					'%d of its %d files changed or gone since',
					$drift['missing'] + $drift['changed'],
					$drift['files']
				), $restart];
			} else {
				$preload = [self::OK, \sprintf('%d files, up to date', $drift['files']), ''];
			}

			$checks[] = self::row('Preload', ...$preload);
		}

		return $checks;
	}

	/**
	 * Whether the last build of this release compiled everything it set out to.
	 *
	 * A failure and not a warning, unlike the drift checks around it: drift means the build was
	 * whole and the code has moved since, while this means the build itself came out short of what
	 * the project asked for, which no amount of waiting corrects. Requests do compile what is
	 * missing, each paying for it, so what fails is the release and not the serving of it -- which
	 * is exactly what a deploy must stop on, and what reading two counts off a terminal was the
	 * only guard against.
	 *
	 * @param array<string, array{
	 *     settings_bundles: int,
	 *     settings_sources: int,
	 *     settings_failed: array<string, string>,
	 *     route_tables: int,
	 *     route_tables_failed: list<string>,
	 *     descriptors?: array{peak: null|int, limit: null|int, hard: null|int},
	 * }> $scopes what the build wrote per scope ({@see ProjectBuilder::result()})
	 *
	 * @return array{name: string, status: string, detail: string, fix: string}
	 */
	private static function buildResultCheck(array $scopes, string $rebuild): array
	{
		$short = [];

		foreach ($scopes as $scope => $scope_result) {
			$missing = $scope_result['settings_sources'] - $scope_result['settings_bundles'];

			if ($missing > 0) {
				$short[] = \sprintf('scope "%s": %d settings source(s) not bundled', $scope, $missing);
			}

			if ([] !== $scope_result['route_tables_failed']) {
				$short[] = \sprintf(
					'scope "%s": %d route table(s) not written',
					$scope,
					\count($scope_result['route_tables_failed'])
				);
			}
		}

		if ([] !== $short) {
			return self::row('Build result', self::FAIL, \implode('; ', $short), $rebuild);
		}

		$bundles = 0;
		$tables  = 0;

		foreach ($scopes as $scope_result) {
			$bundles += $scope_result['settings_bundles'];
			$tables += $scope_result['route_tables'];
		}

		return self::row('Build result', self::OK, \sprintf(
			'%d scope(s), %d settings bundles, %d route table(s)',
			\count($scopes),
			$bundles,
			$tables
		), '');
	}

	/**
	 * Prints the checks as a table, then the fix hints of what is not ok. The table fits the
	 * terminal itself, wrapping a long detail, so it is printed as it is.
	 *
	 * @param list<array{name: string, status: string, detail: string, fix: string}> $checks
	 */
	private function report(array $checks): void
	{
		$cli   = $this->getCli();
		$table = new KliTable();

		$table->addHeader('Check', 'name')->alignLeft();
		$table->addHeader('Status', 'status')->alignCenter()->setCellFormatter(self::statusFormatter());
		$table->addHeader('Detail', 'detail')->alignLeft();
		$table->addRows($checks);

		$cli->writeLn((string) $table, false);

		foreach ($checks as $check) {
			if (self::OK === $check['status'] || '' === $check['fix']) {
				continue;
			}

			$cli->writeLn(\sprintf(
				' %s  %s: %s',
				self::FAIL === $check['status'] ? '!' : '~',
				$check['name'],
				$check['fix']
			));
		}
	}

	/**
	 * Colours the status cell without changing the value the JSON output carries.
	 */
	private static function statusFormatter(): KliTableCellFormatterInterface
	{
		return new class implements KliTableCellFormatterInterface {
			#[Override]
			public function format(mixed $value, KliTableHeader $header, array $row): string
			{
				return \strtoupper((string) $value);
			}

			#[Override]
			public function getStyle(mixed $value, KliTableHeader $header, array $row): ?KliStyle
			{
				return match (\strtolower($value)) {
					DoctorCmd::FAIL => new KliStyle()->red()->bold(),
					DoctorCmd::WARN => new KliStyle()->yellow(),
					default         => new KliStyle()->green(),
				};
			}
		};
	}

	/**
	 * @return array{name: string, status: string, detail: string, fix: string}
	 */
	private static function row(string $name, string $status, string $detail, string $fix): array
	{
		return ['name' => $name, 'status' => $status, 'detail' => $detail, 'fix' => $fix];
	}

	/**
	 * A duration, for a person to read.
	 */
	private static function ago(int $seconds): string
	{
		return match (true) {
			$seconds < 60   => $seconds . ' s',
			$seconds < 3600 => \intdiv($seconds, 60) . ' min',
			default         => \intdiv($seconds, 3600) . ' h',
		};
	}

	/**
	 * The message of the deepest cause of a throwable, which is the one that says what is wrong.
	 */
	private static function rootCause(Throwable $t): string
	{
		oz_logger()->debug($t);

		while (null !== ($previous = $t->getPrevious())) {
			$t = $previous;
		}

		return $t->getMessage();
	}
}
