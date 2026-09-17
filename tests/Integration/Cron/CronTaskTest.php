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

use OZONE\Core\Testing\DbTestConfig;
use OZONE\Core\Testing\OZTestProject;
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
 *   is invoked so the task registry is populated before `Cron::getTask()` is called: without it,
 *   the subprocess threw "Cron task not found".
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
	 * A task runs once per scheduled minute: the first `oz cron run` of a minute runs the everyMinute
	 * task again, a second one in the same minute does not -- as for two servers' schedulers.
	 *
	 * @dataProvider provideDbConfig
	 */
	public function testCronRunIsIdempotent(DbTestConfig $config): void
	{
		$proj     = self::getProject($config);
		$flagFile = $proj->getPath() . \DIRECTORY_SEPARATOR . 'cron_ran.flag';

		$minute = self::nextMinute();

		self::removeFile($flagFile);
		$proj->oz('cron', 'run')->mustRun();

		self::assertFileExists($flagFile, 'The first run of a minute should run the task.');

		self::removeFile($flagFile);
		$proj->oz('cron', 'run')->mustRun();

		self::assertSame($minute, \intdiv(\time(), 60), 'Both runs should have been in the same minute.');
		self::assertFileDoesNotExist($flagFile, 'A second run in the same minute should not run the task again.');
	}

	// =========================================================================
	// Async cron tests (CronTaskWorker in a subprocess, which has not collected the tasks)
	// =========================================================================

	/**
	 * Dispatches an async cron task (`inBackground()`) and verifies it completes.
	 *
	 * A background task routes to the `cron:async` queue.  `oz cron run` spawns
	 * a subprocess (`oz jobs run --force`) to execute it.  In that subprocess OZone
	 * bootstraps but `Cron::runDues()` is never called, so `Cron::$tasks` would be
	 * empty if `CronTaskWorker::__construct()` did not call `Cron::collect()` to
	 * populate the registry before `Cron::getTask()` is called.
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
	 * The same for a background task: run by the first `oz cron run` of a minute, not by a second one.
	 *
	 * @dataProvider provideDbConfig
	 */
	public function testAsyncCronRunIsIdempotent(DbTestConfig $config): void
	{
		$proj     = self::getAsyncProject($config);
		$flagFile = $proj->getPath() . \DIRECTORY_SEPARATOR . 'async_cron_ran.flag';

		$minute = self::nextMinute();

		self::removeFile($flagFile);
		$proj->oz('cron', 'run')->mustRun();

		self::assertTrue(self::waitForFile($flagFile, 15.0), 'The first run of a minute should run the task.');

		self::removeFile($flagFile);
		$proj->oz('cron', 'run')->mustRun();

		// the background subprocess is fire-and-forget: give it the time it took above
		self::assertFalse(self::waitForFile($flagFile, 3.0), 'A second run in the same minute should not run it.');
		self::assertSame($minute, \intdiv(\time(), 60), 'Both runs should have been in the same minute.');
	}

	public static function provideDbConfig(): iterable
	{
		return DbTestConfig::allConfigured('cron-task');
	}

	/**
	 * Waits for the start of a minute no run has dispatched yet, and returns it.
	 */
	private static function nextMinute(): int
	{
		$next = (\intdiv(\time(), 60) + 1) * 60;

		\time_sleep_until($next + 1);

		return \intdiv(\time(), 60);
	}

	private static function waitForFile(string $file, float $seconds): bool
	{
		$deadline = \microtime(true) + $seconds;

		while (!\is_file($file) && \microtime(true) < $deadline) {
			\usleep(100_000);
		}

		return \is_file($file);
	}

	private static function removeFile(string $file): void
	{
		if (\is_file($file)) {
			\unlink($file);
		}
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
