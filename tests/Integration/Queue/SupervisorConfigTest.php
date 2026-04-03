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

namespace OZONE\Tests\Integration\Queue;

use OZONE\Tests\Integration\Support\DbTestConfig;
use OZONE\Tests\Integration\Support\OZTestProject;
use PHPUnit\Framework\TestCase;

/**
 * Tests the `oz jobs supervisor` command generates a valid supervisord config.
 *
 * The supervisor command generates text output and does not depend on the
 * RDBMS type. However OZone bootstrap requires a working DB connection, so
 * we use SQLite (always available) and run the full migration setup once.
 * All sub-tests reuse the same project.
 *
 * @internal
 *
 * @coversNothing
 */
final class SupervisorConfigTest extends TestCase
{
	private static ?OZTestProject $project = null;
	private static ?string $dbFile         = null;

	public static function tearDownAfterClass(): void
	{
		if (null !== self::$project) {
			self::$project->destroy();
			self::$project = null;
		}

		if (null !== self::$dbFile && \is_file(self::$dbFile)) {
			\unlink(self::$dbFile);
			self::$dbFile = null;
		}

		parent::tearDownAfterClass();
	}

	/**
	 * Creates the project and runs migrations so OZone can bootstrap for the
	 * subsequent CLI tests.
	 */
	public function testSetup(): void
	{
		$config = DbTestConfig::sqlite(\sys_get_temp_dir() . '/oz_test_' . \getmypid() . '_supervisor.db');
		$proj   = OZTestProject::create('supervisor-config', shared: true, fresh: true);
		$proj->writeEnv($config->toEnvArray());

		self::$project = $proj;
		self::$dbFile  = $config->host;

		$proj->oz('db', 'build', '--build-all', '--class-only')->mustRun();
		$proj->cleanDb();
		$proj->oz('migrations', 'create', '--force', '--label=initial')->mustRun();
		$proj->oz('migrations', 'run', '--skip-backup')->mustRun();

		self::assertTrue(true, 'Project setup completed.');
	}

	/**
	 * `oz jobs supervisor` with default options generates a valid config block.
	 */
	public function testDefaultSupervisorBlock(): void
	{
		self::assertNotNull(self::$project, 'Project not initialized. Did testSetup pass?');

		$proc = self::$project->oz('jobs', 'supervisor');
		$proc->mustRun();

		$output = $proc->getOutput();
		self::assertStringContainsString('[program:ozone-worker-default]', $output);
		self::assertStringContainsString('autostart=true', $output);
		self::assertStringContainsString('autorestart=true', $output);
		self::assertStringContainsString('numprocs=1', $output);
		self::assertStringContainsString('command=', $output);
		self::assertStringContainsString('stdout_logfile=', $output);
	}

	/**
	 * --workers=3 sets numprocs=3.
	 */
	public function testWorkersOption(): void
	{
		self::assertNotNull(self::$project, 'Project not initialized. Did testSetup pass?');

		$proc = self::$project->oz('jobs', 'supervisor', '--workers=3');
		$proc->mustRun();

		self::assertStringContainsString('numprocs=3', $proc->getOutput());
	}

	/**
	 * --queue targets a different program name.
	 */
	public function testQueueOption(): void
	{
		self::assertNotNull(self::$project, 'Project not initialized. Did testSetup pass?');

		$proc = self::$project->oz('jobs', 'supervisor', '--queue=cron:sync');
		$proc->mustRun();

		self::assertStringContainsString('[program:ozone-worker-cron:sync]', $proc->getOutput());
	}

	/**
	 * --user=<name> is appended to the config block.
	 */
	public function testUserOption(): void
	{
		self::assertNotNull(self::$project, 'Project not initialized. Did testSetup pass?');

		$proc = self::$project->oz('jobs', 'supervisor', '--user=www-data');
		$proc->mustRun();

		self::assertStringContainsString('user=www-data', $proc->getOutput());
	}

	/**
	 * --output writes the config to a file instead of stdout.
	 */
	public function testOutputFileOption(): void
	{
		self::assertNotNull(self::$project, 'Project not initialized. Did testSetup pass?');

		$outputFile = self::$project->getPath() . '/supervisor.conf';

		$proc = self::$project->oz('jobs', 'supervisor', "--output={$outputFile}");
		$proc->mustRun();

		// stdout reports where the file was written.
		self::assertStringContainsString('Supervisor config written to', $proc->getOutput());

		// The file contains the full config block.
		self::assertFileExists($outputFile);
		$content = (string) \file_get_contents($outputFile);
		self::assertStringContainsString('[program:ozone-worker-default]', $content);
		self::assertStringContainsString('autostart=true', $content);
	}
}
