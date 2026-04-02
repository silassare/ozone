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

/**
 * Integration tests for `oz db backup`.
 *
 * Tests cover:
 *  - All configured DB types can produce a backup file (via @dataProvider).
 *  - SQLite :memory: backup exits with an error.
 *
 * DB types are enabled via env vars (see DbTestConfig::allConfigured()).
 * SQLite is always tested. MySQL and PostgreSQL require the respective CLI
 * tools (mysqldump / pg_dump) to be available on the system.
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
		$this->backupDir = \sys_get_temp_dir() . '/_oz_backup_' . \uniqid('', true);
		\mkdir($this->backupDir, 0o775, true);
	}

	protected function tearDown(): void
	{
		foreach (\glob($this->backupDir . '/*') ?: [] as $f) {
			\unlink($f);
		}
		if (\is_dir($this->backupDir)) {
			\rmdir($this->backupDir);
		}
		parent::tearDown();
	}

	// -------------------------------------------------------------------------
	// Tests
	// -------------------------------------------------------------------------

	/**
	 * Verifies that `oz db backup` creates a backup file for every configured
	 * DB type.
	 *
	 * @dataProvider provideBackupCreatesFileCases
	 */
	public function testBackupCreatesFile(DbTestConfig $config): void
	{
		$proj = OZTestProject::create('db-backup-' . $config->rdbms, shared: false);
		$proj->writeEnv($config->toEnvArray());
		$proj->oz('migrations', 'run', '--skip-backup')->mustRun();

		$proc = $proj->oz('db', 'backup', '--dir=' . $this->backupDir);
		$proc->mustRun();

		$proj->destroy();

		$ext   = $config->isSQLite() ? '*.db' : '*.sql';
		$files = \glob($this->backupDir . '/' . $ext) ?: [];
		self::assertCount(1, $files, "Expected one {$ext} backup file for {$config->rdbms}.");
		self::assertFileExists($files[0]);
	}

	// -------------------------------------------------------------------------
	// Data providers
	// -------------------------------------------------------------------------

	/**
	 * Provides one entry per DB type that is currently configured.
	 * SQLite is always present. MySQL / PostgreSQL require env vars
	 * (see DbTestConfig::allConfigured()).
	 *
	 * @return array<string, array{DbTestConfig}>
	 */
	public static function provideBackupCreatesFileCases(): iterable
	{
		return DbTestConfig::allConfigured();
	}

	public function testSQLiteInMemoryBackupFails(): void
	{
		$proj = OZTestProject::create('db-backup-sqlite-mem', shared: false);
		$proj->writeEnv(DbTestConfig::sqlite(':memory:')->toEnvArray());

		$proc = $proj->oz('db', 'backup', '--dir=' . $this->backupDir);
		$proc->run();

		$proj->destroy();

		self::assertNotSame(0, $proc->getExitCode(), 'Backup of :memory: SQLite must fail.');
		$files = \glob($this->backupDir . '/*') ?: [];
		self::assertCount(0, $files, 'No backup file should be created for :memory: SQLite.');
	}
}
