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
 * End-to-end test for the job batch system.
 *
 * Two workers (BatchTestWorkerA and BatchTestWorkerB) are dispatched together
 * as a batch via BatchManager::create(). A BatchFinished listener writes a
 * flag file when the batch completes. After a single `oz jobs run` invocation:
 *   - Both worker flag files must exist (all jobs ran).
 *   - The batch-finished flag file must exist (BatchFinished event fired).
 *   - The batch-finished flag must indicate no error (has_error = false).
 *
 * @internal
 *
 * @coversNothing
 */
final class JobBatchTest extends TestCase
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
	 * Creates the project, installs schema, dispatches a two-worker batch.
	 * A single `oz jobs run` must process both jobs and fire BatchFinished.
	 *
	 * @dataProvider provideBatchJobsRunAndBatchFinishedEventFiresCases
	 */
	public function testBatchJobsRunAndBatchFinishedEventFires(DbTestConfig $config): void
	{
		$rdbms = $config->rdbms;
		$proj  = OZTestProject::create('job-batch-' . $rdbms, shared: true, fresh: true);
		$proj->writeEnv($config->toEnvArray());

		self::$projects[$rdbms] = $proj;
		self::$dbFiles[$rdbms]  = $config->isSQLite() ? $config->host : null;

		$proj->oz('db', 'build', '--build-all', '--class-only')->mustRun();
		$proj->cleanDb();
		$proj->oz('migrations', 'create', '--force', '--label=initial')->mustRun();
		$proj->oz('migrations', 'run', '--skip-backup')->mustRun();

		$ns           = $proj->getNamespace();
		$projPath     = $proj->getPath();
		$flagA        = $projPath . '/batch_worker_a.flag';
		$flagB        = $projPath . '/batch_worker_b.flag';
		$finishedFlag = $projPath . '/batch_finished.flag';
		$triggerFile  = $projPath . '/dispatch.trigger';

		$proj->writeFileFromStub('BatchTestWorkerA', 'app/Workers/BatchTestWorkerA.php', [
			'namespace' => $ns,
		]);
		$proj->writeFileFromStub('BatchTestWorkerB', 'app/Workers/BatchTestWorkerB.php', [
			'namespace' => $ns,
		]);
		$proj->writeFileFromStub('BatchTestBootHookReceiver', 'app/BatchTestBootHookReceiver.php', [
			'namespace'     => $ns,
			'flag_a'        => $flagA,
			'flag_b'        => $flagB,
			'finished_flag' => $finishedFlag,
			'trigger_file'  => $triggerFile,
		]);
		$proj->setSetting('oz.boot', "{$ns}\\BatchTestBootHookReceiver", true);

		\file_put_contents($triggerFile, '1');

		$proc = $proj->oz('jobs', 'run');
		$proc->mustRun();

		self::assertFileExists(
			$flagA,
			"BatchTestWorkerA should have written its flag file.\n"
				. $proc->getOutput() . $proc->getErrorOutput()
		);
		self::assertFileExists(
			$flagB,
			"BatchTestWorkerB should have written its flag file.\n"
				. $proc->getOutput() . $proc->getErrorOutput()
		);
		self::assertFileExists(
			$finishedFlag,
			"BatchFinished listener should have written the finished flag.\n"
				. $proc->getOutput() . $proc->getErrorOutput()
		);
		self::assertSame(
			'batch-finished:0',
			\trim((string) \file_get_contents($finishedFlag)),
			'Batch must complete without errors (has_error = false).'
		);
	}

	public static function provideBatchJobsRunAndBatchFinishedEventFiresCases(): iterable
	{
		return DbTestConfig::allConfigured('job-batch');
	}
}
