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
 * Tests end-to-end job payload encryption.
 *
 * Flow per DB type:
 *   1. testSetup -- creates project and schema, dispatches an encrypted job,
 *      reads the raw stored entity payload back from the DB (still the
 *      `{"$enc":...}` blob at that point), verifies the sentinel is present
 *      and the plaintext key is absent, then runs the queue and confirms the
 *      worker produced the expected flag file (proving transparent decryption).
 *   2. testSecondEncryptedJobAlsoWorks -- dispatches a second encrypted job
 *      and repeats the same assertions to confirm there are no one-shot key
 *      or state issues.
 *
 * @internal
 *
 * @coversNothing
 */
final class EncryptedJobTest extends TestCase
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
	 * Creates the project, dispatches an encrypted job, verifies the raw
	 * stored payload carries the "$enc" sentinel (not plaintext), runs the
	 * queue, and asserts the worker flag file was created.
	 *
	 * @dataProvider provideDbConfig
	 */
	public function testSetup(DbTestConfig $config): void
	{
		$rdbms = $config->rdbms;
		$proj  = OZTestProject::create('encrypted-job-' . $rdbms, shared: true, fresh: true);
		$proj->writeEnv($config->toEnvArray());

		self::$projects[$rdbms] = $proj;
		self::$dbFiles[$rdbms]  = $config->isSQLite() ? $config->host : null;

		$proj->oz('db', 'build', '--build-all', '--class-only')->mustRun();
		$proj->cleanDb();
		$proj->oz('migrations', 'create', '--force', '--label=initial')->mustRun();
		$proj->oz('migrations', 'run', '--skip-backup')->mustRun();

		$ns             = $proj->getNamespace();
		$projPath       = $proj->getPath();
		$flagFile       = $projPath . '/worker_flag.txt';
		$rawPayloadFile = $projPath . '/raw_payload.json';
		$triggerFile    = $projPath . '/dispatch.trigger';

		$proj->writeFile('app/Workers/EncryptTestWorker.php', self::workerSource($ns, $flagFile));
		$proj->writeFile('app/EncryptTestBootHookReceiver.php', self::bootHookSource($ns, $flagFile, $rawPayloadFile, $triggerFile));
		$proj->setSetting('oz.boot', "{$ns}\\EncryptTestBootHookReceiver", true);

		// Dispatch the encrypted job without processing it.
		\file_put_contents($triggerFile, '1');
		$proj->oz('jobs', 'dead-letter', '--action=list')->mustRun();

		// Verify the stored payload is encrypted: the "$enc" sentinel must be present,
		// and the original plaintext "flag_file" key must be absent.
		self::assertFileExists($rawPayloadFile, 'Boot hook must have written the raw payload JSON.');
		$rawPayload = \json_decode(\trim((string) \file_get_contents($rawPayloadFile)), true);
		self::assertIsArray($rawPayload, 'Raw payload file must contain valid JSON.');
		self::assertArrayHasKey(
			'$enc',
			$rawPayload,
			'Encrypted job payload must be stored with the "$enc" sentinel key.'
		);
		self::assertArrayNotHasKey(
			'flag_file',
			$rawPayload,
			'Plaintext payload key "flag_file" must not appear in the encrypted store value.'
		);

		// Run the queue -- the job must execute correctly via transparent decryption.
		$proc = $proj->oz('jobs', 'run');
		$proc->mustRun();
		self::assertFileExists(
			$flagFile,
			"Encrypted job must execute correctly after decryption.\n"
				. $proc->getOutput() . $proc->getErrorOutput()
		);
		self::assertSame('ran', \trim((string) \file_get_contents($flagFile)));
	}

	/**
	 * Dispatches a second encrypted job and repeats the storage + execution
	 * assertions to confirm encryption is stateless (no one-shot key issues).
	 *
	 * @dataProvider provideDbConfig
	 */
	public function testSecondEncryptedJobAlsoWorks(DbTestConfig $config): void
	{
		$proj           = self::getProject($config);
		$projPath       = $proj->getPath();
		$flagFile       = $projPath . '/worker_flag.txt';
		$rawPayloadFile = $projPath . '/raw_payload.json';
		$triggerFile    = $projPath . '/dispatch.trigger';

		// Remove previous run's flag so we get a fresh signal.
		if (\is_file($flagFile)) {
			\unlink($flagFile);
		}

		// Dispatch a second encrypted job.
		\file_put_contents($triggerFile, '1');
		$proj->oz('jobs', 'dead-letter', '--action=list')->mustRun();

		// Stored payload must still carry the sentinel.
		$rawPayload = \json_decode(\trim((string) \file_get_contents($rawPayloadFile)), true);
		self::assertIsArray($rawPayload);
		self::assertArrayHasKey('$enc', $rawPayload, 'Second encrypted job must also be stored encrypted.');
		self::assertArrayNotHasKey('flag_file', $rawPayload);

		// And the job must execute correctly.
		$proc = $proj->oz('jobs', 'run');
		$proc->mustRun();
		self::assertFileExists(
			$flagFile,
			"Second encrypted job must execute correctly after decryption.\n"
				. $proc->getOutput() . $proc->getErrorOutput()
		);
	}

	public static function provideDbConfig(): iterable
	{
		return DbTestConfig::allConfigured('encrypted-job');
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
 * Worker for EncryptedJobTest.
 *
 * Writes a flag file on execution so the test can confirm the job ran (and
 * thus that decryption succeeded end-to-end).
 */
final class EncryptTestWorker implements WorkerInterface
{
	private JSONResult \$result;

	public function __construct(private readonly string \$flagFile) {}

	public static function getName(): string
	{
		return 'encrypt-test-worker';
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
		string $rawPayloadFile,
		string $triggerFile,
	): string {
		$flagFile       = \addslashes($flagFile);
		$rawPayloadFile = \addslashes($rawPayloadFile);
		$triggerFile    = \addslashes($triggerFile);

		return <<<PHP
<?php

declare(strict_types=1);

namespace {$namespace};

use OZONE\\Core\\Db\\OZJobsQuery;
use OZONE\\Core\\Hooks\\Events\\InitHook;
use OZONE\\Core\\Hooks\\Interfaces\\BootHookReceiverInterface;
use OZONE\\Core\\Queue\\JobsManager;
use OZONE\\Core\\Queue\\Queue;
use {$namespace}\\Workers\\EncryptTestWorker;

/**
 * Boot hook receiver for EncryptedJobTest.
 *
 * When the trigger file is present:
 *   1. Dispatches one encrypted job.
 *   2. Reads back the raw OZJob entity (payload still encrypted at this point)
 *      and writes its payload JSON to raw_payload.json so the test can
 *      assert the "\$enc" sentinel without touching the DB directly.
 */
final class EncryptTestBootHookReceiver implements BootHookReceiverInterface
{
	public static function boot(): void
	{
		JobsManager::registerWorker(EncryptTestWorker::class);

		InitHook::listen(static function () {
			\$triggerFile = '{$triggerFile}';

			if (!\\is_file(\$triggerFile)) {
				return;
			}

			\\unlink(\$triggerFile);

			\$contract = Queue::get(Queue::DEFAULT)
				->push(new EncryptTestWorker('{$flagFile}'))
				->encrypted()
				->dispatch();

			// Read back the raw entity -- payload is still {"\$enc":"..."} here because
			// OZJobsQuery does NOT decrypt; only DbJobStore::fromEntity() does.
			\$entity = (new OZJobsQuery())
				->whereRefIs(\$contract->getRef())
				->find(1)
				->fetchClass();

			if (null !== \$entity) {
				\\file_put_contents(
					'{$rawPayloadFile}',
					\\json_encode(\$entity->getPayload()->getData())
				);
			}
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
