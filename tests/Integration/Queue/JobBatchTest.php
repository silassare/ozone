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

		$proj->writeFile('app/Workers/BatchTestWorkerA.php', self::workerASource($ns, $flagA));
		$proj->writeFile('app/Workers/BatchTestWorkerB.php', self::workerBSource($ns, $flagB));
		$proj->writeFile('app/BatchTestBootHookReceiver.php', self::bootHookSource($ns, $flagA, $flagB, $finishedFlag, $triggerFile));
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

	// -------------------------------------------------------------------------
	// PHP source templates
	// -------------------------------------------------------------------------

	private static function workerASource(string $namespace, string $flagA): string
	{
		$flagA = \addslashes($flagA);

		return <<<PHP
<?php

declare(strict_types=1);

namespace {$namespace}\\Workers;

use OZONE\\Core\\Queue\\Interfaces\\JobContractInterface;
use OZONE\\Core\\Queue\\Interfaces\\WorkerInterface;
use OZONE\\Core\\Utils\\JSONResult;

/**
 * First batch worker. Writes a flag file and succeeds.
 */
final class BatchTestWorkerA implements WorkerInterface
{
	private JSONResult \$result;

	public function __construct(private readonly string \$flagFile) {}

	public static function getName(): string
	{
		return 'batch-test-worker-a';
	}

	public function isAsync(): bool
	{
		return false;
	}

	public function work(JobContractInterface \$job): static
	{
		\$this->result = new JSONResult();
		\\file_put_contents(\$this->flagFile, 'ok');
		\$this->result->setDone()->setData(['worker' => 'A']);

		return \$this;
	}

	public function getResult(): JSONResult
	{
		return \$this->result;
	}

	public static function fromPayload(array \$payload): static
	{
		return new self(\$payload['flag_file']);
	}

	public function getPayload(): array
	{
		return ['flag_file' => \$this->flagFile];
	}
}
PHP;
	}

	private static function workerBSource(string $namespace, string $flagB): string
	{
		$flagB = \addslashes($flagB);

		return <<<PHP
<?php

declare(strict_types=1);

namespace {$namespace}\\Workers;

use OZONE\\Core\\Queue\\Interfaces\\JobContractInterface;
use OZONE\\Core\\Queue\\Interfaces\\WorkerInterface;
use OZONE\\Core\\Utils\\JSONResult;

/**
 * Second batch worker. Writes a flag file and succeeds.
 */
final class BatchTestWorkerB implements WorkerInterface
{
	private JSONResult \$result;

	public function __construct(private readonly string \$flagFile) {}

	public static function getName(): string
	{
		return 'batch-test-worker-b';
	}

	public function isAsync(): bool
	{
		return false;
	}

	public function work(JobContractInterface \$job): static
	{
		\$this->result = new JSONResult();
		\\file_put_contents(\$this->flagFile, 'ok');
		\$this->result->setDone()->setData(['worker' => 'B']);

		return \$this;
	}

	public function getResult(): JSONResult
	{
		return \$this->result;
	}

	public static function fromPayload(array \$payload): static
	{
		return new self(\$payload['flag_file']);
	}

	public function getPayload(): array
	{
		return ['flag_file' => \$this->flagFile];
	}
}
PHP;
	}

	private static function bootHookSource(
		string $namespace,
		string $flagA,
		string $flagB,
		string $finishedFlag,
		string $triggerFile,
	): string {
		$flagA        = \addslashes($flagA);
		$flagB        = \addslashes($flagB);
		$finishedFlag = \addslashes($finishedFlag);
		$triggerFile  = \addslashes($triggerFile);

		return <<<PHP
<?php

declare(strict_types=1);

namespace {$namespace};

use OZONE\\Core\\Hooks\\Events\\InitHook;
use OZONE\\Core\\Hooks\\Interfaces\\BootHookReceiverInterface;
use OZONE\\Core\\Queue\\BatchManager;
use OZONE\\Core\\Queue\\Hooks\\BatchFinished;
use OZONE\\Core\\Queue\\JobsManager;
use OZONE\\Core\\Queue\\Queue;
use {$namespace}\\Workers\\BatchTestWorkerA;
use {$namespace}\\Workers\\BatchTestWorkerB;

/**
 * Boot hook receiver for JobBatchTest.
 *
 * Registers both batch workers, listens for BatchFinished to write a flag,
 * and dispatches a two-worker batch when the trigger file is present.
 */
final class BatchTestBootHookReceiver implements BootHookReceiverInterface
{
	public static function boot(): void
	{
		JobsManager::registerWorker(BatchTestWorkerA::class);
		JobsManager::registerWorker(BatchTestWorkerB::class);

		BatchFinished::listen(static function (BatchFinished \$event) {
			\$hasError = \$event->has_error ? '1' : '0';
			\\file_put_contents('{$finishedFlag}', 'batch-finished:' . \$hasError);
		});

		InitHook::listen(static function () {
			\$triggerFile = '{$triggerFile}';

			if (!\\is_file(\$triggerFile)) {
				return;
			}

			\\unlink(\$triggerFile);

			BatchManager::create(
				[
					new BatchTestWorkerA('{$flagA}'),
					new BatchTestWorkerB('{$flagB}'),
				],
				Queue::DEFAULT,
				'test-batch',
			);
		});
	}
}
PHP;
	}
}
