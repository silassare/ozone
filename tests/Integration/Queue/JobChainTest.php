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

		$proj->writeFile('app/Workers/ChainTestWorkerA.php', self::workerASource($ns, $flagA));
		$proj->writeFile('app/Workers/ChainTestWorkerB.php', self::workerBSource($ns, $flagB));
		$proj->writeFile('app/ChainTestBootHookReceiver.php', self::bootHookSource($ns, $flagA, $flagB, $trigger));
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
 * First worker in the chain. Writes a flag file and succeeds.
 */
final class ChainTestWorkerA implements WorkerInterface
{
	private JSONResult \$result;

	public function __construct(private readonly string \$flagFile) {}

	public static function getName(): string
	{
		return 'chain-test-worker-a';
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
 * Second worker in the chain. Writes a flag file and succeeds.
 */
final class ChainTestWorkerB implements WorkerInterface
{
	private JSONResult \$result;

	public function __construct(private readonly string \$flagFile) {}

	public static function getName(): string
	{
		return 'chain-test-worker-b';
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
		string $triggerFile,
	): string {
		$flagA       = \addslashes($flagA);
		$flagB       = \addslashes($flagB);
		$triggerFile = \addslashes($triggerFile);

		return <<<PHP
<?php

declare(strict_types=1);

namespace {$namespace};

use OZONE\\Core\\Hooks\\Events\\InitHook;
use OZONE\\Core\\Hooks\\Interfaces\\BootHookReceiverInterface;
use OZONE\\Core\\Queue\\JobsManager;
use OZONE\\Core\\Queue\\Queue;
use {$namespace}\\Workers\\ChainTestWorkerA;
use {$namespace}\\Workers\\ChainTestWorkerB;

/**
 * Boot hook receiver for JobChainTest.
 *
 * Registers both chain workers and dispatches WorkerA with WorkerB in its
 * chain when the trigger file is present.
 */
final class ChainTestBootHookReceiver implements BootHookReceiverInterface
{
	public static function boot(): void
	{
		JobsManager::registerWorker(ChainTestWorkerA::class);
		JobsManager::registerWorker(ChainTestWorkerB::class);

		InitHook::listen(static function () {
			\$triggerFile = '{$triggerFile}';

			if (!\\is_file(\$triggerFile)) {
				return;
			}

			\\unlink(\$triggerFile);

			\$chain = [
				[
					'worker'  => ChainTestWorkerB::getName(),
					'payload' => (new ChainTestWorkerB('{$flagB}'))->getPayload(),
					'queue'   => Queue::DEFAULT,
				],
			];

			Queue::get(Queue::DEFAULT)
				->push(new ChainTestWorkerA('{$flagA}'))
				->setChain(\$chain)
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
			self::fail(\sprintf('Project for %s not initialised. Did testFirstJobInChainRuns pass?', $rdbms));
		}

		return self::$projects[$rdbms];
	}
}
