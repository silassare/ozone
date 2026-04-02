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

namespace OZONE\Tests\Integration\Db;

use OZONE\Tests\Integration\Support\DbTestConfig;
use OZONE\Tests\Integration\Support\OZTestProject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

/**
 * Integration tests for the `oz migrations` command lifecycle.
 *
 * Runs the full state machine against every configured DB type via @dataProvider.
 * SQLite is always tested. MySQL / PostgreSQL are tested when their env vars are
 * set (see DbTestConfig::allConfigured()).
 *
 * One project directory is created per configured DB type and REUSED across all
 * test methods via the static project store. Tests run in declaration order so
 * state advances correctly for each RDBMS.
 *
 * Setup order: `oz db build --build-all --class-only` MUST precede every
 * migration command so ORM classes are available.
 *
 * @internal
 *
 * @coversNothing
 */
final class MigrationsTest extends TestCase
{
	/**
	 * Projects created per RDBMS, kept for tearDownAfterClass.
	 *
	 * @var array<string, OZTestProject>
	 */
	private static array $projects = [];

	/**
	 * SQLite database file per RDBMS key (null for server-based RDBMS).
	 *
	 * @var array<string, null|string>
	 */
	private static array $dbFiles = [];

	public static function tearDownAfterClass(): void
	{
		foreach (self::$projects as $key => $proj) {
			$proj->destroy();
			$file = self::$dbFiles[$key] ?? null;
			if (null !== $file && \is_file($file)) {
				\unlink($file);
			}
		}
		self::$projects = [];
		self::$dbFiles  = [];
		parent::tearDownAfterClass();
	}

	/**
	 * Creates the test project for the given DB type, runs `oz db build`,
	 * and asserts that a fresh project reports no pending migrations.
	 *
	 * @dataProvider provideDbConfig
	 */
	public function testFreshProjectNoPendingMigrations(DbTestConfig $config): void
	{
		$proj = self::initProject($config);

		$proc = $proj->oz('migrations', 'check');
		$proc->run();

		self::assertSame(0, $proc->getExitCode(), $proc->getErrorOutput());
		self::assertStringContainsStringIgnoringCase('no pending migrations', $proc->getOutput());
	}

	/**
	 * @dataProvider provideDbConfig
	 */
	public function testMigrationsCreateGeneratesFile(DbTestConfig $config): void
	{
		$proj = self::getProject($config);

		// --force writes a migration file even when no schema diff is detected.
		$proc = $proj->oz('migrations', 'create', '--force', '--label=initial');
		$proc->mustRun();

		$migrDir = $proj->getPath() . '/app/migrations';
		self::assertDirectoryExists($migrDir, 'app/migrations/ must be created by migrations create.');

		$files = \glob($migrDir . '/*.php') ?: [];
		self::assertCount(1, $files, 'Exactly one migration file must exist after initial create.');
	}

	/**
	 * @dataProvider provideDbConfig
	 */
	public function testAfterCreateHasPendingMigrations(DbTestConfig $config): void
	{
		$proj = self::getProject($config);
		$proc = $proj->oz('migrations', 'check');
		$proc->mustRun();

		// Output must NOT contain the "no pending" message -- there is one pending migration.
		self::assertStringNotContainsStringIgnoringCase('no pending migrations', $proc->getOutput());
	}

	/**
	 * @dataProvider provideDbConfig
	 */
	public function testMigrationsRunInstallsSchema(DbTestConfig $config): void
	{
		$proc = self::getProject($config)->oz('migrations', 'run', '--skip-backup');
		$proc->mustRun();

		self::assertSame(0, $proc->getExitCode(), $proc->getErrorOutput());
	}

	/**
	 * @dataProvider provideDbConfig
	 */
	public function testAfterRunNoPendingMigrations(DbTestConfig $config): void
	{
		$proc = self::getProject($config)->oz('migrations', 'check');
		$proc->mustRun();

		self::assertStringContainsStringIgnoringCase('no pending migrations', $proc->getOutput());
	}

	/**
	 * @dataProvider provideDbConfig
	 */
	public function testMigrationsCreateSecondMigration(DbTestConfig $config): void
	{
		$proj = self::getProject($config);
		$proc = $proj->oz('migrations', 'create', '--force', '--label=second');
		$proc->mustRun();

		$migrDir = $proj->getPath() . '/app/migrations';
		$files   = \glob($migrDir . '/*.php') ?: [];
		self::assertGreaterThanOrEqual(2, \count($files), 'Expected at least 2 migration files after second create.');

		foreach ($files as $file) {
			$lint = new Process([\PHP_BINARY, '-l', $file]);
			$lint->mustRun();
			self::assertStringContainsString('No syntax errors', $lint->getOutput(), "Syntax error in {$file}.");
		}
	}

	/**
	 * @dataProvider provideDbConfig
	 */
	public function testMigrationsRunAppliesNewMigration(DbTestConfig $config): void
	{
		// After the second create the state is PENDING.
		$proj  = self::getProject($config);
		$check = $proj->oz('migrations', 'check');
		$check->mustRun();
		self::assertStringNotContainsStringIgnoringCase('no pending migrations', $check->getOutput());

		$run = $proj->oz('migrations', 'run', '--skip-backup');
		$run->mustRun();
		self::assertSame(0, $run->getExitCode(), $run->getErrorOutput());
	}

	/**
	 * @dataProvider provideDbConfig
	 */
	public function testAfterSecondRunNoPendingMigrations(DbTestConfig $config): void
	{
		$proc = self::getProject($config)->oz('migrations', 'check');
		$proc->mustRun();

		self::assertStringContainsStringIgnoringCase('no pending migrations', $proc->getOutput());
	}

	/**
	 * @dataProvider provideDbConfig
	 */
	public function testMigrationsRollback(DbTestConfig $config): void
	{
		// Roll back to the first migration version.
		$proc = self::getProject($config)->oz('migrations', 'rollback', '--to-version=1');
		$proc->mustRun();

		// After rollback the DB version is behind the source code version -> PENDING.
		$check = self::getProject($config)->oz('migrations', 'check');
		$check->mustRun();
		self::assertStringNotContainsStringIgnoringCase('no pending migrations', $check->getOutput());
	}

	// -------------------------------------------------------------------------
	// Data provider
	// -------------------------------------------------------------------------

	/**
	 * Provides one entry per DB type that is currently configured.
	 * The 'migrations' tag ensures the SQLite file path is unique to this
	 * test class and does not collide with other DB test classes.
	 *
	 * @return array<string, array{DbTestConfig}>
	 */
	public static function provideDbConfig(): iterable
	{
		return DbTestConfig::allConfigured('migrations');
	}

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	/**
	 * Creates (or returns the already-created) project for the given RDBMS.
	 * The project is created once and reused across all test methods in this class.
	 * Runs `oz db build --build-all --class-only` before returning the project.
	 */
	private static function initProject(DbTestConfig $config): OZTestProject
	{
		$rdbms = $config->rdbms;

		if (isset(self::$projects[$rdbms])) {
			return self::$projects[$rdbms];
		}

		$proj = OZTestProject::create('migrations-' . $rdbms, shared: true, fresh: true);
		$proj->writeEnv($config->toEnvArray());
		// ORM classes must be generated before any migration operation.
		$proj->oz('db', 'build', '--build-all', '--class-only')->mustRun();
		// Drop all tables so FK constraint names don't collide between test runs.
		$proj->cleanDb();

		self::$projects[$rdbms] = $proj;
		self::$dbFiles[$rdbms]  = $config->isSQLite() ? $config->host : null;

		return $proj;
	}

	/**
	 * Returns the project for the given RDBMS.
	 * Fails the test if the project was not yet initialized (i.e. the first
	 * test in the chain did not run / failed before storing the project).
	 */
	private static function getProject(DbTestConfig $config): OZTestProject
	{
		$rdbms = $config->rdbms;

		if (!isset(self::$projects[$rdbms])) {
			self::fail(\sprintf(
				'Project for %s is not initialized. Did testFreshProjectNoPendingMigrations pass?',
				$rdbms,
			));
		}

		return self::$projects[$rdbms];
	}
}
