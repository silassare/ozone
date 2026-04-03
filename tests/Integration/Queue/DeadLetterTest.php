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
 * Verifies the dead-letter queue lifecycle: a job that exhausts its retry
 * budget transitions to DEAD_LETTER, can be listed via `oz jobs dead-letter`,
 * reset to PENDING via `--action=retry`, and then succeeds on the next run.
 *
 * Flow per DB type:
 *   1. testSetup -- dispatch (trigger) + fail (1st attempt, try_count=1 < retry_max=2)
 *      -> PENDING with retry_delay=0 (run_after = now).
 *   2. testSecondFailureMovesToDeadLetter -- second `oz jobs run` picks the job up
 *      (run_after <= now), fails again (try_count=2 >= retry_max=2) -> DEAD_LETTER.
 *      `oz jobs dead-letter --action=list` must show the job.
 *   3. testDeadLetterJobCanBeRetried -- delete fail flag, reset via
 *      `oz jobs dead-letter --action=retry`, run again -> job succeeds.
 *
 * @internal
 *
 * @coversNothing
 */
final class DeadLetterTest extends TestCase
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
	 * Creates the project, installs schema, dispatches and fails the job once.
	 *
	 * @dataProvider provideDbConfig
	 */
	public function testSetup(DbTestConfig $config): void
	{
		$rdbms = $config->rdbms;
		$proj  = OZTestProject::create('dead-letter-' . $rdbms, shared: true, fresh: true);
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
		$successFile = $projPath . '/success.flag';
		$triggerFile = $projPath . '/dispatch.trigger';

		$proj->writeFile('app/Workers/DeadLetterTestWorker.php', self::workerSource($ns, $counterFile, $failFile, $successFile));
		$proj->writeFile('app/DeadLetterBootHookReceiver.php', self::bootHookSource($ns, $counterFile, $failFile, $successFile, $triggerFile));
		$proj->setSetting('oz.boot', "{$ns}\\DeadLetterBootHookReceiver", true);

		// Worker will fail (fail flag present), retry_max=2, retry_delay=0.
		\file_put_contents($failFile, '1');
		\file_put_contents($triggerFile, '1');

		$proc = $proj->oz('jobs', 'run');
		$proc->mustRun();

		// First failure: try_count=1 < retry_max=2 -> PENDING (retry).
		self::assertSame(
			'1',
			\trim((string) \file_get_contents($counterFile)),
			'Worker should have run exactly once on first invocation.'
		);
	}

	/**
	 * A second run with retry_delay=0 picks up the job immediately and fails
	 * it again. try_count reaches retry_max -> job moves to DEAD_LETTER.
	 *
	 * @dataProvider provideDbConfig
	 */
	public function testSecondFailureMovesToDeadLetter(DbTestConfig $config): void
	{
		$proj        = self::getProject($config);
		$counterFile = $proj->getPath() . '/attempt_count.txt';

		// No trigger -> no new dispatch. PENDING job with run_after=now -> picked up.
		$proc = $proj->oz('jobs', 'run');
		$proc->mustRun();

		self::assertSame(
			'2',
			\trim((string) \file_get_contents($counterFile)),
			'Worker should have run a second time (retry_delay=0).'
		);

		// Dead-letter list must show the worker.
		$listProc = $proj->oz('jobs', 'dead-letter', '--action=list');
		$listProc->mustRun();
		$output = $listProc->getOutput();

		self::assertStringContainsString(
			'dead-letter-test-worker',
			$output,
			'Dead-letter list should include the exhausted job.'
		);
		self::assertStringContainsString(
			'tries=2/2',
			$output,
			'Dead-letter list should show try_count/retry_max as 2/2.'
		);
	}

	/**
	 * After removing the fail flag and resetting via `--action=retry`, the job
	 * should succeed on the next run.
	 *
	 * @dataProvider provideDbConfig
	 */
	public function testDeadLetterJobCanBeRetried(DbTestConfig $config): void
	{
		$proj        = self::getProject($config);
		$failFile    = $proj->getPath() . '/fail.flag';
		$successFile = $proj->getPath() . '/success.flag';

		// Allow the worker to succeed.
		if (\is_file($failFile)) {
			\unlink($failFile);
		}

		// Reset all dead-letter jobs to PENDING.
		$retryProc = $proj->oz('jobs', 'dead-letter', '--action=retry');
		$retryProc->mustRun();

		self::assertStringContainsString(
			'1 job(s) affected',
			$retryProc->getOutput(),
			'dead-letter --action=retry should report 1 job affected.'
		);

		// Run the retried job -> worker succeeds -> success.flag written.
		$runProc = $proj->oz('jobs', 'run');
		$runProc->mustRun();

		self::assertFileExists(
			$successFile,
			"Worker should have written the success flag after the dead-letter retry.\n"
				. $runProc->getOutput() . $runProc->getErrorOutput()
		);
	}

	public static function provideDbConfig(): iterable
	{
		return DbTestConfig::allConfigured('dead-letter');
	}

	// -------------------------------------------------------------------------
	// PHP source templates
	// -------------------------------------------------------------------------

	private static function workerSource(
		string $namespace,
		string $counterFile,
		string $failFile,
		string $successFile,
	): string {
		$counterFile = \addslashes($counterFile);
		$failFile    = \addslashes($failFile);
		$successFile = \addslashes($successFile);

		return <<<PHP
<?php

declare(strict_types=1);

namespace {$namespace}\\Workers;

use OZONE\\Core\\Queue\\Interfaces\\JobContractInterface;
use OZONE\\Core\\Queue\\Interfaces\\WorkerInterface;
use OZONE\\Core\\Utils\\JSONResult;

/**
 * Synchronous worker for DeadLetterTest.
 *
 * Increments counter each run. Throws when fail-flag exists; writes
 * success-flag on success.
 */
final class DeadLetterTestWorker implements WorkerInterface
{
	private JSONResult \$result;

	public function __construct(
		private readonly string \$counterFile,
		private readonly string \$failFile,
		private readonly string \$successFile,
	) {}

	public static function getName(): string
	{
		return 'dead-letter-test-worker';
	}

	public function isAsync(): bool
	{
		return false;
	}

	public function work(JobContractInterface \$job): static
	{
		\$this->result = new JSONResult();

		\$count = \\is_file(\$this->counterFile)
			? (int) \\trim((string) \\file_get_contents(\$this->counterFile))
			: 0;
		\\file_put_contents(\$this->counterFile, (string) (\$count + 1));

		if (\\is_file(\$this->failFile)) {
			throw new \\RuntimeException('Forced failure from DeadLetterTestWorker.');
		}

		\\file_put_contents(\$this->successFile, 'ok');
		\$this->result->setDone()->setData(['ok' => true]);

		return \$this;
	}

	public function getResult(): JSONResult
	{
		return \$this->result;
	}

	public static function fromPayload(array \$payload): static
	{
		return new self(
			\$payload['counter_file'],
			\$payload['fail_file'],
			\$payload['success_file'],
		);
	}

	public function getPayload(): array
	{
		return [
			'counter_file' => \$this->counterFile,
			'fail_file'    => \$this->failFile,
			'success_file' => \$this->successFile,
		];
	}
}
PHP;
	}

	private static function bootHookSource(
		string $namespace,
		string $counterFile,
		string $failFile,
		string $successFile,
		string $triggerFile,
	): string {
		$counterFile = \addslashes($counterFile);
		$failFile    = \addslashes($failFile);
		$successFile = \addslashes($successFile);
		$triggerFile = \addslashes($triggerFile);

		return <<<PHP
<?php

declare(strict_types=1);

namespace {$namespace};

use OZONE\\Core\\Hooks\\Events\\InitHook;
use OZONE\\Core\\Hooks\\Interfaces\\BootHookReceiverInterface;
use OZONE\\Core\\Queue\\JobsManager;
use OZONE\\Core\\Queue\\Queue;
use {$namespace}\\Workers\\DeadLetterTestWorker;

/**
 * Boot hook receiver for DeadLetterTest.
 *
 * Registers the worker and dispatches one job (retry_max=2, retry_delay=0)
 * when the trigger file is present.
 */
final class DeadLetterBootHookReceiver implements BootHookReceiverInterface
{
	public static function boot(): void
	{
		JobsManager::registerWorker(DeadLetterTestWorker::class);

		InitHook::listen(static function () {
			\$triggerFile = '{$triggerFile}';

			if (!\\is_file(\$triggerFile)) {
				return;
			}

			\\unlink(\$triggerFile);

			Queue::get(Queue::DEFAULT)
				->push(new DeadLetterTestWorker('{$counterFile}', '{$failFile}', '{$successFile}'))
				->setRetryMax(2)
				->setRetryDelay(0)
				->dispatch();
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
			self::fail(\sprintf('Project for %s not initialised. Did testSetup pass?', $rdbms));
		}

		return self::$projects[$rdbms];
	}
}
