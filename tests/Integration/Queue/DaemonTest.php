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
 * Tests the `oz jobs work` daemon mode.
 *
 * Flow per DB type:
 *   1. testSetup -- creates project and schema, injects a custom worker and
 *      boot hook receiver that dispatches one job when a trigger file is
 *      present.
 *   2. testDaemonProcessesJobAndExits -- pre-dispatches exactly one job via
 *      a neutral oz invocation (trigger present), then runs the daemon with
 *      `--max-jobs=1 --sleep=0`. Verifies the daemon exits cleanly (code 0)
 *      and the worker flag file was created.
 *   3. testDaemonExitsImmediatelyWhenQueueEmpty -- drains any remaining jobs
 *      then runs the daemon with `--max-jobs=1 --sleep=0`. When the queue is
 *      empty the first poll returns 0 jobs, matching max-jobs (0 processed
 *      < 1 but no job available), so the daemon loops once, sleeps 0 s, and
 *      stops once `--max-time` is met. To make the test deterministic we also
 *      pass `--max-time=2` so the process always exits within 2 seconds.
 *
 * @internal
 *
 * @coversNothing
 */
final class DaemonTest extends TestCase
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
     * Creates the project, installs the schema, and injects the worker and
     * boot hook receiver used by subsequent tests.
     *
     * @dataProvider provideDbConfig
     */
    public function testSetup(DbTestConfig $config): void
    {
        $rdbms = $config->rdbms;
        $proj  = OZTestProject::create('daemon-' . $rdbms, shared: true, fresh: true);
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
        $triggerFile = $projPath . '/dispatch.trigger';

        $proj->writeFile('app/Workers/DaemonTestWorker.php', self::workerSource($ns, $flagFile));
        $proj->writeFile('app/DaemonTestBootHookReceiver.php', self::bootHookSource($ns, $flagFile, $triggerFile));
        $proj->setSetting('oz.boot', "{$ns}\\DaemonTestBootHookReceiver", true);

        self::assertTrue(true, 'Setup completed.');
    }

    /**
     * Pre-dispatches one job, then runs the daemon with `--max-jobs=1` and
     * `--sleep=0`. The daemon must process the job and exit with code 0.
     *
     * @dataProvider provideDbConfig
     */
    public function testDaemonProcessesJobAndExits(DbTestConfig $config): void
    {
        $proj        = self::getProject($config);
        $flagFile    = $proj->getPath() . '/worker_flag.txt';
        $triggerFile = $proj->getPath() . '/dispatch.trigger';

        // Ensure no stale flag from a previous run.
        if (\is_file($flagFile)) {
            \unlink($flagFile);
        }

        // Pre-dispatch one job via a neutral invocation (trigger present).
        // "dead-letter --action=list" fires InitHook (which dispatches the job)
        // but does not process any jobs.
        \file_put_contents($triggerFile, '1');
        $proj->oz('jobs', 'dead-letter', '--action=list')->mustRun();

        // Start the daemon. --max-jobs=1 makes it process exactly 1 job and exit.
        // --sleep=0 prevents the 3-second idle poll from blocking the test.
        $proc = $proj->oz('jobs', 'work', '--max-jobs=1', '--sleep=0');
        $proc->mustRun();

        self::assertSame(
            0,
            $proc->getExitCode(),
            "Daemon must exit 0 after processing its job.\n"
                . $proc->getOutput() . $proc->getErrorOutput()
        );
        self::assertFileExists(
            $flagFile,
            "Worker must have written the flag file while running under the daemon.\n"
                . $proc->getOutput() . $proc->getErrorOutput()
        );
        self::assertSame('ran', \trim((string) \file_get_contents($flagFile)));
    }

    /**
     * When the queue is empty the daemon still exits cleanly once the
     * max-time limit is reached.
     *
     * @dataProvider provideDbConfig
     */
    public function testDaemonExitsCleanlyOnMaxTime(DbTestConfig $config): void
    {
        $proj = self::getProject($config);

        // Drain any left-over jobs so the queue is empty.
        $proj->oz('jobs', 'run')->mustRun();

        // The daemon finds nothing to do, polls once (sleep 0), then stops
        // because max-time=2 seconds has elapsed.
        $proc = $proj->oz('jobs', 'work', '--max-time=2', '--sleep=0');
        $proc->mustRun();

        self::assertSame(
            0,
            $proc->getExitCode(),
            "Daemon must exit 0 when max-time is reached with an empty queue.\n"
                . $proc->getOutput() . $proc->getErrorOutput()
        );
    }

    public static function provideDbConfig(): iterable
    {
        return DbTestConfig::allConfigured('daemon');
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
 * Minimal worker for DaemonTest.
 *
 * Writes a flag file when it executes so the test can confirm the daemon
 * actually processed a job.
 */
final class DaemonTestWorker implements WorkerInterface
{
	private JSONResult \$result;

	public function __construct(private readonly string \$flagFile) {}

	public static function getName(): string
	{
		return 'daemon-test-worker';
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
        string $triggerFile,
    ): string {
        $flagFile    = \addslashes($flagFile);
        $triggerFile = \addslashes($triggerFile);

        return <<<PHP
<?php

declare(strict_types=1);

namespace {$namespace};

use OZONE\\Core\\Hooks\\Events\\InitHook;
use OZONE\\Core\\Hooks\\Interfaces\\BootHookReceiverInterface;
use OZONE\\Core\\Queue\\JobsManager;
use OZONE\\Core\\Queue\\Queue;
use {$namespace}\\Workers\\DaemonTestWorker;

/**
 * Boot hook receiver for DaemonTest.
 *
 * Registers the worker. When the trigger file is present, dispatches one job
 * WITHOUT running it -- this simulates work arriving in the queue before the
 * daemon starts.
 */
final class DaemonTestBootHookReceiver implements BootHookReceiverInterface
{
	public static function boot(): void
	{
		JobsManager::registerWorker(DaemonTestWorker::class);

		InitHook::listen(static function () {
			\$triggerFile = '{$triggerFile}';

			if (!\\is_file(\$triggerFile)) {
				return;
			}

			\\unlink(\$triggerFile);

			Queue::get(Queue::DEFAULT)
				->push(new DaemonTestWorker('{$flagFile}'))
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
            self::fail(\sprintf('Project for %s not initialized. Did testSetup pass?', $rdbms));
        }

        return self::$projects[$rdbms];
    }
}
