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

namespace OZONE\Tests\Integration\Cron;

use OZONE\Tests\Integration\Support\DbTestConfig;
use OZONE\Tests\Integration\Support\OZTestProject;
use PHPUnit\Framework\TestCase;

/**
 * End-to-end test for the cron + queue pipeline.
 *
 * A `TestCronBootHookReceiver` is injected into each test project. Its `boot()` method
 * registers a `CronCollect::listen()` handler that adds a callble task scheduled
 * `everyMinute()` (always due). The task writes a flag file when it runs.
 *
 * Test flow per DB type:
 *   1. `oz cron run` - dispatches due callable tasks into `cron:sync` and immediately
 *      executes them in-process (CronCmd now calls JobsManager::run after runDues).
 *   2. Assert the flag file was written with correct content
 *
 * This exercises: CronCollect -> Cron::call() -> Schedule::everyMinute() -> Cron::runDues()
 * -> Queue::push(CronTaskWorker) -> DbJobStore::add() -> JobsManager::run(CRON_SYNC) ->
 * CronTaskWorker::work() -> CallableTask::run() -> flag file written.
 *
 * @internal
 *
 * @coversNothing
 */
final class CronTaskTest extends TestCase
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
	 * Creates the test project, installs the schema, injects a cron boot hook receiver
	 * that registers an everyMinute task, then runs the full dispatch + process pipeline.
	 *
	 * @dataProvider provideDbConfig
	 */
	public function testCronTaskDispatchedAndProcessed(DbTestConfig $config): void
	{
		$rdbms = $config->rdbms;
		$proj  = OZTestProject::create('cron-task-' . $rdbms, shared: true, fresh: true);
		$proj->writeEnv($config->toEnvArray());

		self::$projects[$rdbms] = $proj;
		self::$dbFiles[$rdbms]  = $config->isSQLite() ? $config->host : null;

		$proj->oz('db', 'build', '--build-all', '--class-only')->mustRun();
		$proj->cleanDb();
		$proj->oz('migrations', 'create', '--force', '--label=initial')->mustRun();
		$proj->oz('migrations', 'run', '--skip-backup')->mustRun();

		$ns       = $proj->getNamespace();
		$flagFile = $proj->getPath() . \DIRECTORY_SEPARATOR . 'cron_ran.flag';

		$proj->writeFile('app/TestCronBootHookReceiver.php', self::bootHookSource($ns, $flagFile));
		$proj->setSetting('oz.boot', "{$ns}\\TestCronBootHookReceiver", true);

		// oz cron run dispatches AND processes the due task (CronCmd runs cron:sync after dispatch).
		$cronProc = $proj->oz('cron', 'run');
		$cronProc->mustRun();

		self::assertFileExists(
			$flagFile,
			"The cron task callable should have written the flag file.\n"
				. 'cron run output:' . "\n" . $cronProc->getOutput() . $cronProc->getErrorOutput()
		);
		self::assertSame('cron-ok', \trim((string) \file_get_contents($flagFile)));
	}

	/**
	 * A second `oz cron run` cycle re-dispatches and re-executes the task
	 * (everyMinute is always due) and exits cleanly.
	 *
	 * @dataProvider provideDbConfig
	 */
	public function testCronRunIsIdempotent(DbTestConfig $config): void
	{
		$proj     = self::getProject($config);
		$flagFile = $proj->getPath() . \DIRECTORY_SEPARATOR . 'cron_ran.flag';

		// Remove the previous flag so we detect a fresh run.
		if (\is_file($flagFile)) {
			\unlink($flagFile);
		}

		$proc1 = $proj->oz('cron', 'run');
		$proc1->mustRun();

		self::assertSame(0, $proc1->getExitCode());
		self::assertFileExists($flagFile, 'Task should run again on a second dispatch cycle.');
	}

	public static function provideDbConfig(): iterable
	{
		return DbTestConfig::allConfigured('cron-task');
	}

	// -------------------------------------------------------------------------
	// PHP source templates injected into test projects
	// -------------------------------------------------------------------------

	/**
	 * Returns the PHP source for the TestCronBootHookReceiver class.
	 *
	 * Registers a CronCollect listener that adds an everyMinute callable task.
	 * The task writes 'cron-ok' to the flag file so the test can confirm it ran.
	 */
	private static function bootHookSource(string $namespace, string $flagFile): string
	{
		$escapedFlag = \addslashes($flagFile);

		return <<<PHP
<?php

declare(strict_types=1);

namespace {$namespace};

use OZONE\\Core\\Cli\\Cron\\Cron;
use OZONE\\Core\\Cli\\Cron\\Hooks\\CronCollect;
use OZONE\\Core\\Hooks\\Interfaces\\BootHookReceiverInterface;
use OZONE\\Core\\Utils\\JSONResult;

/**
 * Registers a callable cron task via CronCollect so the integration test
 * can verify the full dispatch + process pipeline.
 */
final class TestCronBootHookReceiver implements BootHookReceiverInterface
{
	public static function boot(): void
	{
		CronCollect::listen(static function () {
			Cron::call(static function (JSONResult \$result) {
				\\file_put_contents('{$escapedFlag}', 'cron-ok');
				\$result->setDone()->setData(['flag' => '{$escapedFlag}']);
			}, 'test-cron-flag-task')->everyMinute();
		});
	}
}
PHP;
	}

	private static function getProject(DbTestConfig $config): OZTestProject
	{
		$rdbms = $config->rdbms;
		if (!isset(self::$projects[$rdbms])) {
			self::fail(\sprintf(
				'Project for %s not initialized. Did testCronTaskDispatchedAndProcessed pass?',
				$rdbms
			));
		}

		return self::$projects[$rdbms];
	}
}
