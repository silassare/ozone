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
 * Tests programmatic job cancellation via `oz jobs cancel`.
 *
 * Flow per DB type:
 *   1. testSetup -- creates project and schema, dispatches one PENDING job
 *      (without running it), cancels it via `oz jobs cancel`, drains the
 *      queue, and asserts the worker flag file was NOT created.
 *   2. testCancelAlreadyCancelledJobFails -- dispatches a second job, cancels
 *      it once (succeeds), then cancels it again and asserts the CLI reports
 *      that it "could not be cancelled".
 *
 * @internal
 *
 * @coversNothing
 */
final class CancelJobTest extends TestCase
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
	 * Creates the project, dispatches a PENDING job, cancels it via the CLI,
	 * drains the queue, and verifies the worker never ran.
	 *
	 * @dataProvider provideDbConfig
	 */
	public function testSetup(DbTestConfig $config): void
	{
		$rdbms = $config->rdbms;
		$proj  = OZTestProject::create('cancel-job-' . $rdbms, shared: true, fresh: true);
		$proj->writeEnv($config->toEnvArray());

		self::$projects[$rdbms] = $proj;
		self::$dbFiles[$rdbms]  = $config->isSQLite() ? $config->host : null;

		$proj->oz('db', 'build', '--build-all', '--class-only')->mustRun();
		$proj->cleanDb();
		$proj->oz('migrations', 'create', '--force', '--label=initial')->mustRun();
		$proj->oz('migrations', 'run', '--skip-backup')->mustRun();

		$ns          = $proj->getNamespace();
		$projPath    = $proj->getPath();
		$flagFile    = $projPath . '/worker_flag.txt';
		$refFile     = $projPath . '/job_ref.txt';
		$triggerFile = $projPath . '/dispatch.trigger';

		$proj->writeFileFromStub('CancelTestWorker', 'app/Workers/CancelTestWorker.php', [
			'namespace' => $ns,
		]);
		$proj->writeFileFromStub('CancelTestBootHookReceiver', 'app/CancelTestBootHookReceiver.php', [
			'namespace'    => $ns,
			'flag_file'    => $flagFile,
			'ref_file'     => $refFile,
			'trigger_file' => $triggerFile,
		]);
		$proj->setSetting('oz.boot', "{$ns}\\CancelTestBootHookReceiver", true);

		// Touch trigger so InitHook dispatches a job on the next oz invocation.
		\file_put_contents($triggerFile, '1');

		// Use a neutral command to fire InitHook+dispatch without processing jobs.
		$proj->oz('jobs', 'dead-letter', '--action=list')->mustRun();

		self::assertFileExists($refFile, 'Boot hook must have written the job ref.');
		$ref = \trim((string) \file_get_contents($refFile));
		self::assertNotEmpty($ref, 'Job ref must not be empty.');

		// Cancel the PENDING job via the CLI.
		$cancelProc = $proj->oz('jobs', 'cancel', '--store=db', "--ref={$ref}");
		$cancelProc->mustRun();
		self::assertStringContainsString(
			'cancelled',
			$cancelProc->getOutput(),
			'Cancel command should report success.'
		);

		// Drain the queue -- the cancelled job must not execute.
		$runProc = $proj->oz('jobs', 'run');
		$runProc->mustRun();

		self::assertFileDoesNotExist(
			$flagFile,
			"Cancelled job must not execute when queue is drained.\n"
				. 'Cancel output: ' . $cancelProc->getOutput() . "\n"
				. 'Run output: ' . $runProc->getOutput() . $runProc->getErrorOutput()
		);
	}

	/**
	 * Cancelling an already-CANCELLED job must report failure.
	 *
	 * @dataProvider provideDbConfig
	 */
	public function testCancelAlreadyCancelledJobFails(DbTestConfig $config): void
	{
		$proj        = self::getProject($config);
		$refFile     = $proj->getPath() . '/job_ref.txt';
		$triggerFile = $proj->getPath() . '/dispatch.trigger';

		// Dispatch a new PENDING job.
		\file_put_contents($triggerFile, '1');
		$proj->oz('jobs', 'dead-letter', '--action=list')->mustRun();

		$ref = \trim((string) \file_get_contents($refFile));
		self::assertNotEmpty($ref, 'Second job ref must not be empty.');

		// First cancel -- must succeed (job is PENDING).
		$proj->oz('jobs', 'cancel', '--store=db', "--ref={$ref}")->mustRun();

		// Second cancel -- job is now CANCELLED, must report failure.
		$secondCancel = $proj->oz('jobs', 'cancel', '--store=db', "--ref={$ref}");
		$secondCancel->mustRun();
		self::assertStringContainsString(
			'could not be cancelled',
			$secondCancel->getOutput(),
			'Cancelling an already-CANCELLED job must report failure.'
		);
	}

	public static function provideDbConfig(): iterable
	{
		return DbTestConfig::allConfigured('cancel-job');
	}

	// -------------------------------------------------------------------------
	// Static project store helpers
	// -------------------------------------------------------------------------

	private static function getProject(DbTestConfig $config): OZTestProject
	{
		$rdbms = $config->rdbms;

		if (!isset(self::$projects[$rdbms])) {
			self::fail(\sprintf('Project for %s not initialized. Did testSetup pass?', $rdbms));
		}

		return self::$projects[$rdbms];
	}
}
