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
 * Verifies that run_after enforcement prevents a failed job from being
 * re-queued before its retry delay has elapsed.
 *
 * Flow:
 *   1. A job is dispatched with retry_max=3 and retry_delay=3600 (1 hour).
 *   2. First `oz jobs run`: the job runs, throws an exception, and is reset
 *      to PENDING with run_after = time() + 3600.
 *   3. Second `oz jobs run` (immediately after): the iterator skips the job
 *      because run_after is still in the future.
 *   4. Assert the worker ran exactly once.
 *
 * @internal
 *
 * @coversNothing
 */
final class RetryDelayTest extends TestCase
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
	 * then dispatches and runs the job once. Asserts the worker ran exactly once.
	 *
	 * @dataProvider provideDbConfig
	 */
	public function testSetup(DbTestConfig $config): void
	{
		$rdbms = $config->rdbms;
		$proj  = OZTestProject::create('retry-delay-' . $rdbms, shared: true, fresh: true);
		$proj->writeEnv($config->toEnvArray());

		self::$projects[$rdbms] = $proj;
		self::$dbFiles[$rdbms]  = $config->isSQLite() ? $config->host : null;

		$proj->oz('db', 'build', '--build-all', '--class-only')->mustRun();
		$proj->cleanDb();
		$proj->oz('migrations', 'create', '--force', '--label=initial')->mustRun();
		$proj->oz('migrations', 'run', '--skip-backup')->mustRun();

		$ns          = $proj->getNamespace();
		$projPath    = $proj->getPath();
		$counterFile = $projPath . '/attempt_count.txt';
		$failFile    = $projPath . '/fail.flag';
		$triggerFile = $projPath . '/dispatch.trigger';

		$proj->writeFileFromStub('RetryTestWorker', 'app/Workers/RetryTestWorker.php', [
			'namespace' => $ns,
		]);
		$proj->writeFileFromStub('RetryTestBootHookReceiver', 'app/RetryTestBootHookReceiver.php', [
			'namespace'    => $ns,
			'counter_file' => $counterFile,
			'fail_file'    => $failFile,
			'trigger_file' => $triggerFile,
		]);
		$proj->setSetting('oz.boot', "{$ns}\\RetryTestBootHookReceiver", true);

		// Create the fail flag so the worker throws on every attempt.
		\file_put_contents($failFile, '1');
		// Create the dispatch trigger so the boot hook dispatches exactly one job.
		\file_put_contents($triggerFile, '1');

		$proc = $proj->oz('jobs', 'run');
		$proc->mustRun();

		self::assertFileExists(
			$counterFile,
			"RetryTestWorker should have incremented the counter file.\n"
				. $proc->getOutput() . $proc->getErrorOutput()
		);
		self::assertSame(
			'1',
			\trim((string) \file_get_contents($counterFile)),
			'Worker should have run exactly once on the first invocation.'
		);
	}

	/**
	 * A second `oz jobs run` must NOT pick up the failed job because its
	 * run_after timestamp is 3600 seconds in the future.
	 *
	 * @dataProvider provideDbConfig
	 */
	public function testRunAfterPreventsImmediateRequeue(DbTestConfig $config): void
	{
		$proj        = self::getProject($config);
		$counterFile = $proj->getPath() . '/attempt_count.txt';

		// No trigger file -> no new dispatch. The PENDING job has run_after = now+3600.
		$proc = $proj->oz('jobs', 'run');
		$proc->mustRun();

		self::assertSame(
			'1',
			\trim((string) \file_get_contents($counterFile)),
			'Counter must still be 1: the job must be skipped by the iterator because run_after is in the future.'
		);
	}

	public static function provideDbConfig(): iterable
	{
		return DbTestConfig::allConfigured('retry-delay');
	}

	// -------------------------------------------------------------------------
	// Static project store helpers
	// -------------------------------------------------------------------------

	private static function getProject(DbTestConfig $config): OZTestProject
	{
		$rdbms = $config->rdbms;

		if (!isset(self::$projects[$rdbms])) {
			self::fail(\sprintf('Project for %s not initialised. Did testSetup pass?', $rdbms));
		}

		return self::$projects[$rdbms];
	}
}
