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

namespace OZONE\Tests\Integration\Scopes;

use Override;
use OZONE\Core\App\InstanceLayout;
use OZONE\Core\Testing\DbTestConfig;
use OZONE\Core\Testing\OZTestProject;
use PDO;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Process\Process;

/**
 * A route of the `api` scope called as nobody, in process, from the other places a project needs it:
 * a page of a `www` scope (`--api=false`) served to a signed-in visitor, and a cron task. What comes
 * back must be what an anonymous visitor of the `api` server gets, whoever asked, and the asker must
 * go on after a refusal.
 *
 * @internal
 */
#[CoversNothing]
final class AnonymousCallTest extends TestCase
{
	private const string AGENT         = 'User-Agent: OZoneTest/1.0 (anonymous call)';
	private const string USER_EMAIL    = 'ann.call@example.com';
	private const string USER_PASSWORD = 'Ann_Call_42';
	private const string RESULT_FILE   = 'anon_call_cron.json';

	/** @var array<string, OZTestProject> projects by RDBMS */
	private static array $projects = [];

	/** @var array<string, array<string, array{0: Process, 1: int}>> each project's servers, by scope */
	private static array $servers = [];

	private static string $host = '127.0.0.1';

	#[Override]
	public static function tearDownAfterClass(): void
	{
		foreach (self::$servers as $servers) {
			foreach ($servers as [$server]) {
				if ($server->isRunning()) {
					$server->stop(3);
				}
			}
		}

		foreach (self::$projects as $project) {
			$project->destroy();
		}

		self::$servers  = [];
		self::$projects = [];

		parent::tearDownAfterClass();
	}

	/**
	 * A signed-in visitor of `www` asks a page that calls the `api` scope: the call is nobody, and
	 * answers exactly what the `api` server answers a visitor with no cookie.
	 */
	#[DataProvider('provideDbConfig')]
	public function testAPageCallingTheApiAsNobodyGetsWhatAnAnonymousVisitorGets(DbTestConfig $config): void
	{
		$rdbms = self::project($config);
		$sid   = self::login($rdbms);

		$cookie = 'Cookie: OZONE_SID=' . $sid;

		// The visitor is signed in, as the api server itself shows.
		[$status, $body] = self::request($rdbms, 'api', '/anon-call/members', [$cookie]);

		self::assertSame(200, $status, $body);

		// The last one's class lives in the `api` scope's own directory, which `www` does not read.
		foreach (['service', 'members', 'scoped'] as $service) {
			[$status, $body] = self::request($rdbms, 'www', '/anon-call/proxy/' . $service, [$cookie]);

			self::assertSame(200, $status, $body);

			$page = self::data($body);

			self::assertTrue($page['caller_signed_in'], $body);
			self::assertSame('www', $page['caller_scope'], $body);

			[$direct_status, $direct_body] = self::request($rdbms, 'api', '/anon-call/' . $service);

			self::assertSame($direct_status, $page['status'], $service . ': ' . $body);
			self::assertSame(self::answer($direct_body), self::answer($page['body']), $service);
		}

		// What the call answered, for the record: nobody, from this machine, with the rows.
		[, $body] = self::request($rdbms, 'www', '/anon-call/proxy/service', [$cookie]);

		self::assertSame(
			['scope' => 'api', 'signed_in' => false, 'countries' => 1, 'ip' => '127.0.0.1'],
			self::answer(self::data($body)['body'])['data']
		);

		[, $body] = self::request($rdbms, 'www', '/anon-call/proxy/members', [$cookie]);

		self::assertSame(403, self::data($body)['status'], $body);

		[, $body] = self::request($rdbms, 'www', '/anon-call/proxy/scoped', [$cookie]);

		self::assertSame(
			['scoped' => true, 'scope' => 'api'],
			self::answer(self::data($body)['body'])['data'] ?? null,
			$body
		);
	}

	/**
	 * A cron task makes the same calls from the command line: a refusal answered as a status, a
	 * service answered as to anybody, and the process going on to exit cleanly.
	 */
	#[DataProvider('provideDbConfig')]
	public function testACronTaskCallsTheApiAsNobodyAndGoesOn(DbTestConfig $config): void
	{
		$rdbms  = self::project($config);
		$proj   = self::$projects[$rdbms];
		$result = $proj->getPath() . \DIRECTORY_SEPARATOR . self::RESULT_FILE;

		if (\is_file($result)) {
			\unlink($result);
		}

		$cron = $proj->oz('cron', 'run');
		$cron->run();

		$out = $cron->getOutput() . $cron->getErrorOutput();

		self::assertSame(0, $cron->getExitCode(), $out);
		self::assertFileExists($result, $out);

		$seen = \json_decode((string) \file_get_contents($result), true, 512, \JSON_THROW_ON_ERROR);

		self::assertIsArray($seen);
		self::assertSame(403, $seen['refused_status']);
		self::assertSame(200, $seen['served_status']);
		self::assertSame('root', $seen['scope_after']);

		[$status, $body] = self::request($rdbms, 'api', '/anon-call/service');

		self::assertSame(200, $status, $body);
		self::assertSame(self::answer($body), self::answer($seen['served_body']));
	}

	/**
	 * @return array<string, array{DbTestConfig}>
	 */
	public static function provideDbConfig(): iterable
	{
		return DbTestConfig::allConfigured('anon-call');
	}

	/**
	 * In production a scope's routes come from its own route table: the call compiles the `api`
	 * table in the `api` scope's directory, never in the one of the scope it was made from.
	 */
	public function testInProductionTheCallReadsTheTargetsOwnRouteTable(): void
	{
		$proj = OZTestProject::create('anon-call-production', fresh: true);

		try {
			$proj->writeEnv([
				'ENV_MODE'    => 'production',
				'OZ_DB_RDBMS' => 'sqlite',
				'OZ_DB_HOST'  => $proj->getPath() . '/anon_call_production.sqlite',
			]);

			self::prepare($proj);

			[$server, , $port] = $proj->startServer('www', self::$host);

			try {
				[$status, $body] = self::send($port, 'GET', '/anon-call/proxy/service');
			} finally {
				$server->stop(3);
			}

			self::assertSame(200, $status, $body);
			self::assertSame(200, self::data($body)['status'], $body);

			$build = InstanceLayout::releaseBuildPath($proj->getPath());

			self::assertCount(1, \glob($build . '/routes/www/web.*.php') ?: [], 'the page\'s own table');
			self::assertCount(1, \glob($build . '/routes/api/api.*.php') ?: [], 'the called scope\'s own table');
			self::assertSame([], \glob($build . '/routes/www/api.*.php') ?: []);
		} finally {
			$proj->destroy();
		}
	}

	/**
	 * The project of an RDBMS, made and served once for the class: an `api` scope with the service,
	 * a `www` scope with the page, a user, a country row, a cron task.
	 */
	private static function project(DbTestConfig $config): string
	{
		$rdbms = $config->rdbms;

		if (isset(self::$projects[$rdbms])) {
			return $rdbms;
		}

		$proj   = OZTestProject::create('anon-call-' . $rdbms, fresh: true);
		$config = $proj->useDatabase($config);

		self::$projects[$rdbms] = $proj;

		self::prepare($proj, $config);

		$proj->oz(
			'auth-users',
			'add',
			'--civility=Mrs',
			'--display_name=Ann Call',
			'--first_name=Ann',
			'--last_name=Call',
			'--email=' . self::USER_EMAIL,
			'--gender=Female',
			'--birth_date=1990-05-17',
			'--pass=' . self::USER_PASSWORD,
			'--cc2=BJ',
		)->mustRun();

		foreach (['api', 'www'] as $scope) {
			try {
				[$server, , $port] = $proj->startServer($scope, self::$host);
			} catch (RuntimeException $e) {
				self::fail($e->getMessage());
			}

			self::$servers[$rdbms][$scope] = [$server, $port];
		}

		return $rdbms;
	}

	/**
	 * The scopes, the routes, the cron task and the schema of a project, with one country.
	 */
	private static function prepare(OZTestProject $proj, ?DbTestConfig $config = null): void
	{
		$ns = $proj->getNamespace();

		$proj->oz('scopes', 'add', '--name=www', '--origin=http://' . self::$host, '--api=false')->mustRun();

		$proj->writeFileFromStub('AnonCallApiService', 'app/AnonCallApiService.php', ['namespace' => $ns]);
		$proj->setSetting('oz.routes.api', "{$ns}\\AnonCallApiService", true);
		$proj->writeFileFromStub('AnonCallScopedService', 'scopes/api/AnonCallScopedService.php', ['namespace' => $ns]);
		$proj->writeFile(
			'scopes/api/settings/oz.routes.api.php',
			\sprintf("<?php\n\ndeclare(strict_types=1);\n\nreturn [\\%s\\AnonCallScopedService::class => true];\n", $ns)
		);
		$proj->writeFileFromStub('AnonCallWebPage', 'app/AnonCallWebPage.php', ['namespace' => $ns]);
		$proj->setSetting('oz.routes.web', "{$ns}\\AnonCallWebPage", true);
		$proj->writeFileFromStub('AnonCallCronBootHookReceiver', 'app/AnonCallCronBootHookReceiver.php', [
			'namespace'   => $ns,
			'result_file' => $proj->getPath() . \DIRECTORY_SEPARATOR . self::RESULT_FILE,
		]);
		$proj->setSetting('oz.boot', "{$ns}\\AnonCallCronBootHookReceiver", true);
		// The task runs when the test says, never after a request of the servers.
		$proj->setSetting('oz.cron', 'OZ_CRON_RUNNER', 'scheduler');
		$proj->setSetting('oz.sessions', 'OZ_SESSION_SOURCE_KEY', 'user_agent');

		$proj->oz('db', 'build', '--build-all', '--class-only')->mustRun();
		$proj->cleanDb();
		$proj->oz('migrations', 'create', '--force', '--label=initial')->mustRun();
		$proj->oz('migrations', 'run', '--skip-backup')->mustRun();

		self::seedCountry($proj, $config);
	}

	private static function login(string $rdbms): string
	{
		[$status, $body, $headers] = self::request($rdbms, 'api', '/login', [], [
			'auth_user_type'             => 'user',
			'auth_user_identifier_type'  => 'email',
			'auth_user_identifier_value' => self::USER_EMAIL,
			'auth_user_password'         => self::USER_PASSWORD,
		]);

		self::assertSame(200, $status, $body);

		foreach ($headers as $header) {
			if (\preg_match('~^Set-Cookie:\s*OZONE_SID=([^;]+)~i', $header, $m)) {
				return \urldecode($m[1]);
			}
		}

		self::fail('Signing in gave no session cookie: ' . $body);
	}

	/**
	 * @param list<string>          $headers
	 * @param array<string, string> $fields
	 *
	 * @return array{0: int, 1: string, 2: list<string>}
	 */
	private static function request(
		string $rdbms,
		string $scope,
		string $path,
		array $headers = [],
		array $fields = []
	): array {
		$port = self::$servers[$rdbms][$scope][1];

		return self::send($port, [] === $fields ? 'GET' : 'POST', $path, $headers, $fields);
	}

	/**
	 * @param list<string>          $headers
	 * @param array<string, string> $fields
	 *
	 * @return array{0: int, 1: string, 2: list<string>}
	 */
	private static function send(
		int $port,
		string $method,
		string $path,
		array $headers = [],
		array $fields = []
	): array {
		$opts = [
			'method'          => $method,
			'timeout'         => 30,
			'ignore_errors'   => true,
			'follow_location' => false,
			'header'          => "Accept: application/json\r\n" . self::AGENT . "\r\n"
				. \implode('', \array_map(static fn (string $h): string => $h . "\r\n", $headers)),
		];

		if ([] !== $fields) {
			$opts['content'] = \http_build_query($fields);
			$opts['header'] .= "Content-Type: application/x-www-form-urlencoded\r\n";
		}

		$url     = 'http://' . self::$host . ':' . $port . $path;
		$body    = @\file_get_contents($url, false, \stream_context_create(['http' => $opts]));
		$headers = \http_get_last_response_headers() ?? [];
		$status  = isset($headers[0]) && \preg_match('~HTTP/\d+(?:\.\d+)? (\d+)~', $headers[0], $m) ? (int) $m[1] : 0;

		return [$status, false === $body ? '' : $body, $headers];
	}

	/**
	 * The `data` of a page's envelope.
	 *
	 * @return array<string, mixed>
	 */
	private static function data(string $body): array
	{
		$decoded = \json_decode($body, true);

		self::assertIsArray($decoded, $body);
		self::assertIsArray($decoded['data'] ?? null, $body);

		return $decoded['data'];
	}

	/**
	 * An envelope without the time it was written at, which two answers a second apart differ by.
	 *
	 * @return array<string, mixed>
	 */
	private static function answer(string $body): array
	{
		$decoded = \json_decode($body, true);

		self::assertIsArray($decoded, $body);

		unset($decoded['utime'], $decoded['stime']);

		return $decoded;
	}

	private static function seedCountry(OZTestProject $proj, ?DbTestConfig $config): void
	{
		if (null === $config || $config->isSQLite()) {
			$file = null === $config ? $proj->getPath() . '/anon_call_production.sqlite' : $config->host;
			$pdo  = new PDO('sqlite:' . $file);
		} elseif ($config->isMySQL()) {
			$pdo = new PDO(
				\sprintf('mysql:host=%s;port=%d;dbname=%s', $config->host, $config->port, $config->name),
				$config->user,
				$config->pass
			);
		} else {
			$pdo = new PDO(
				\sprintf('pgsql:host=%s;port=%d;dbname=%s', $config->host, $config->port, $config->name),
				$config->user,
				$config->pass
			);
		}

		$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

		$prefix = $proj->tablePrefix();
		$table  = null === $prefix || '' === $prefix ? 'oz_countries' : $prefix . '_oz_countries';
		$quote  = null !== $config && $config->isMySQL() ? '`' : '"';
		$now    = (string) \time();

		$pdo->prepare(\sprintf(
			'INSERT INTO %2$s%1$s%2$s (country_cc2, country_calling_code, country_name, country_name_real,'
				. ' country_data, country_is_valid, country_created_at, country_updated_at, country_deleted,'
				. ' country_deleted_at) VALUES (?, ?, ?, ?, ?, TRUE, ?, ?, FALSE, NULL)',
			$table,
			$quote
		))->execute(['BJ', '+229', 'Benin', 'Benin', '{}', $now, $now]);
	}
}
