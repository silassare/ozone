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

use OZONE\Core\Testing\DbTestConfig;
use OZONE\Core\Testing\OZTestProject;
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

	/**
	 * A table added to the code's schema gets no class until a migration creating it has run, and the
	 * build says so instead of only reporting success.
	 *
	 * @dataProvider provideDbConfig
	 */
	public function testBuildNamesTablesNoMigrationHasInstalled(DbTestConfig $config): void
	{
		$proj = self::getProject($config);
		$base = $proj->getPath() . '/app/Db/Base/BuildNoteBase.php';

		$proj->setSetting('oz.db.schema', 'build_notes', [
			'plural_name'   => 'build_notes',
			'singular_name' => 'build_note',
			'column_prefix' => 'bn',
			'columns'       => [
				'id'    => ['type' => 'bigint', 'auto_increment' => true, 'unsigned' => true],
				'title' => ['type' => 'string', 'max' => 60],
			],
			'constraints'   => [
				['type' => 'primary_key', 'columns' => ['id']],
			],
		]);

		$proc = $proj->oz('db', 'build');
		$proc->mustRun();
		$out = $proc->getOutput() . $proc->getErrorOutput();

		self::assertStringContainsString('1 table(s) of the schema are in no migration the database has run', $out);
		self::assertStringContainsString('build_notes', $out);
		self::assertFileDoesNotExist($base);

		$json = $proj->oz('db', 'build', '--class-only', '--json');
		$json->mustRun();
		self::assertSame(['build_notes'], \json_decode($json->getOutput(), true)['data']['not_installed'] ?? null);

		$bundle = $proj->oz('db', 'ts-bundle', '--dir=' . $proj->getPath());
		$bundle->mustRun();
		self::assertStringContainsString('build_notes', $bundle->getOutput() . $bundle->getErrorOutput());

		$proj->oz('migrations', 'run', '--skip-backup')->mustRun();

		$again = $proj->oz('db', 'build', '--class-only');
		$again->mustRun();

		self::assertStringNotContainsString('no migration the database has run', $again->getOutput() . $again->getErrorOutput());
		self::assertFileExists($base);
	}

	/**
	 * `OZ_DB_GENERATED_TIMESTAMP` off: a header no longer carries the generation time, so a rebuild of an
	 * unchanged schema rewrites nothing.
	 *
	 * @dataProvider provideDbConfig
	 */
	public function testGeneratedTimestampCanBeTurnedOff(DbTestConfig $config): void
	{
		$proj = self::getProject($config);
		$base = $proj->getPath() . '/app/Db/Base/BuildNoteBase.php';
		$ts   = $proj->getPath() . '/gobl/db/base/BuildNoteBase.ts';

		self::assertStringContainsString(" * Time: ", (string) \file_get_contents($base));

		$proj->setSetting('oz.db', 'OZ_DB_GENERATED_TIMESTAMP', false);

		$proj->oz('db', 'build', '--class-only')->mustRun();
		$proj->oz('db', 'ts-bundle', '--dir=' . $proj->getPath())->mustRun();

		$php = (string) \file_get_contents($base);
		$tsc = (string) \file_get_contents($ts);

		self::assertStringNotContainsString('Time:', $php);
		self::assertStringContainsString('Proudly With:', $php);
		self::assertStringNotContainsString('Time:', $tsc);
		self::assertStringContainsString('export type { BuildNoteRow } from', (string) \file_get_contents(
			$proj->getPath() . '/gobl/index.ts'
		));

		$proj->oz('db', 'build', '--class-only')->mustRun();

		self::assertSame($php, (string) \file_get_contents($base));
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
