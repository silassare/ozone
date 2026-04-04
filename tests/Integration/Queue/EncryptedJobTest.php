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

		$proj->writeFileFromStub('EncryptTestWorker', 'app/Workers/EncryptTestWorker.php', [
			'namespace' => $ns,
		]);
		$proj->writeFileFromStub('EncryptTestBootHookReceiver', 'app/EncryptTestBootHookReceiver.php', [
			'namespace'       => $ns,
			'flag_file'       => $flagFile,
			'raw_payload_file'=> $rawPayloadFile,
			'trigger_file'    => $triggerFile,
		]);
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
