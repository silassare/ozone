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
 * Verifies that run_after enforcement prevents a failed job from being
 * re-queued before its retry delay has elapsed.
 *
 * Flow:
 *   1. A job is dispatched with retry_max=3 and retry_delay=3600 (1 hour).
 *   2. First `oz jobs run`: the job runs, throws an exception, and is reset
 *      to PENDING with run_after = time() + 3600.
 *   3. Second `oz jobs run` (immediately after): the iterator skips the job
 *      because run_after is still in the future.
 *   4. Assert the worker ran exactly once.
 *
 * @internal
 *
 * @coversNothing
 */
final class RetryDelayTest extends TestCase
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
	 * Creates the project, installs the schema, injects the worker + boot hook,
	 * then dispatches and runs the job once. Asserts the worker ran exactly once.
	 *
	 * @dataProvider provideDbConfig
	 */
	public function testSetup(DbTestConfig $config): void
	{
		$rdbms = $config->rdbms;
		$proj  = OZTestProject::create('retry-delay-' . $rdbms, shared: true, fresh: true);
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
		$triggerFile = $projPath . '/dispatch.trigger';

		$proj->writeFile('app/Workers/RetryTestWorker.php', self::workerSource($ns, $counterFile, $failFile));
		$proj->writeFile('app/RetryTestBootHookReceiver.php', self::bootHookSource($ns, $counterFile, $failFile, $triggerFile));
		$proj->setSetting('oz.boot', "{$ns}\\RetryTestBootHookReceiver", true);

		// Create the fail flag so the worker throws on every attempt.
		\file_put_contents($failFile, '1');
		// Create the dispatch trigger so the boot hook dispatches exactly one job.
		\file_put_contents($triggerFile, '1');

		$proc = $proj->oz('jobs', 'run');
		$proc->mustRun();

		self::assertFileExists(
			$counterFile,
			"RetryTestWorker should have incremented the counter file.\n"
				. $proc->getOutput() . $proc->getErrorOutput()
		);
		self::assertSame(
			'1',
			\trim((string) \file_get_contents($counterFile)),
			'Worker should have run exactly once on the first invocation.'
		);
	}

	/**
	 * A second `oz jobs run` must NOT pick up the failed job because its
	 * run_after timestamp is 3600 seconds in the future.
	 *
	 * @dataProvider provideDbConfig
	 */
	public function testRunAfterPreventsImmediateRequeue(DbTestConfig $config): void
	{
		$proj        = self::getProject($config);
		$counterFile = $proj->getPath() . '/attempt_count.txt';

		// No trigger file -> no new dispatch. The PENDING job has run_after = now+3600.
		$proc = $proj->oz('jobs', 'run');
		$proc->mustRun();

		self::assertSame(
			'1',
			\trim((string) \file_get_contents($counterFile)),
			'Counter must still be 1: the job must be skipped by the iterator because run_after is in the future.'
		);
	}

	public static function provideDbConfig(): iterable
	{
		return DbTestConfig::allConfigured('retry-delay');
	}

	// -------------------------------------------------------------------------
	// PHP source templates
	// -------------------------------------------------------------------------

	private static function workerSource(string $namespace, string $counterFile, string $failFile): string
	{
		$counterFile = \addslashes($counterFile);
		$failFile    = \addslashes($failFile);

		return <<<PHP
<?php

declare(strict_types=1);

namespace {$namespace}\\Workers;

use OZONE\\Core\\Queue\\Interfaces\\JobContractInterface;
use OZONE\\Core\\Queue\\Interfaces\\WorkerInterface;
use OZONE\\Core\\Utils\\JSONResult;

/**
 * Synchronous worker for RetryDelayTest.
 *
 * Increments a counter file on every run. Throws if a fail-flag file exists.
 */
final class RetryTestWorker implements WorkerInterface
{
	private JSONResult \$result;

	public function __construct(
		private readonly string \$counterFile,
		private readonly string \$failFile,
	) {}

	public static function getName(): string
	{
		return 'retry-test-worker';
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
			throw new \\RuntimeException('Forced failure from RetryTestWorker.');
		}

		\$this->result->setDone()->setData(['ok' => true]);

		return \$this;
	}

	public function getResult(): JSONResult
	{
		return \$this->result;
	}

	public static function fromPayload(array \$payload): static
	{
		return new self(\$payload['counter_file'], \$payload['fail_file']);
	}

	public function getPayload(): array
	{
		return [
			'counter_file' => \$this->counterFile,
			'fail_file'    => \$this->failFile,
		];
	}
}
PHP;
	}

	private static function bootHookSource(
		string $namespace,
		string $counterFile,
		string $failFile,
		string $triggerFile,
	): string {
		$counterFile = \addslashes($counterFile);
		$failFile    = \addslashes($failFile);
		$triggerFile = \addslashes($triggerFile);

		return <<<PHP
<?php

declare(strict_types=1);

namespace {$namespace};

use OZONE\\Core\\Hooks\\Events\\InitHook;
use OZONE\\Core\\Hooks\\Interfaces\\BootHookReceiverInterface;
use OZONE\\Core\\Queue\\JobsManager;
use OZONE\\Core\\Queue\\Queue;
use {$namespace}\\Workers\\RetryTestWorker;

/**
 * Boot hook receiver for RetryDelayTest.
 *
 * Registers the RetryTestWorker and dispatches one job (via InitHook) only
 * when the trigger file is present, consuming it immediately.
 */
final class RetryTestBootHookReceiver implements BootHookReceiverInterface
{
	public static function boot(): void
	{
		JobsManager::registerWorker(RetryTestWorker::class);

		InitHook::listen(static function () {
			\$triggerFile = '{$triggerFile}';

			if (!\\is_file(\$triggerFile)) {
				return;
			}

			\\unlink(\$triggerFile);

			Queue::get(Queue::DEFAULT)
				->push(new RetryTestWorker('{$counterFile}', '{$failFile}'))
				->setRetryMax(3)
				->setRetryDelay(3600)
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
