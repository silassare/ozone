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
 * Verifies that job chaining dispatches the next job in the chain after the
 * current job completes successfully.
 *
 * Two distinct workers are used:
 *   - ChainTestWorkerA: writes a flag file then succeeds.
 *   - ChainTestWorkerB: writes a different flag file then succeeds.
 *
 * WorkerA is dispatched with a chain containing WorkerB. When WorkerA
 * finishes (DONE), JobsManager::finish() shifts WorkerB off the chain and
 * enqueues it. A subsequent `oz jobs run` processes WorkerB.
 *
 * @internal
 *
 * @coversNothing
 */
final class JobChainTest extends TestCase
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
	 * Creates the project, installs schema, dispatches WorkerA with WorkerB
	 * in its chain. After the first run WorkerA's flag must exist.
	 *
	 * @dataProvider provideDbConfig
	 */
	public function testFirstJobInChainRuns(DbTestConfig $config): void
	{
		$rdbms = $config->rdbms;
		$proj  = OZTestProject::create('job-chain-' . $rdbms, shared: true, fresh: true);
		$proj->writeEnv($config->toEnvArray());

		self::$projects[$rdbms] = $proj;
		self::$dbFiles[$rdbms]  = $config->isSQLite() ? $config->host : null;

		$proj->oz('db', 'build', '--build-all', '--class-only')->mustRun();
		$proj->cleanDb();
		$proj->oz('migrations', 'create', '--force', '--label=initial')->mustRun();
		$proj->oz('migrations', 'run', '--skip-backup')->mustRun();

		$ns      = $proj->getNamespace();
		$flagA   = $proj->getPath() . '/worker_a.flag';
		$flagB   = $proj->getPath() . '/worker_b.flag';
		$trigger = $proj->getPath() . '/dispatch.trigger';

		$proj->writeFileFromStub('ChainTestWorkerA', 'app/Workers/ChainTestWorkerA.php', [
			'namespace' => $ns,
		]);
		$proj->writeFileFromStub('ChainTestWorkerB', 'app/Workers/ChainTestWorkerB.php', [
			'namespace' => $ns,
		]);
		$proj->writeFileFromStub('ChainTestBootHookReceiver', 'app/ChainTestBootHookReceiver.php', [
			'namespace'    => $ns,
			'flag_a'       => $flagA,
			'flag_b'       => $flagB,
			'trigger_file' => $trigger,
		]);
		$proj->setSetting('oz.boot', "{$ns}\\ChainTestBootHookReceiver", true);

		\file_put_contents($trigger, '1');

		$proc = $proj->oz('jobs', 'run');
		$proc->mustRun();

		self::assertFileExists(
			$flagA,
			"ChainTestWorkerA should have written its flag file.\n"
				. $proc->getOutput() . $proc->getErrorOutput()
		);
	}

	/**
	 * A second `oz jobs run` must process the chained WorkerB that was
	 * enqueued when WorkerA completed.
	 *
	 * @dataProvider provideDbConfig
	 */
	public function testChainedJobDispatches(DbTestConfig $config): void
	{
		$proj  = self::getProject($config);
		$flagB = $proj->getPath() . '/worker_b.flag';

		// No trigger -> no new dispatch. WorkerB was queued by WorkerA's finish().
		$proc = $proj->oz('jobs', 'run');
		$proc->mustRun();

		self::assertFileExists(
			$flagB,
			"ChainTestWorkerB should have written its flag file after the chain was dispatched.\n"
				. $proc->getOutput() . $proc->getErrorOutput()
		);
	}

	public static function provideDbConfig(): iterable
	{
		return DbTestConfig::allConfigured('job-chain');
	}

	// -------------------------------------------------------------------------
	// Static project store helpers
	// -------------------------------------------------------------------------

	private static function getProject(DbTestConfig $config): OZTestProject
	{
		$rdbms = $config->rdbms;

		if (!isset(self::$projects[$rdbms])) {
			self::fail(\sprintf('Project for %s not initialised. Did testFirstJobInChainRuns pass?', $rdbms));
		}

		return self::$projects[$rdbms];
	}
}
