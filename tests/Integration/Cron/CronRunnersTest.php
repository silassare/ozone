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

use OZONE\Core\Cli\Cron\CronEndpoint;
use OZONE\Core\Testing\DbTestConfig;
use OZONE\Core\Testing\OZTestProject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

/**
 * Who runs the due cron tasks, in a real project served over HTTP: a request when no scheduler runs,
 * the `oz:cron` route with its key, a scheduler (`oz cron run`, `oz cron work`) keeping them out of
 * requests. The check-in is read back through `oz doctor check`.
 *
 * @internal
 *
 * @coversNothing
 */
final class CronRunnersTest extends TestCase
{
	private const KEY = 'cron-runners-test-key-0123456789abcdef';

	/** @var array<string, array{0: OZTestProject, 1: Process, 2: string, 3: int}> */
	private static array $projects = [];

	/** @var array<string, null|string> */
	private static array $dbFiles = [];

	public static function tearDownAfterClass(): void
	{
		foreach (self::$projects as $rdbms => [$proj, $server]) {
			$server->stop(3);
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

	/**
	 * @dataProvider provideDbConfig
	 */
	public function testARequestRunsTheDueTasksWhenNoSchedulerDoes(DbTestConfig $config): void
	{
		$rdbms = $config->rdbms;
		$proj  = OZTestProject::create('cron-runners-' . $rdbms, shared: false);

		$proj->writeEnv($config->toEnvArray() + ['OZ_CRON_WEB_KEY' => self::KEY]);

		self::$dbFiles[$rdbms] = $config->isSQLite() ? $config->host : null;

		$proj->oz('db', 'build', '--build-all', '--class-only')->mustRun();
		// Every class shares one database on MySQL and PostgreSQL (constraint names are per schema).
		$proj->cleanDb();
		$proj->oz('migrations', 'create', '--force', '--label=initial')->mustRun();
		$proj->oz('migrations', 'run', '--skip-backup')->mustRun();

		$flag = self::flag($proj);

		$proj->writeFileFromStub('TestCronBootHookReceiver', 'app/TestCronBootHookReceiver.php', [
			'namespace' => $proj->getNamespace(),
			'flag_file' => $flag,
		]);
		$proj->setSetting('oz.boot', $proj->getNamespace() . '\TestCronBootHookReceiver', true);
		// A rate of its own, to read back from the answer: the route's limit is a setting.
		$proj->setSetting('oz.cron', 'OZ_CRON_WEB_IP_RATE', 7);

		[$server, $host, $port] = $proj->startServer('api');

		self::$projects[$rdbms] = [$proj, $server, $host, $port];

		// Any request: its response sent, it runs the due tasks, since no scheduler has checked in.
		self::request($host, $port, '/cron-runners-probe');

		self::assertTrue(self::waitFor($flag), 'A request should have run the due task.');
		self::assertSame('requests', self::lastRunner($proj));
	}

	/**
	 * @dataProvider provideDbConfig
	 */
	public function testTheCronRouteNeedsItsKey(DbTestConfig $config): void
	{
		[$proj, , $host, $port] = self::project($config);

		$path = CronEndpoint::PATH;

		self::assertSame(403, self::request($host, $port, $path));
		self::assertSame(403, self::request($host, $port, $path, ['X-OZONE-Cron-Key' => 'not-the-key']));
		self::assertSame(202, self::request($host, $port, $path, ['X-OZONE-Cron-Key' => self::KEY]));

		// the tick runs once the response is sent
		self::assertTrue(self::waitFor(null, static fn (): bool => 'web' === self::lastRunner($proj)));
	}

	/**
	 * The cron route answers the envelope every JSON answer of OZone has.
	 *
	 * @dataProvider provideDbConfig
	 */
	public function testTheCronRouteAnswersTheStandardEnvelope(DbTestConfig $config): void
	{
		[, , $host, $port] = self::project($config);

		[$status, $body] = self::requestBody(
			$host,
			$port,
			CronEndpoint::PATH,
			['X-OZONE-Cron-Key' => self::KEY]
		);

		$data = \json_decode($body, true);

		self::assertSame(202, $status, $body);
		self::assertIsArray($data, $body);
		self::assertSame(0, $data['error'] ?? null, $body);
		self::assertIsString($data['msg'] ?? null, $body);
		self::assertIsInt($data['utime'] ?? null, $body);
		self::assertTrue($data['data']['accepted'] ?? false, $body);
	}

	/**
	 * The per-IP limit of the cron route is what the project's settings say.
	 *
	 * @dataProvider provideDbConfig
	 */
	public function testTheCronRouteRateLimitComesFromTheSettings(DbTestConfig $config): void
	{
		[, , $host, $port] = self::project($config);

		[$status, $headers] = self::requestWithHeaders(
			$host,
			$port,
			CronEndpoint::PATH,
			['X-OZONE-Cron-Key' => self::KEY]
		);

		self::assertSame(202, $status);
		self::assertSame('7', self::header($headers, 'X-RateLimit-Limit'));
	}

	/**
	 * @dataProvider provideDbConfig
	 */
	public function testASchedulerKeepsTheTasksOutOfRequests(DbTestConfig $config): void
	{
		[$proj, , $host, $port] = self::project($config);

		// Twice in a row: the second finds this minute's jobs dispatched already.
		$proj->oz('cron', 'run')->mustRun();
		$proj->oz('cron', 'run')->mustRun();

		self::assertSame('scheduler', self::lastRunner($proj));

		$proj->oz('cron', 'work', '--max-time=2')->mustRun();

		self::assertSame('scheduler', self::lastRunner($proj));

		// Let this server look again this minute: a scheduler runs, so the request leaves the tasks.
		@\unlink($proj->getPath() . '/.ozone/cache/cron.minute');

		self::request($host, $port, '/cron-runners-probe');
		\usleep(500_000);

		self::assertSame('scheduler', self::lastRunner($proj));
	}

	public static function provideDbConfig(): iterable
	{
		return DbTestConfig::allConfigured('cron-runners');
	}

	/**
	 * @return array{0: OZTestProject, 1: Process, 2: string, 3: int}
	 */
	private static function project(DbTestConfig $config): array
	{
		if (!isset(self::$projects[$config->rdbms])) {
			self::fail(\sprintf('Project for %s not initialized.', $config->rdbms));
		}

		return self::$projects[$config->rdbms];
	}

	private static function flag(OZTestProject $proj): string
	{
		return $proj->getPath() . '/cron_ran.flag';
	}

	/**
	 * Sends a request, and returns the status code.
	 *
	 * @param array<string, string> $headers
	 */
	private static function request(string $host, int $port, string $path, array $headers = []): int
	{
		$lines = ['Accept: application/json'];

		foreach ($headers as $name => $value) {
			$lines[] = $name . ': ' . $value;
		}

		$context = \stream_context_create(['http' => [
			'method'        => 'POST',
			'ignore_errors' => true,
			'header'        => \implode("\r\n", $lines) . "\r\n",
			'timeout'       => 30,
		]]);

		\file_get_contents("http://{$host}:{$port}{$path}", false, $context);

		/** @var list<string> $http_response_header */
		\preg_match('~^HTTP/\S+\s+(\d{3})~', $http_response_header[0] ?? '', $m);

		return (int) ($m[1] ?? 0);
	}

	/**
	 * Sends a request, and returns the status code with the response headers.
	 *
	 * @param array<string, string> $headers
	 *
	 * @return array{0:int, 1:list<string>}
	 */
	private static function requestWithHeaders(
		string $host,
		int $port,
		string $path,
		array $headers = []
	): array {
		$lines = ['Accept: application/json'];

		foreach ($headers as $name => $value) {
			$lines[] = $name . ': ' . $value;
		}

		$context = \stream_context_create(['http' => [
			'method'        => 'POST',
			'ignore_errors' => true,
			'header'        => \implode("\r\n", $lines) . "\r\n",
			'timeout'       => 30,
		]]);

		\file_get_contents("http://{$host}:{$port}{$path}", false, $context);

		/** @var list<string> $http_response_header */
		$received = $http_response_header ?? [];

		\preg_match('~^HTTP/\S+\s+(\d{3})~', $received[0] ?? '', $m);

		return [(int) ($m[1] ?? 0), $received];
	}

	/**
	 * Sends a request, and returns the status code with the body.
	 *
	 * @param array<string, string> $headers
	 *
	 * @return array{0:int, 1:string}
	 */
	private static function requestBody(
		string $host,
		int $port,
		string $path,
		array $headers = []
	): array {
		$lines = ['Accept: application/json'];

		foreach ($headers as $name => $value) {
			$lines[] = $name . ': ' . $value;
		}

		$context = \stream_context_create(['http' => [
			'method'        => 'POST',
			'ignore_errors' => true,
			'header'        => \implode("\r\n", $lines) . "\r\n",
			'timeout'       => 30,
		]]);

		$body = @\file_get_contents("http://{$host}:{$port}{$path}", false, $context);

		/** @var list<string> $http_response_header */
		\preg_match('~^HTTP/\S+\s+(\d{3})~', $http_response_header[0] ?? '', $m);

		return [(int) ($m[1] ?? 0), false === $body ? '' : $body];
	}

	/**
	 * The value of a header of a response.
	 *
	 * @param list<string> $headers
	 */
	private static function header(array $headers, string $name): ?string
	{
		foreach ($headers as $header) {
			if (\preg_match('~^' . \preg_quote($name, '~') . ':\s*(.*)$~i', $header, $m)) {
				return \trim($m[1]);
			}
		}

		return null;
	}

	/**
	 * Waits for a file to exist, or a condition to hold, for up to ten seconds.
	 *
	 * @param null|callable():bool $condition
	 */
	private static function waitFor(?string $file, ?callable $condition = null): bool
	{
		for ($i = 0; $i < 50; ++$i) {
			if ((null !== $file && \is_file($file)) || (null !== $condition && $condition())) {
				return true;
			}

			\usleep(200_000);
		}

		return false;
	}

	/**
	 * What ran the last tick, as `oz doctor check` reports it ("requests, 3 s ago").
	 */
	private static function lastRunner(OZTestProject $proj): string
	{
		$proc = $proj->oz('doctor', 'check', '--json');
		$proc->run();

		$out    = \json_decode($proc->getOutput(), true, 512, \JSON_THROW_ON_ERROR);
		$detail = (string) (\array_column($out['data']['checks'], 'detail', 'name')['Cron'] ?? '');

		return \explode(',', $detail)[0];
	}
}
