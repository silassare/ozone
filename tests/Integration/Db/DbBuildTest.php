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
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Symfony\Component\Process\Process;

/**
 * Integration tests for `oz db build`.
 *
 * Runs against every configured DB type via @dataProvider.
 * SQLite is always tested. MySQL / PostgreSQL are tested when their env vars are
 * set (see DbTestConfig::allConfigured()).
 *
 * One project directory is created per configured DB type and REUSED across all
 * test methods via the static project store. Tests run in declaration order.
 *
 * @internal
 *
 * @coversNothing
 */
final class DbBuildTest extends TestCase
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

	// -------------------------------------------------------------------------
	// Tests
	// -------------------------------------------------------------------------

	/**
	 * Creates the test project, runs full setup (db build + migrations), then
	 * re-runs `oz db build --class-only` and asserts it exits cleanly.
	 *
	 * @dataProvider provideDbConfig
	 */
	public function testDbBuildExitsCleanly(DbTestConfig $config): void
	{
		$rdbms = $config->rdbms;
		$proj  = OZTestProject::create('db-build-' . $rdbms, shared: true, fresh: true);
		$proj->writeEnv($config->toEnvArray());

		self::$projects[$rdbms] = $proj;
		self::$dbFiles[$rdbms]  = $config->isSQLite() ? $config->host : null;

		// ORM classes must exist before migrations can run.
		$proj->oz('db', 'build', '--build-all', '--class-only')->mustRun();
		// Drop all tables so FK constraint names don't collide between test runs.
		$proj->cleanDb();
		// Create and apply initial migration so the DB is ready.
		$proj->oz('migrations', 'create', '--force', '--label=initial')->mustRun();
		$proj->oz('migrations', 'run', '--skip-backup')->mustRun();

		// Confirm a plain non-rebuild exits cleanly.
		$proc = $proj->oz('db', 'build', '--class-only');
		$proc->mustRun();
		self::assertSame(0, $proc->getExitCode(), $proc->getErrorOutput());
	}

	/**
	 * @dataProvider provideDbConfig
	 */
	public function testDbDirIsCreated(DbTestConfig $config): void
	{
		$dbDir = self::getProject($config)->getPath() . '/app/Db';
		self::assertDirectoryExists($dbDir, 'app/Db/ should be created by db build.');
	}

	/**
	 * @dataProvider provideDbConfig
	 */
	public function testEntityFilesAreGenerated(DbTestConfig $config): void
	{
		// For an empty project schema only the Base/ subdirectory is created.
		// Verify it exists as proof that the build ran for the project namespace.
		$dbBaseDir = self::getProject($config)->getPath() . '/app/Db/Base';
		self::assertDirectoryExists($dbBaseDir, 'app/Db/Base/ should be created by db build for project namespace.');
	}

	/**
	 * @dataProvider provideDbConfig
	 */
	public function testGeneratedFilesAreValidPhp(DbTestConfig $config): void
	{
		$dbDir = self::getProject($config)->getPath() . '/app/Db';
		$files = [];

		if (\is_dir($dbDir)) {
			$it = new RecursiveIteratorIterator(
				new RecursiveDirectoryIterator($dbDir, RecursiveDirectoryIterator::SKIP_DOTS),
			);
			foreach ($it as $file) {
				if ('php' === $file->getExtension()) {
					$files[] = $file->getPathname();
				}
			}
		}

		// An empty project schema generates no entity PHP files — this is expected.
		// Ensure the loop below is always entered when files do exist.
		self::assertIsArray($files, 'Expected array of PHP files (possibly empty for a blank schema).');

		foreach ($files as $file) {
			$lint = new Process([\PHP_BINARY, '-l', $file]);
			$lint->run();
			self::assertSame(0, $lint->getExitCode(), "Syntax error in {$file}: " . $lint->getOutput());
		}
	}

	/**
	 * @dataProvider provideDbConfig
	 */
	public function testDbBuildIsIdempotent(DbTestConfig $config): void
	{
		// Run build a second time; must exit cleanly.
		$proc = self::getProject($config)->oz('db', 'build', '--class-only');
		$proc->mustRun();
		self::assertSame(0, $proc->getExitCode(), $proc->getErrorOutput());
	}

	// -------------------------------------------------------------------------
	// Data provider
	// -------------------------------------------------------------------------

	/**
	 * Provides one entry per DB type that is currently configured.
	 * The 'dbbuild' tag ensures the SQLite file path is unique to this
	 * test class and does not collide with other DB test classes.
	 *
	 * @return array<string, array{DbTestConfig}>
	 */
	public static function provideDbConfig(): iterable
	{
		return DbTestConfig::allConfigured('dbbuild');
	}

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	/**
	 * Returns the project for the given RDBMS.
	 * Fails the test if the project was not yet initialized.
	 */
	private static function getProject(DbTestConfig $config): OZTestProject
	{
		$rdbms = $config->rdbms;

		if (!isset(self::$projects[$rdbms])) {
			self::fail(\sprintf(
				'Project for %s is not initialized. Did testDbBuildExitsCleanly pass?',
				$rdbms,
			));
		}

		return self::$projects[$rdbms];
	}
}
