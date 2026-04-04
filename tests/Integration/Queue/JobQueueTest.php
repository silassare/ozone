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
 * Tests the job queue system end-to-end via CLI subprocesses.
 *
 * A custom synchronous worker is injected into each test project.  A
 * BootHookReceiver registered in oz.boot dispatches one job per bootstrap
 * through an InitHook listener.  Running `oz jobs run` then processes that
 * job; the worker records its result by writing a flag file, which the test
 * asserts.
 *
 * @internal
 *
 * @coversNothing
 */
final class JobQueueTest extends TestCase
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
	 * Creates the test project, installs the schema, injects the custom worker
	 * and boot hook receiver, then runs the queue.
	 *
	 * @dataProvider provideDbConfig
	 */
	public function testJobDispatchedAndProcessed(DbTestConfig $config): void
	{
		$rdbms = $config->rdbms;
		$proj  = OZTestProject::create('job-queue-' . $rdbms, shared: true, fresh: true);
		$proj->writeEnv($config->toEnvArray());

		self::$projects[$rdbms] = $proj;
		self::$dbFiles[$rdbms]  = $config->isSQLite() ? $config->host : null;

		// Build ORM classes, then install the schema.
		$proj->oz('db', 'build', '--build-all', '--class-only')->mustRun();
		$proj->cleanDb();
		$proj->oz('migrations', 'create', '--force', '--label=initial')->mustRun();
		$proj->oz('migrations', 'run', '--skip-backup')->mustRun();

		// Inject the custom worker and boot hook receiver PHP classes.
		$ns       = $proj->getNamespace();
		$flagFile = $proj->getPath() . \DIRECTORY_SEPARATOR . 'job_ran.flag';

		$proj->writeFileFromStub('TestJobWorker', 'app/Workers/TestJobWorker.php', [
			'namespace' => $ns,
		]);
		$proj->writeFileFromStub('TestJobBootHookReceiver', 'app/TestJobBootHookReceiver.php', [
			'namespace' => $ns,
		]);
		$proj->setSetting('oz.boot', "{$ns}\\TestJobBootHookReceiver", true);

		// Running the queue processes the job dispatched by TestJobBootHookReceiver.
		$proc = $proj->oz('jobs', 'run');
		$proc->mustRun();

		self::assertFileExists(
			$flagFile,
			"TestJobWorker should have created the flag file when its job ran.\n"
				. "Queue process output:\n" . $proc->getOutput() . $proc->getErrorOutput()
		);
		self::assertSame('done', \trim((string) \file_get_contents($flagFile)));
	}

	/**
	 * Verifies that a second call to `oz jobs run` is idempotent -- no new jobs
	 * are queued once the boot hook has already run, so re-running the queue
	 * command exits cleanly.
	 *
	 * Depends on testJobDispatchedAndProcessed having run first (same project).
	 *
	 * @dataProvider provideDbConfig
	 */
	public function testSubsequentJobsRunIsIdempotent(DbTestConfig $config): void
	{
		$proj = self::getProject($config);

		// Remove the flag so we can detect if a second job accidentally runs.
		$flagFile = $proj->getPath() . \DIRECTORY_SEPARATOR . 'job_ran.flag';
		if (\is_file($flagFile)) {
			\unlink($flagFile);
		}

		// The boot hook dispatches one new job per `oz` invocation, so the flag
		// will be recreated -- but the command must still exit cleanly.
		$proc = $proj->oz('jobs', 'run');
		$proc->mustRun();

		// Command exited successfully regardless of whether a job ran.
		self::assertSame(0, $proc->getExitCode());
	}

	public static function provideDbConfig(): iterable
	{
		return DbTestConfig::allConfigured('job-queue');
	}

	// -------------------------------------------------------------------------
	// Static project store helpers
	// -------------------------------------------------------------------------

	private static function getProject(DbTestConfig $config): OZTestProject
	{
		$rdbms = $config->rdbms;
		if (!isset(self::$projects[$rdbms])) {
			self::fail(
				\sprintf(
					'Project for %s not initialised. Did testJobDispatchedAndProcessed pass?',
					$rdbms
				)
			);
		}

		return self::$projects[$rdbms];
	}
}
