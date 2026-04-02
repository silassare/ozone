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
 * Integration tests for `oz db backup`.
 *
 * Tests cover:
 *  - SQLite file backup creates a .db copy in the target directory
 *  - SQLite :memory: backup exits with an error
 *  - MySQL backup creates a .sql dump (skipped when MySQL is not configured
 *    or mysqldump is not available)
 *  - PostgreSQL backup creates a .sql dump (skipped when PostgreSQL is not
 *    configured or pg_dump is not available)
 *
 * @internal
 *
 * @coversNothing
 */
final class DbBackupTest extends TestCase
{
	private string $backupDir;

	protected function setUp(): void
	{
		parent::setUp();
		// Each test gets its own temp dir so backup files do not collide.
		$this->backupDir = \sys_get_temp_dir() . '/_oz_tests_backup_' . \uniqid('', true);
		\mkdir($this->backupDir, 0o775, true);
	}

	protected function tearDown(): void
	{
		$files = \glob($this->backupDir . '/*') ?: [];
		foreach ($files as $f) {
			\unlink($f);
		}
		if (\is_dir($this->backupDir)) {
			\rmdir($this->backupDir);
		}
		parent::tearDown();
	}

	public function testSQLiteBackupCreatesDbFile(): void
	{
		$proj    = OZTestProject::create('db-backup-sqlite', shared: false);
		$db_file = $proj->getPath() . '/test.db';

		$proj->writeEnv([
			'OZ_DB_RDBMS' => 'sqlite',
			'OZ_DB_HOST'  => $db_file,
		]);

		$proj->oz('migrations', 'run', '--skip-backup')->mustRun();

		$proj->oz('db', 'backup', '--dir=' . $this->backupDir)->mustRun();

		$proj->destroy();

		$files = \glob($this->backupDir . '/*.db') ?: [];
		self::assertCount(1, $files, 'Expected exactly one .db backup file.');
		self::assertFileExists($files[0]);
	}

	public function testSQLiteInMemoryBackupFails(): void
	{
		$proj = OZTestProject::create('db-backup-sqlite-mem', shared: false);
		$proj->writeEnv([
			'OZ_DB_RDBMS' => 'sqlite',
			'OZ_DB_HOST'  => ':memory:',
		]);

		$proc = $proj->oz('db', 'backup', '--dir=' . $this->backupDir);
		$proc->run();

		$proj->destroy();

		self::assertNotSame(0, $proc->getExitCode(), 'Backup of :memory: SQLite must fail.');
		$files = \glob($this->backupDir . '/*') ?: [];
		self::assertCount(0, $files, 'No backup file should be created for :memory: SQLite.');
	}

	public function testMySQLBackupCreatesSqlFile(): void
	{
		$config = new DbTestConfig();
		if (!$config->isMySQL()) {
			self::markTestSkipped('MySQL not configured (set OZ_TEST_DB_RDBMS=mysql).');
		}
		if (!self::commandAvailable('mysqldump')) {
			self::markTestSkipped('mysqldump not found on this system.');
		}

		$proj = OZTestProject::create('db-backup-mysql', shared: false);
		$proj->writeEnv($config->toEnvArray());
		$proj->oz('migrations', 'run', '--skip-backup')->mustRun();

		$proj->oz('db', 'backup', '--dir=' . $this->backupDir)->mustRun();

		$proj->destroy();

		$files = \glob($this->backupDir . '/*.sql') ?: [];
		self::assertCount(1, $files, 'Expected exactly one .sql backup file.');
		self::assertFileExists($files[0]);
	}

	public function testPostgreSQLBackupCreatesSqlFile(): void
	{
		$config = new DbTestConfig();
		if (!$config->isPostgreSQL()) {
			self::markTestSkipped('PostgreSQL not configured (set OZ_TEST_DB_RDBMS=postgresql).');
		}
		if (!self::commandAvailable('pg_dump')) {
			self::markTestSkipped('pg_dump not found on this system.');
		}

		$proj = OZTestProject::create('db-backup-postgresql', shared: false);
		$proj->writeEnv($config->toEnvArray());
		$proj->oz('migrations', 'run', '--skip-backup')->mustRun();

		$proj->oz('db', 'backup', '--dir=' . $this->backupDir)->mustRun();

		$proj->destroy();

		$files = \glob($this->backupDir . '/*.sql') ?: [];
		self::assertCount(1, $files, 'Expected exactly one .sql backup file.');
		self::assertFileExists($files[0]);
	}

	private static function commandAvailable(string $cmd): bool
	{
		$proc = new Process(['which', $cmd]);
		$proc->run();

		return $proc->isSuccessful();
	}
}
