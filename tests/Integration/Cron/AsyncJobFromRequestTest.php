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

use Override;
use OZONE\Core\Testing\DbTestConfig;
use OZONE\Core\Testing\OZTestProject;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

/**
 * An async job started by a request: cron's requests mode runs a tick in the process that served the
 * request, a task of that tick runs a queue holding an async worker's job, and the job's own process
 * (`oz jobs run`) is started from there -- where `PHP_BINARY` is not the command line's.
 *
 * The request is served by php-cgi, a real SAPI (Apache's mod_fcgid and mod_cgi, lighttpd) and the
 * one this image ships whose binary is not the CLI's, as PHP-FPM's and FrankenPHP's are not either.
 * Started the way a web server starts it, with the request in its environment and, as a PHP-FPM
 * pool's cleared one would be, no PATH.
 *
 * @internal
 */
#[CoversNothing]
final class AsyncJobFromRequestTest extends TestCase
{
	/** @var array<string, OZTestProject> */
	private static array $projects = [];

	/** @var array<string, null|string> */
	private static array $dbFiles = [];

	#[Override]
	public static function tearDownAfterClass(): void
	{
		foreach (self::$projects as $rdbms => $proj) {
			$proj->destroy();

			$file = self::$dbFiles[$rdbms] ?? null;

			if (null !== $file && \is_file($file)) {
				\unlink($file);
			}
		}

		self::$projects = [];
		self::$dbFiles  = [];

		parent::tearDownAfterClass();
	}

	#[DataProvider('provideDbConfig')]
	public function testARequestsTickStartsAnAsyncJobWithTheCommandLine(DbTestConfig $config): void
	{
		$cgi = \dirname(\PHP_BINARY) . \DIRECTORY_SEPARATOR . 'php-cgi';

		self::assertTrue(\is_executable($cgi), 'The request is served by php-cgi, which the php image ships: ' . $cgi);

		$rdbms = $config->rdbms;
		$proj  = OZTestProject::create('cron-async-from-request-' . $rdbms, shared: false);

		self::$projects[$rdbms] = $proj;

		$config = $proj->useDatabase($config);

		self::$dbFiles[$rdbms] = $config->isSQLite() ? $config->host : null;

		$proj->oz('db', 'build', '--build-all', '--class-only')->mustRun();
		// Every class shares one database on MySQL and PostgreSQL (constraint names are per schema).
		$proj->cleanDb();
		$proj->oz('migrations', 'create', '--force', '--label=initial')->mustRun();
		$proj->oz('migrations', 'run', '--skip-backup')->mustRun();

		$path = $proj->getPath();
		$tick = $path . '/tick.json';
		$flag = $path . '/job.json';
		$ns   = $proj->getNamespace();

		$proj->writeFileFromStub('PhpCliTestWorker', 'app/Workers/PhpCliTestWorker.php', [
			'namespace' => $ns,
		]);
		$proj->writeFileFromStub('PhpCliTestBootHookReceiver', 'app/PhpCliTestBootHookReceiver.php', [
			'namespace' => $ns,
			'tick_file' => $tick,
			'flag_file' => $flag,
		]);
		$proj->setSetting('oz.boot', $ns . '\PhpCliTestBootHookReceiver', true);
		// Whatever has ticked before: the requests run the due tasks.
		$proj->setSetting('oz.cron', 'OZ_CRON_RUNNER', 'requests');

		$index = $path . '/public/api/index.php';

		$request = new Process([$cgi], \dirname($index), [
			'PATH'              => false,
			'GATEWAY_INTERFACE' => 'CGI/1.1',
			'REDIRECT_STATUS'   => '200',
			'REQUEST_METHOD'    => 'GET',
			'SCRIPT_FILENAME'   => $index,
			'SCRIPT_NAME'       => '/index.php',
			'REQUEST_URI'       => '/async-job-from-request-probe',
			'QUERY_STRING'      => '',
			'SERVER_NAME'       => '127.0.0.1',
			'SERVER_PORT'       => '80',
			'SERVER_PROTOCOL'   => 'HTTP/1.1',
			'REMOTE_ADDR'       => '127.0.0.1',
			'HTTP_HOST'         => '127.0.0.1',
			'HTTP_ACCEPT'       => 'application/json',
		], null, 60);

		$request->mustRun();

		// The request was answered, and its tick ran the task in that process.
		self::assertStringContainsString('Content-Type: application/json', $request->getOutput());
		self::assertTrue(self::waitFor($tick), 'The request should have run the due task: ' . $request->getOutput());
		self::assertSame('cgi-fcgi', self::json($tick)['sapi'] ?? null);

		// The job ran, in a process of its own, under the command line.
		self::assertTrue(
			self::waitFor($flag),
			"The async job the request's tick started should have run. The logs:\n" . self::logs($path)
		);

		$job = self::json($flag);

		self::assertSame('cli', $job['sapi'] ?? null);
		self::assertNotSame(self::json($tick)['pid'] ?? null, $job['pid'] ?? null);
	}

	public static function provideDbConfig(): iterable
	{
		return DbTestConfig::allConfigured('cron-async-from-request');
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function json(string $file): array
	{
		$data = \json_decode((string) \file_get_contents($file), true);

		return \is_array($data) ? $data : [];
	}

	/**
	 * The end of what the project logged, where a job that did not run says why.
	 */
	private static function logs(string $path): string
	{
		$said = '';

		foreach (\glob($path . '/.ozone/logs/*.log') ?: [] as $log) {
			$said .= '== ' . \basename($log) . \PHP_EOL . \substr((string) \file_get_contents($log), -4000) . \PHP_EOL;
		}

		return '' === $said ? '(none)' : $said;
	}

	/**
	 * Waits for a file to exist, for up to fifteen seconds.
	 */
	private static function waitFor(string $file): bool
	{
		for ($i = 0; $i < 75; ++$i) {
			if (\is_file($file)) {
				return true;
			}

			\usleep(200_000);
		}

		return false;
	}
}
