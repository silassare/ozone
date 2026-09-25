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

use Kli\KliArgs;
use Kli\KliStyle;
use Kli\Table\Interfaces\KliTableCellFormatterInterface;
use Kli\Table\KliTable;
use Kli\Table\KliTableHeader;
use Override;
use OZONE\Core\Cli\Build\ProjectBuilder;
use OZONE\Core\Cli\Command;
use OZONE\Core\Cli\Cron\CronRunner;
use OZONE\Core\Cli\Utils\Requirements;
use OZONE\Core\Cli\Utils\Utils;
use OZONE\Core\Lang\Message\PluralRules;
use OZONE\Core\Lang\Polyglot;
use OZONE\Core\Migrations\Enums\MigrationsState;
use OZONE\Core\Migrations\Migrations;
use OZONE\Core\OZone;
use Throwable;

/**
 * Class DoctorCmd.
 *
 * Answers "can this machine run OZone, and is this project able to start?" in one place, so the
 * `install` script only has to check PHP itself and can point here for everything else.
 */
final class DoctorCmd extends Command
{
	public const OK   = 'ok';
	public const WARN = 'warn';
	public const FAIL = 'fail';

	/**
	 * Runs every check and reports.
	 *
	 * @param KliArgs $args
	 */
	public function check(KliArgs $args): void
	{
		$checks = \array_merge($this->environmentChecks(), $this->projectChecks());
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
	 * {@inheritDoc}
	 */
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
			$found = null !== self::which($bin);

			$checks[] = self::row(
				$label,
				$found ? self::OK : self::WARN,
				$found ? (string) self::which($bin) : 'not on PATH',
				$found ? '' : \sprintf('Install %s and make sure it is on PATH.', $label)
			);
		}

		return $checks;
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
			'data'   => $app->getDataDir(),
			'.ozone' => $app->getProjectDir()->cd('.ozone', true),
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

		return \array_merge(
			$checks,
			$this->databaseChecks(),
			$this->buildChecks(),
			$this->cronChecks(),
			$this->translationChecks()
		);
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
						: 'Give the role to a user: "oz users grant --user=<id|email> --role=super-admin".'
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
	 * Warnings, not failures: requests compile what a build left out, the first ones paying for it.
	 * A stale preload list is worse -- PHP serves the code it preloaded until it restarts -- but only
	 * the PHP that serves requests knows what it preloaded.
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
					$build = [self::WARN, 'not built for this release', $rebuild];
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
	 * Prints the checks as a table, then the fix hints of what is not ok.
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
		$table->addRows(\array_map(
			static function (array $check): array {
				$check['detail'] = \strlen($check['detail']) > 56
					? \substr($check['detail'], 0, 53) . '...'
					: $check['detail'];

				return $check;
			},
			$checks
		));

		$cli->writeLn((string) $table);

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

			if (\strlen($check['detail']) > 56) {
				$cli->writeLn('    ' . $check['detail']);
			}
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
					DoctorCmd::FAIL => (new KliStyle())->red()->bold(),
					DoctorCmd::WARN => (new KliStyle())->yellow(),
					default         => (new KliStyle())->green(),
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

	/**
	 * The absolute path of an executable on PATH, or null.
	 */
	private static function which(string $bin): ?string
	{
		$paths = \explode(\PATH_SEPARATOR, (string) \getenv('PATH'));

		foreach ($paths as $dir) {
			if ('' === $dir) {
				continue;
			}

			$path = \rtrim($dir, DS) . DS . $bin;

			if (\is_file($path) && \is_executable($path)) {
				return $path;
			}
		}

		return null;
	}
}
