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

namespace OZONE\Tests\Integration\Cron;

use OZONE\Tests\Integration\Support\DbTestConfig;
use OZONE\Tests\Integration\Support\OZTestProject;
use PHPUnit\Framework\TestCase;

/**
 * End-to-end test for the cron + queue pipeline.
 *
 * A `TestCronBootHookReceiver` is injected into each test project. Its `boot()` method
 * registers a `CronCollect::listen()` handler that adds a callable task scheduled
 * `everyMinute()` (always due). The task writes a flag file when it runs.
 *
 * Sync flow (testCronTaskDispatchedAndProcessed):
 *   `oz cron run` dispatches due tasks into `cron:sync` and immediately processes them
 *   in-process.
 *
 * Async flow (testAsyncCronTaskDispatchedAndProcessed):
 *   A second project uses a task with `inBackground()`.  `oz cron run` dispatches it to
 *   `cron:async` and spawns a subprocess via `oz jobs run --force`.  The subprocess
 *   bootstraps OZone and calls `CronTaskWorker::__construct()`, where `Cron::collect()`
 *   is now invoked so the task registry is populated before `Cron::getTask()` is called.
 *   Without the BUG-1 fix, the subprocess always threw "Cron task not found".
 *
 * @internal
 *
 * @coversNothing
 */
final class CronTaskTest extends TestCase
{
	/** @var array<string, OZTestProject> */
	private static array $projects = [];

	/** @var array<string, null|string> */
	private static array $dbFiles = [];

	/** @var array<string, OZTestProject> projects used by the async cron tests */
	private static array $asyncProjects = [];

	/** @var array<string, null|string> */
	private static array $asyncDbFiles = [];

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
		foreach (self::$asyncProjects as $proj) {
			$proj->destroy();
		}
		foreach (self::$asyncDbFiles as $file) {
			if (null !== $file && \is_file($file)) {
				\unlink($file);
			}
		}
		self::$projects      = [];
		self::$dbFiles       = [];
		self::$asyncProjects = [];
		self::$asyncDbFiles  = [];
		parent::tearDownAfterClass();
	}

	/**
	 * Creates the test project, installs the schema, injects a cron boot hook receiver
	 * that registers an everyMinute task, then runs the full dispatch + process pipeline.
	 *
	 * @dataProvider provideDbConfig
	 */
	public function testCronTaskDispatchedAndProcessed(DbTestConfig $config): void
	{
		$rdbms = $config->rdbms;
		$proj  = OZTestProject::create('cron-task-' . $rdbms, shared: true, fresh: true);
		$proj->writeEnv($config->toEnvArray());

		self::$projects[$rdbms] = $proj;
		self::$dbFiles[$rdbms]  = $config->isSQLite() ? $config->host : null;

		$proj->oz('db', 'build', '--build-all', '--class-only')->mustRun();
		$proj->cleanDb();
		$proj->oz('migrations', 'create', '--force', '--label=initial')->mustRun();
		$proj->oz('migrations', 'run', '--skip-backup')->mustRun();

		$ns       = $proj->getNamespace();
		$flagFile = $proj->getPath() . \DIRECTORY_SEPARATOR . 'cron_ran.flag';

		$proj->writeFileFromStub('TestCronBootHookReceiver', 'app/TestCronBootHookReceiver.php', [
			'namespace' => $ns,
			'flag_file' => $flagFile,
		]);
		$proj->setSetting('oz.boot', "{$ns}\\TestCronBootHookReceiver", true);

		// oz cron run dispatches AND processes the due task (CronCmd runs cron:sync after dispatch).
		$cronProc = $proj->oz('cron', 'run');
		$cronProc->mustRun();

		self::assertFileExists(
			$flagFile,
			"The cron task callable should have written the flag file.\n"
				. 'cron run output:' . "\n" . $cronProc->getOutput() . $cronProc->getErrorOutput()
		);
		self::assertSame('cron-ok', \trim((string) \file_get_contents($flagFile)));
	}

	/**
	 * A second `oz cron run` cycle re-dispatches and re-executes the task
	 * (everyMinute is always due) and exits cleanly.
	 *
	 * @dataProvider provideDbConfig
	 */
	public function testCronRunIsIdempotent(DbTestConfig $config): void
	{
		$proj     = self::getProject($config);
		$flagFile = $proj->getPath() . \DIRECTORY_SEPARATOR . 'cron_ran.flag';

		// Remove the previous flag so we detect a fresh run.
		if (\is_file($flagFile)) {
			\unlink($flagFile);
		}

		$proc1 = $proj->oz('cron', 'run');
		$proc1->mustRun();

		self::assertSame(0, $proc1->getExitCode());
		self::assertFileExists($flagFile, 'Task should run again on a second dispatch cycle.');
	}

	// =========================================================================
	// Async cron tests (BUG-1 regression: CronTaskWorker subprocess context)
	// =========================================================================

	/**
	 * Dispatches an async cron task (`inBackground()`) and verifies it completes.
	 *
	 * A background task routes to the `cron:async` queue.  `oz cron run` spawns
	 * a subprocess (`oz jobs run --force`) to execute it.  In that subprocess OZone
	 * bootstraps but `Cron::runDues()` is never called, so `Cron::$tasks` would be
	 * empty without the BUG-1 fix.  `CronTaskWorker::__construct()` now calls
	 * `Cron::collect()` to populate the registry before `Cron::getTask()` is called.
	 *
	 * @dataProvider provideDbConfig
	 */
	public function testAsyncCronTaskDispatchedAndProcessed(DbTestConfig $config): void
	{
		$rdbms = $config->rdbms;
		$proj  = OZTestProject::create('cron-async-' . $rdbms, shared: true, fresh: true);
		$proj->writeEnv($config->toEnvArray());

		self::$asyncProjects[$rdbms] = $proj;
		self::$asyncDbFiles[$rdbms]  = $config->isSQLite() ? $config->host : null;

		$proj->oz('db', 'build', '--build-all', '--class-only')->mustRun();
		$proj->cleanDb();
		$proj->oz('migrations', 'create', '--force', '--label=initial')->mustRun();
		$proj->oz('migrations', 'run', '--skip-backup')->mustRun();

		$ns       = $proj->getNamespace();
		$flagFile = $proj->getPath() . \DIRECTORY_SEPARATOR . 'async_cron_ran.flag';

		$proj->writeFileFromStub('TestAsyncCronBootHookReceiver', 'app/TestAsyncCronBootHookReceiver.php', [
			'namespace' => $ns,
			'flag_file' => $flagFile,
		]);
		$proj->setSetting('oz.boot', "{$ns}\\TestAsyncCronBootHookReceiver", true);

		// oz cron run dispatches to cron:async and spawns a subprocess to execute it.
		$cronProc = $proj->oz('cron', 'run');
		$cronProc->mustRun();

		// The subprocess is fire-and-forget (Process::start() non-blocking).
		// Poll until the flag appears or we hit the deadline (15 s).
		$deadline = \microtime(true) + 15.0;
		while (!\is_file($flagFile) && \microtime(true) < $deadline) {
			\usleep(100_000); // 100 ms
		}

		self::assertFileExists(
			$flagFile,
			"The async cron task should have written the flag file via a background subprocess.\n"
				. 'cron run stdout: ' . $cronProc->getOutput()
				. "\ncron run stderr: " . $cronProc->getErrorOutput()
		);
		self::assertSame('async-cron-ok', \trim((string) \file_get_contents($flagFile)));
	}

	/**
	 * A second `oz cron run` cycle re-dispatches and re-executes the async task.
	 *
	 * @dataProvider provideDbConfig
	 */
	public function testAsyncCronRunIsIdempotent(DbTestConfig $config): void
	{
		$proj     = self::getAsyncProject($config);
		$flagFile = $proj->getPath() . \DIRECTORY_SEPARATOR . 'async_cron_ran.flag';

		if (\is_file($flagFile)) {
			\unlink($flagFile);
		}

		$proc = $proj->oz('cron', 'run');
		$proc->mustRun();

		// Poll for the flag file (background subprocess is fire-and-forget).
		$deadline = \microtime(true) + 15.0;
		while (!\is_file($flagFile) && \microtime(true) < $deadline) {
			\usleep(100_000); // 100 ms
		}

		self::assertSame(0, $proc->getExitCode());
		self::assertFileExists($flagFile, 'Async task should run again on a second dispatch cycle.');
	}

	public static function provideDbConfig(): iterable
	{
		return DbTestConfig::allConfigured('cron-task');
	}

	// -------------------------------------------------------------------------
	// Static project store helpers
	// -------------------------------------------------------------------------

	private static function getProject(DbTestConfig $config): OZTestProject
	{
		$rdbms = $config->rdbms;
		if (!isset(self::$projects[$rdbms])) {
			self::fail(\sprintf(
				'Project for %s not initialized. Did testCronTaskDispatchedAndProcessed pass?',
				$rdbms
			));
		}

		return self::$projects[$rdbms];
	}

	private static function getAsyncProject(DbTestConfig $config): OZTestProject
	{
		$rdbms = $config->rdbms;
		if (!isset(self::$asyncProjects[$rdbms])) {
			self::fail(\sprintf(
				'Async project for %s not initialized. Did testAsyncCronTaskDispatchedAndProcessed pass?',
				$rdbms
			));
		}

		return self::$asyncProjects[$rdbms];
	}
}
