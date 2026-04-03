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

		$proj->writeFile('app/Workers/CancelTestWorker.php', self::workerSource($ns, $flagFile));
		$proj->writeFile('app/CancelTestBootHookReceiver.php', self::bootHookSource($ns, $flagFile, $refFile, $triggerFile));
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
	// PHP source templates
	// -------------------------------------------------------------------------

	private static function workerSource(string $namespace, string $flagFile): string
	{
		$flagFile = \addslashes($flagFile);

		return <<<PHP
<?php

declare(strict_types=1);

namespace {$namespace}\\Workers;

use OZONE\\Core\\Queue\\Interfaces\\JobContractInterface;
use OZONE\\Core\\Queue\\Interfaces\\WorkerInterface;
use OZONE\\Core\\Utils\\JSONResult;

/**
 * Minimal worker for CancelJobTest.
 *
 * Writes a flag file when it executes so the test can confirm the job ran
 * (or did not run when cancelled).
 */
final class CancelTestWorker implements WorkerInterface
{
	private JSONResult \$result;

	public function __construct(private readonly string \$flagFile) {}

	public static function getName(): string
	{
		return 'cancel-test-worker';
	}

	public function isAsync(): bool
	{
		return false;
	}

	public function work(JobContractInterface \$job): static
	{
		\$this->result = new JSONResult();
		\\file_put_contents(\$this->flagFile, 'ran');
		\$this->result->setDone()->setData(['ran' => true]);

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
		string $flagFile,
		string $refFile,
		string $triggerFile,
	): string {
		$flagFile    = \addslashes($flagFile);
		$refFile     = \addslashes($refFile);
		$triggerFile = \addslashes($triggerFile);

		return <<<PHP
<?php

declare(strict_types=1);

namespace {$namespace};

use OZONE\\Core\\Hooks\\Events\\InitHook;
use OZONE\\Core\\Hooks\\Interfaces\\BootHookReceiverInterface;
use OZONE\\Core\\Queue\\JobsManager;
use OZONE\\Core\\Queue\\Queue;
use {$namespace}\\Workers\\CancelTestWorker;

/**
 * Boot hook receiver for CancelJobTest.
 *
 * Registers the worker. When the trigger file is present, dispatches one job
 * and writes the job ref to a file so the test can pass it to `oz jobs cancel`.
 */
final class CancelTestBootHookReceiver implements BootHookReceiverInterface
{
	public static function boot(): void
	{
		JobsManager::registerWorker(CancelTestWorker::class);

		InitHook::listen(static function () {
			\$triggerFile = '{$triggerFile}';

			if (!\\is_file(\$triggerFile)) {
				return;
			}

			\\unlink(\$triggerFile);

			\$contract = Queue::get(Queue::DEFAULT)
				->push(new CancelTestWorker('{$flagFile}'))
				->dispatch();

			\\file_put_contents('{$refFile}', \$contract->getRef());
		});
	}
}
PHP;
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
