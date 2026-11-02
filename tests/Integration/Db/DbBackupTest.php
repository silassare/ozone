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

use Override;
use OZONE\Core\Testing\DbTestConfig;
use OZONE\Core\Testing\OZTestProject;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\ExecutableFinder;

/**
 * Integration tests for `oz db backup`.
 *
 * Tests cover:
 *  - All configured DB types can produce a backup file (via a data provider).
 *  - SQLite :memory: backup exits with an error.
 *
 * DB types are enabled via env vars (see DbTestConfig::allConfigured()).
 * SQLite is always tested. MySQL and PostgreSQL require the respective CLI
 * tools (mysqldump / pg_dump) to be available on the system.
 *
 * @internal
 */
#[CoversNothing]
final class DbBackupTest extends TestCase
{
	private string $backupDir;

	#[Override]
	protected function setUp(): void
	{
		parent::setUp();
		$this->backupDir = \sys_get_temp_dir() . '/_oz_backup_' . \uniqid('', true);
		\mkdir($this->backupDir, 0o775, true);
	}

	#[Override]
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
	 */
	#[DataProvider('provideBackupCreatesFileCases')]
	public function testBackupCreatesFile(DbTestConfig $config): void
	{
		$proj = OZTestProject::create('db-backup-' . $config->rdbms, shared: true, fresh: true);

		try {
			$config = $proj->useDatabase($config);

			// ORM classes must exist before migrations can run.
			$proj->oz('db', 'build', '--build-all', '--class-only')->mustRun();
			// Drop all tables so FK constraint names don't collide between test runs.
			$proj->cleanDb();
			// Create and apply the initial migration so the DB schema exists.
			$proj->oz('migrations', 'create', '--force', '--label=initial')->mustRun();
			$proj->oz('migrations', 'run', '--skip-backup')->mustRun();

			// Run the actual backup command.
			$proc = $proj->oz('db', 'backup', '--dir=' . $this->backupDir);
			$proc->mustRun();
		} finally {
			$proj->destroy();
		}

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
		return DbTestConfig::allConfigured('backup');
	}

	/**
	 * The password reaches mysqldump through an option file and never on its command line, which
	 * `ps` shows to every user on the host.
	 *
	 * Read from the real dump's own arguments: a recording wrapper, first on PATH, writes down what
	 * it was called with and then runs the real mysqldump, so what is asserted is the command that
	 * actually took the backup.
	 */
	public function testTheMySqlPasswordNeverReachesTheCommandLine(): void
	{
		$config = DbTestConfig::mysql();

		if (null === $config || '' === $config->pass) {
			self::markTestSkipped('No MySQL server with a password configured for the tests.');
		}

		$real = new ExecutableFinder()->find('mysqldump');

		if (null === $real) {
			self::markTestSkipped('mysqldump is not on this machine.');
		}

		// The wrapper lives in the project and not beside the backup: the temporary directory is
		// mounted without exec in the test container, where a wrapper is skipped with a permission
		// error and the real binary found one PATH entry later -- a test that silently asserts
		// nothing. What it records does go beside the backup, which outlives the project.
		$argv = $this->backupDir . '/mysqldump.argv';
		$cnf  = $this->backupDir . '/mysqldump.cnf';

		$proj = OZTestProject::create('db-backup-argv', shared: true, fresh: true);
		$bin  = $proj->getPath() . '/wrapper-bin';

		\mkdir($bin, 0o775, true);

		// Its arguments, and the option file one of them names: its mode and its content, read while
		// it still exists, because the dump deletes it when it is over.
		\file_put_contents($bin . '/mysqldump', \sprintf(
			"#!/bin/sh\n"
				. "printf '%%s\\n' \"$@\" > %s\n"
				. "for a in \"$@\"; do\n"
				. "  case \"\$a\" in --defaults-extra-file=*)\n"
				. "    f=\"\${a#--defaults-extra-file=}\"\n"
				. "    { stat -c %%a \"\$f\"; cat \"\$f\"; } > %s\n"
				. "  ;; esac\n"
				. "done\n"
				. "exec %s \"$@\"\n",
			\escapeshellarg($argv),
			\escapeshellarg($cnf),
			\escapeshellarg($real)
		));
		\chmod($bin . '/mysqldump', 0o755);

		self::assertTrue(\is_executable($bin . '/mysqldump'), 'The wrapper must be runnable.');

		try {
			$proj->useDatabase($config);
			$proj->oz('db', 'build', '--build-all', '--class-only')->mustRun();
			$proj->cleanDb();
			$proj->oz('migrations', 'create', '--force', '--label=initial')->mustRun();
			$proj->oz('migrations', 'run', '--skip-backup')->mustRun();

			$proc = $proj->oz('db', 'backup', '--dir=' . $this->backupDir);
			$proc->setEnv(['PATH' => $bin . ':' . \getenv('PATH')]);
			$proc->mustRun();
		} finally {
			$proj->destroy();
		}

		// The dump ran, and through the wrapper.
		self::assertCount(1, \glob($this->backupDir . '/*.sql') ?: []);
		self::assertFileExists($argv, 'The wrapper did not run: nothing was asserted about the real dump.');

		$arguments = \explode("\n", \trim((string) \file_get_contents($argv)));
		$line      = \implode(' ', $arguments);

		// No `-p` in any form: not the option with the password stuck to it, and not the bare one,
		// which would make mysqldump ask for a password instead of running. The password itself is
		// not asserted absent here -- the test server's happens to equal its user name -- it is
		// asserted present where it belongs, below.
		foreach ($arguments as $argument) {
			self::assertStringStartsNotWith('-p', $argument, $line);
			self::assertStringStartsNotWith('--password', $argument, $line);
		}

		// Where it did go: an option file, named first as mysqldump reads it, readable by nobody
		// else, and gone by the time the dump is over.
		self::assertStringStartsWith('--defaults-extra-file=', $arguments[0], $line);
		self::assertFileDoesNotExist(\explode('=', $arguments[0], 2)[1]);

		$option_file = \explode("\n", \trim((string) \file_get_contents($cnf)), 2);

		self::assertSame('600', $option_file[0]);
		self::assertStringContainsString('password="' . $config->pass . '"', $option_file[1] ?? '');
	}

	public function testSQLiteInMemoryBackupFails(): void
	{
		$proj = OZTestProject::create('db-backup-sqlite-mem', shared: true, fresh: true);

		try {
			$proj->writeEnv(DbTestConfig::sqlite(':memory:')->toEnvArray());

			$proc = $proj->oz('db', 'backup', '--dir=' . $this->backupDir);
			$proc->run();
		} finally {
			$proj->destroy();
		}

		self::assertNotSame(0, $proc->getExitCode(), 'Backup of :memory: SQLite must fail.');
		$files = \glob($this->backupDir . '/*') ?: [];
		self::assertCount(0, $files, 'No backup file should be created for :memory: SQLite.');
	}
}
