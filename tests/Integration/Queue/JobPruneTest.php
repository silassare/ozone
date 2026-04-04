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
 * Verifies the `oz jobs prune` CLI action.
 *
 * Flow:
 *   1. testSetup -- dispatch and successfully run one job (now DONE).
 *   2. testPruneRemovesTerminalJobs -- `oz jobs prune --older-than=0` must
 *      report "Pruned 1 job(s)." (the DONE job is deleted).
 *   3. testPruneIsIdempotent -- a second prune with no matching jobs must
 *      report "Pruned 0 job(s).".
 *   4. testPruneRespectedStateFilter -- dispatch and run another DONE job,
 *      then prune with --state=failed (0 deleted) before pruning with
 *      --state=done (1 deleted).
 *
 * @internal
 *
 * @coversNothing
 */
final class JobPruneTest extends TestCase
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
	 * Creates the project, installs schema, dispatches a job and runs it to
	 * completion so that a DONE record exists in the store.
	 *
	 * @dataProvider provideDbConfig
	 */
	public function testSetup(DbTestConfig $config): void
	{
		$rdbms = $config->rdbms;
		$proj  = OZTestProject::create('job-prune-' . $rdbms, shared: true, fresh: true);
		$proj->writeEnv($config->toEnvArray());

		self::$projects[$rdbms] = $proj;
		self::$dbFiles[$rdbms]  = $config->isSQLite() ? $config->host : null;

		$proj->oz('db', 'build', '--build-all', '--class-only')->mustRun();
		$proj->cleanDb();
		$proj->oz('migrations', 'create', '--force', '--label=initial')->mustRun();
		$proj->oz('migrations', 'run', '--skip-backup')->mustRun();

		$ns          = $proj->getNamespace();
		$projPath    = $proj->getPath();
		$successFile = $projPath . '/success.flag';
		$triggerFile = $projPath . '/dispatch.trigger';

		$proj->writeFileFromStub('PruneTestWorker', 'app/Workers/PruneTestWorker.php', [
			'namespace' => $ns,
		]);
		$proj->writeFileFromStub('PruneTestBootHookReceiver', 'app/PruneTestBootHookReceiver.php', [
			'namespace'    => $ns,
			'success_file' => $successFile,
			'trigger_file' => $triggerFile,
		]);
		$proj->setSetting('oz.boot', "{$ns}\\PruneTestBootHookReceiver", true);

		// Dispatch and run to completion -> DONE record in DB.
		\file_put_contents($triggerFile, '1');

		$proc = $proj->oz('jobs', 'run');
		$proc->mustRun();

		self::assertFileExists(
			$successFile,
			"PruneTestWorker should have written the success flag.\n"
				. $proc->getOutput() . $proc->getErrorOutput()
		);
	}

	/**
	 * `oz jobs prune --older-than=0` must delete the single DONE job.
	 *
	 * @dataProvider provideDbConfig
	 */
	public function testPruneRemovesTerminalJobs(DbTestConfig $config): void
	{
		$proj = self::getProject($config);

		$proc = $proj->oz('jobs', 'prune', '--older-than=0');
		$proc->mustRun();

		self::assertStringContainsString(
			'Pruned 1 job(s).',
			$proc->getOutput(),
			'prune should report exactly 1 job deleted.'
		);
	}

	/**
	 * A second prune with no matching jobs must report "Pruned 0 job(s).".
	 *
	 * @dataProvider provideDbConfig
	 */
	public function testPruneIsIdempotent(DbTestConfig $config): void
	{
		$proj = self::getProject($config);

		$proc = $proj->oz('jobs', 'prune', '--older-than=0');
		$proc->mustRun();

		self::assertStringContainsString(
			'Pruned 0 job(s).',
			$proc->getOutput(),
			'A second prune should report 0 jobs deleted (already pruned).'
		);
	}

	/**
	 * Pruning with --state=failed must not touch a DONE job; pruning with
	 * --state=done must delete it.
	 *
	 * @dataProvider provideDbConfig
	 */
	public function testPruneRespectedStateFilter(DbTestConfig $config): void
	{
		$proj        = self::getProject($config);
		$successFile = $proj->getPath() . '/success.flag';
		$triggerFile = $proj->getPath() . '/dispatch.trigger';

		// Reset the success flag to confirm the worker runs again.
		if (\is_file($successFile)) {
			\unlink($successFile);
		}

		// Dispatch a new job -> DONE.
		\file_put_contents($triggerFile, '1');

		$runProc = $proj->oz('jobs', 'run');
		$runProc->mustRun();
		self::assertFileExists($successFile, 'Worker must run to create a fresh DONE job.');

		// Pruning with --state=failed should not delete the DONE job.
		$pruneFailedProc = $proj->oz('jobs', 'prune', '--older-than=0', '--state=failed');
		$pruneFailedProc->mustRun();

		self::assertStringContainsString(
			'Pruned 0 job(s).',
			$pruneFailedProc->getOutput(),
			'Pruning with --state=failed must not affect DONE jobs.'
		);

		// Pruning with --state=done should delete the DONE job.
		$pruneDoneProc = $proj->oz('jobs', 'prune', '--older-than=0', '--state=done');
		$pruneDoneProc->mustRun();

		self::assertStringContainsString(
			'Pruned 1 job(s).',
			$pruneDoneProc->getOutput(),
			'Pruning with --state=done must delete the DONE job.'
		);
	}

	public static function provideDbConfig(): iterable
	{
		return DbTestConfig::allConfigured('job-prune');
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
