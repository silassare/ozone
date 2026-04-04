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
 * Regression test for BUG-3: DbJobStore stale lock recovery.
 *
 * When a worker subprocess crashes after acquiring the lock but before
 * calling finish(), the job is left in state=RUNNING with locked=true.
 * Without the fix, that job stays stuck indefinitely.
 *
 * This test simulates a stale lock by:
 *   1. Dispatching a job normally.
 *   2. Immediately after dispatch (via an InitHook listener) setting the job to
 *      state=RUNNING, locked=true, updated_at=1 (Unix epoch -- guaranteed to be
 *      older than the 7200-second LOCK_TTL).
 *   3. Running `oz jobs run`, which now calls the stale-lock recovery pass inside
 *      DbJobStore::iterator() and resets the job back to PENDING.
 *   4. Asserting the worker ran and wrote its flag file.
 *
 * @internal
 *
 * @coversNothing
 */
final class StaleLockTest extends TestCase
{
	/** @var array<string, OZTestProject> */
	private static array $projects = [];

	/** @var array<string, null|string> */
	private static array $dbFiles = [];

	public static function tearDownAfterClass(): void
	{
		foreach (self::$projects as $proj) {
			$proj->destroy();
		}
		foreach (self::$dbFiles as $file) {
			if (null !== $file && \is_file($file)) {
				\unlink($file);
			}
		}
		self::$projects = [];
		self::$dbFiles  = [];
		parent::tearDownAfterClass();
	}

	/**
	 * Creates the project, installs the schema, injects the worker + boot hook,
	 * simulates a stale lock, then runs the queue.  Asserts the job was recovered
	 * and the worker ran successfully.
	 *
	 * @dataProvider provideStaleLockJobIsRecoveredAndProcessedCases
	 */
	public function testStaleLockJobIsRecoveredAndProcessed(DbTestConfig $config): void
	{
		$rdbms = $config->rdbms;
		$proj  = OZTestProject::create('stale-lock-' . $rdbms, shared: false, fresh: true);
		$proj->writeEnv($config->toEnvArray());

		self::$projects[$rdbms] = $proj;
		self::$dbFiles[$rdbms]  = $config->isSQLite() ? $config->host : null;

		$proj->oz('db', 'build', '--build-all', '--class-only')->mustRun();
		$proj->cleanDb();
		$proj->oz('migrations', 'create', '--force', '--label=initial')->mustRun();
		$proj->oz('migrations', 'run', '--skip-backup')->mustRun();

		$ns       = $proj->getNamespace();
		$flagFile = $proj->getPath() . \DIRECTORY_SEPARATOR . 'stale_lock.flag';

		$proj->writeFileFromStub('StaleLockTestWorker', 'app/Workers/StaleLockTestWorker.php', [
			'namespace' => $ns,
		]);
		$proj->writeFileFromStub('StaleLockBootHookReceiver', 'app/StaleLockBootHookReceiver.php', [
			'namespace' => $ns,
			'flag_file' => $flagFile,
		]);
		$proj->setSetting('oz.boot', "{$ns}\\StaleLockBootHookReceiver", true);

		// The boot hook receiver dispatches a job then immediately simulates a stale lock.
		// oz jobs run triggers the recovery pass and processes the job.
		$proc = $proj->oz('jobs', 'run');
		$proc->mustRun();

		self::assertFileExists(
			$flagFile,
			"StaleLockTestWorker should have written the flag file after stale-lock recovery.\n"
				. 'jobs run stdout: ' . $proc->getOutput()
				. "\njobs run stderr: " . $proc->getErrorOutput()
		);
		self::assertSame('stale-recovered', \trim((string) \file_get_contents($flagFile)));
	}

	public static function provideStaleLockJobIsRecoveredAndProcessedCases(): iterable
	{
		return DbTestConfig::allConfigured('stale-lock');
	}

	// -------------------------------------------------------------------------
	// Static project store helpers
	// -------------------------------------------------------------------------

	private static function getProject(DbTestConfig $config): OZTestProject
	{
		$rdbms = $config->rdbms;
		if (!isset(self::$projects[$rdbms])) {
			self::fail(\sprintf(
				'Project for %s not initialized. Did testStaleLockJobIsRecoveredAndProcessed pass?',
				$rdbms
			));
		}

		return self::$projects[$rdbms];
	}
}
