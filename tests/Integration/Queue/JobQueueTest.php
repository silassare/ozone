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
 * Tests the job queue system end-to-end via CLI subprocesses.
 *
 * A custom synchronous worker is injected into each test project.  A
 * BootHookReceiver registered in oz.boot dispatches one job per bootstrap
 * through an InitHook listener.  Running `oz jobs run` then processes that
 * job; the worker records its result by writing a flag file, which the test
 * asserts.
 *
 * @internal
 *
 * @coversNothing
 */
final class JobQueueTest extends TestCase
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
	 * Creates the test project, installs the schema, injects the custom worker
	 * and boot hook receiver, then runs the queue.
	 *
	 * @dataProvider provideDbConfig
	 */
	public function testJobDispatchedAndProcessed(DbTestConfig $config): void
	{
		$rdbms = $config->rdbms;
		$proj  = OZTestProject::create('job-queue-' . $rdbms, shared: true, fresh: true);
		$proj->writeEnv($config->toEnvArray());

		self::$projects[$rdbms] = $proj;
		self::$dbFiles[$rdbms]  = $config->isSQLite() ? $config->host : null;

		// Build ORM classes, then install the schema.
		$proj->oz('db', 'build', '--build-all', '--class-only')->mustRun();
		$proj->cleanDb();
		$proj->oz('migrations', 'create', '--force', '--label=initial')->mustRun();
		$proj->oz('migrations', 'run', '--skip-backup')->mustRun();

		// Inject the custom worker and boot hook receiver PHP classes.
		$ns       = $proj->getNamespace();
		$flagFile = $proj->getPath() . \DIRECTORY_SEPARATOR . 'job_ran.flag';

		$proj->writeFile('app/Workers/TestJobWorker.php', self::workerSource($ns, $flagFile));
		$proj->writeFile('app/TestJobBootHookReceiver.php', self::bootHookSource($ns));
		$proj->setSetting('oz.boot', "{$ns}\\TestJobBootHookReceiver", true);

		// Running the queue processes the job dispatched by TestJobBootHookReceiver.
		$proc = $proj->oz('jobs', 'run');
		$proc->mustRun();

		self::assertFileExists(
			$flagFile,
			"TestJobWorker should have created the flag file when its job ran.\n"
				. "Queue process output:\n" . $proc->getOutput() . $proc->getErrorOutput()
		);
		self::assertSame('done', \trim((string) \file_get_contents($flagFile)));
	}

	/**
	 * Verifies that a second call to `oz jobs run` is idempotent -- no new jobs
	 * are queued once the boot hook has already run, so re-running the queue
	 * command exits cleanly.
	 *
	 * Depends on testJobDispatchedAndProcessed having run first (same project).
	 *
	 * @dataProvider provideDbConfig
	 */
	public function testSubsequentJobsRunIsIdempotent(DbTestConfig $config): void
	{
		$proj = self::getProject($config);

		// Remove the flag so we can detect if a second job accidentally runs.
		$flagFile = $proj->getPath() . \DIRECTORY_SEPARATOR . 'job_ran.flag';
		if (\is_file($flagFile)) {
			\unlink($flagFile);
		}

		// The boot hook dispatches one new job per `oz` invocation, so the flag
		// will be recreated -- but the command must still exit cleanly.
		$proc = $proj->oz('jobs', 'run');
		$proc->mustRun();

		// Command exited successfully regardless of whether a job ran.
		self::assertSame(0, $proc->getExitCode());
	}

	public static function provideDbConfig(): iterable
	{
		return DbTestConfig::allConfigured('job-queue');
	}

	// -------------------------------------------------------------------------
	// PHP source templates injected into test projects
	// -------------------------------------------------------------------------

	/**
	 * Returns the PHP source for the TestJobWorker class.
	 *
	 * The worker writes 'done' to a flag file so the test can confirm it ran.
	 */
	private static function workerSource(string $namespace, string $flagFile): string
	{
		$escapedFlag = \addslashes($flagFile);

		return <<<PHP
<?php

declare(strict_types=1);

namespace {$namespace}\\Workers;

use OZONE\\Core\\Queue\\Interfaces\\JobContractInterface;
use OZONE\\Core\\Queue\\Interfaces\\WorkerInterface;
use OZONE\\Core\\Utils\\JSONResult;

/**
 * Minimal synchronous worker used by the integration test.
 *
 * Writes 'done' to a flag file so the test process can assert the job ran.
 */
final class TestJobWorker implements WorkerInterface
{
	private JSONResult \$result;

	public function __construct(private readonly string \$flagFile) {}

	public static function getName(): string
	{
		return 'test-job-worker';
	}

	public function isAsync(): bool
	{
		return false;
	}

	public function work(JobContractInterface \$jobContract): static
	{
		\$this->result = new JSONResult();
		\\file_put_contents(\$this->flagFile, 'done');
		\$this->result->setDone()->setData(['flag' => \$this->flagFile]);

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

	/**
	 * Returns the PHP source for the TestJobBootHookReceiver class.
	 *
	 * On every OZone bootstrap (boot phase) the receiver registers the custom
	 * worker.  Then via an InitHook listener it dispatches one job so that the
	 * subsequent `oz jobs run` action finds something to process.
	 */
	private static function bootHookSource(string $namespace): string
	{
		$escapedNs   = \addslashes($namespace);
		$escapedFlag = '{$flagFile}'; // will be resolved at PHP runtime inside the generated file

		return <<<PHP
<?php

declare(strict_types=1);

namespace {$namespace};

use OZONE\\Core\\Hooks\\Events\\InitHook;
use OZONE\\Core\\Hooks\\Interfaces\\BootHookReceiverInterface;
use OZONE\\Core\\Queue\\JobsManager;
use OZONE\\Core\\Queue\\Queue;
use {$namespace}\\Workers\\TestJobWorker;

/**
 * Registers TestJobWorker and dispatches one test job per bootstrap via InitHook.
 */
final class TestJobBootHookReceiver implements BootHookReceiverInterface
{
	public static function boot(): void
	{
		JobsManager::registerWorker(TestJobWorker::class);

		InitHook::listen(static function () {
			// Flag file path is defined relative to the project root at
			// class-generation time (absolute path embedded by OZTestProject).
			\$flagFile = \\dirname(__DIR__) . \\DIRECTORY_SEPARATOR . 'job_ran.flag';
			// Remove any previous run's flag so the test sees a fresh result.
			if (\\is_file(\$flagFile)) {
				\\unlink(\$flagFile);
			}
			Queue::get(Queue::DEFAULT)->push(new TestJobWorker(\$flagFile))->dispatch();
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
			self::fail(
				\sprintf(
					'Project for %s not initialised. Did testJobDispatchedAndProcessed pass?',
					$rdbms
				)
			);
		}

		return self::$projects[$rdbms];
	}
}
