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

namespace OZONE\Tests\Integration\Maintenance;

use Override;
use OZONE\Core\Testing\OZTestProject;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Process\Process;

/**
 * Maintenance as an operator runs it: `oz maintenance on|off|status` in a project, and what an API
 * scope and a web scope served by real servers answer meanwhile.
 *
 * @internal
 */
#[CoversNothing]
final class MaintenanceModeTest extends TestCase
{
	private const string HOST = '127.0.0.1';

	private const string CRON_KEY = 'maintenance-test-cron-key';

	private const array FOREIGN = ['Accept: application/json', 'Origin: http://elsewhere.example'];

	private const string ADMIN_EMAIL = 'ada.admin@example.com';

	private const string AGENT = 'User-Agent: OZoneTest/1.0 (maintenance)';

	/**
	 * Where the project's `RouteFound` listener writes the name of each route it is told of.
	 */
	private static string $found_log = '';

	private static ?OZTestProject $proj = null;

	/**
	 * The port of each scope's server, by scope name.
	 *
	 * @var array<string, int>
	 */
	private static array $ports = [];

	/**
	 * @var list<Process>
	 */
	private static array $servers = [];

	#[Override]
	public static function setUpBeforeClass(): void
	{
		parent::setUpBeforeClass();

		$proj = OZTestProject::create('maintenance-mode', fresh: true);
		$ns   = $proj->getNamespace();

		// What a maintenance does needs no database; the cron route's rate limit does. SQLite, in a file
		// of this project's own.
		$proj->writeEnv([
			'OZ_DB_RDBMS'     => 'sqlite',
			'OZ_DB_HOST'      => $proj->getPath() . '/maintenance_test.sqlite',
			'OZ_CRON_WEB_KEY' => self::CRON_KEY,
		]);
		$proj->oz('db', 'build', '--build-all', '--class-only')->mustRun();
		$proj->oz('migrations', 'create', '--force', '--label=initial')->mustRun();
		$proj->oz('migrations', 'run', '--skip-backup')->mustRun();

		// An administrator, as `oz auth-users grant` makes one.
		$proj->writeFileFromStub('SeedUser', 'seed_user.php', ['email' => self::ADMIN_EMAIL]);
		new Process([\PHP_BINARY, 'seed_user.php'], $proj->getPath())->mustRun();
		$proj->oz('auth-users', 'grant', '--user=' . self::ADMIN_EMAIL, '--role=admin')->mustRun();
		$proj->oz('scopes', 'add', '--name=www', '--origin=http://localhost', '--api=false')->mustRun();
		$proj->writeFileFromStub('MaintenanceTestRoutesProvider', 'app/MaintenanceTestRoutesProvider.php', [
			'namespace' => $ns,
		]);
		$proj->setSetting('oz.routes', "{$ns}\\MaintenanceTestRoutesProvider", true);
		self::$found_log = $proj->getPath() . '/route_found.log';

		$proj->writeFileFromStub('MaintenanceTestBootHookReceiver', 'app/MaintenanceTestBootHookReceiver.php', [
			'namespace' => $ns,
			'found_log' => self::$found_log,
		]);
		$proj->setSetting('oz.boot', "{$ns}\\MaintenanceTestBootHookReceiver", true);

		try {
			foreach (['api', 'www'] as $scope) {
				[$server, , $port] = $proj->startServer($scope, self::HOST);

				self::$servers[]      = $server;
				self::$ports[$scope]  = $port;
			}
		} catch (RuntimeException $e) {
			self::stopServers();
			$proj->destroy();
			self::fail($e->getMessage());
		}

		self::$proj = $proj;
	}

	#[Override]
	public static function tearDownAfterClass(): void
	{
		self::stopServers();
		self::$proj?->destroy();

		parent::tearDownAfterClass();
	}

	#[Override]
	protected function tearDown(): void
	{
		self::proj()->oz('maintenance', 'off')->mustRun();
	}

	public function testBothScopesAreServedWhileNoMaintenanceIsOn(): void
	{
		self::assertSame(200, self::get('api', '/mt-ping')['status']);
		self::assertSame(200, self::get('www', '/mt-ping')['status']);
		// the project answers the paths no route matches
		self::assertSame(['fallback' => true], self::get('api', '/mt-nowhere')['json']);
		// a request from an origin the project does not allow is refused before it is routed
		self::assertSame(403, self::get('api', '/mt-ping', self::FOREIGN)['status']);
		self::assertNull(self::dataOf(self::proj()->oz('maintenance', 'status', '--json'))['maintenance'] ?? null);
	}

	public function testOnHoldsAnApiAndAWebRequest(): void
	{
		$on = self::dataOf(
			self::proj()->oz('maintenance', 'on', '--retry-after=120', '--message=Back at noon', '--json')
		);

		self::assertSame('all', $on['maintenance']['covers'] ?? null);

		$api = self::get('api', '/mt-ping');

		self::assertSame(503, $api['status']);
		self::assertSame('120', $api['headers']['retry-after'] ?? null);
		self::assertSame('no-store', $api['headers']['cache-control'] ?? null);
		self::assertSame(1, $api['json']['error'] ?? null);
		self::assertSame('OZ_MAINTENANCE', $api['json']['msg'] ?? null);
		self::assertSame(['retry_after' => 120, 'message' => 'Back at noon'], $api['json']['data'] ?? null);

		$web = self::get('www', '/mt-ping', ['Accept: text/html']);

		self::assertSame(503, $web['status']);
		self::assertSame('120', $web['headers']['retry-after'] ?? null);
		self::assertStringStartsWith('text/html', $web['headers']['content-type'] ?? '');
		self::assertStringContainsString('<title>Under maintenance</title>', $web['body']);
		self::assertStringContainsString('Back at noon', $web['body']);

		// A path no route matches has no route to let anyone through, and the project's own answer to
		// such a path is not given while it lasts.
		self::assertSame(503, self::get('api', '/mt-nowhere')['status']);
		// Nor is any other refusal: whatever stops a held request on its way, it is held.
		self::assertSame(503, self::get('api', '/mt-ping', self::FOREIGN)['status']);
	}

	public function testNoProjectListenerRunsOnAHeldRequestAndOneRunsForAnAdministrator(): void
	{
		\file_put_contents(self::$found_log, '');

		self::assertSame(200, self::get('api', '/mt-ping')['status']);
		self::assertSame(['mt:ping'], self::foundRoutes());

		$session = self::signIn();

		self::proj()->oz('maintenance', 'on')->mustRun();
		\file_put_contents(self::$found_log, '');

		self::assertSame(503, self::get('api', '/mt-ping', ['Accept: application/json', self::AGENT])['status']);
		self::assertSame([], self::foundRoutes(), 'a request answered 503 ran a project listener');

		$admin = self::get('api', '/mt-ping', ['Accept: application/json', self::AGENT, 'Cookie: ' . $session]);

		self::assertSame(200, $admin['status'], $admin['body']);
		self::assertSame(['mt:ping'], self::foundRoutes());
	}

	public function testAScopeTheMaintenanceDoesNotNameIsServed(): void
	{
		self::proj()->oz('maintenance', 'on', '--scopes=www')->mustRun();

		self::assertSame(200, self::get('api', '/mt-ping')['status']);
		self::assertSame(503, self::get('www', '/mt-ping')['status']);

		self::proj()->oz('maintenance', 'on', '--only=api')->mustRun();

		self::assertSame(503, self::get('api', '/mt-ping')['status']);
		self::assertSame(200, self::get('www', '/mt-ping')['status']);

		self::proj()->oz('maintenance', 'on', '--only=web')->mustRun();

		self::assertSame(200, self::get('api', '/mt-ping')['status']);
		self::assertSame(503, self::get('www', '/mt-ping')['status']);
	}

	public function testAScopeNameThatIsNoScopeIsRefused(): void
	{
		$on = self::proj()->oz('maintenance', 'on', '--scopes=wwww');

		$on->run();

		self::assertNotSame(0, $on->getExitCode());
		self::assertStringContainsString('"wwww" is not a scope of this project', $on->getOutput());
		self::assertSame(200, self::get('www', '/mt-ping')['status']);
	}

	public function testTheSecretLinkLetsThatBrowserThroughUntilTheNextOn(): void
	{
		$on     = self::dataOf(self::proj()->oz('maintenance', 'on', '--json'));
		$secret = (string) ($on['secret'] ?? '');

		self::assertSame('oz_maintenance', $on['link_param'] ?? null);
		self::assertNotSame('', $secret);

		$link = self::get('www', '/mt-ping?a=1&oz_maintenance=' . \rawurlencode($secret));

		self::assertSame(302, $link['status']);
		self::assertSame('/mt-ping?a=1', $link['headers']['location'] ?? null);
		self::assertMatchesRegularExpression('~^oz_maintenance=([a-f0-9]{64});~', $link['headers']['set-cookie'] ?? '');

		$cookie = \explode(';', (string) ($link['headers']['set-cookie'] ?? ''))[0];

		self::assertSame(200, self::get('www', '/mt-ping?a=1', ['Cookie: ' . $cookie])['status']);
		self::assertSame(503, self::get('www', '/mt-ping')['status']);
		self::assertSame(503, self::get('www', '/mt-ping?oz_maintenance=not-the-secret')['status']);

		// on again: a new maintenance, which the cookie of the last one does not open
		self::proj()->oz('maintenance', 'on')->mustRun();

		self::assertSame(503, self::get('www', '/mt-ping', ['Cookie: ' . $cookie])['status']);
	}

	public function testAnAllowedAddressIsLetThrough(): void
	{
		self::proj()->oz('maintenance', 'on', '--allow=10.0.0.0/8,' . self::HOST)->mustRun();

		self::assertSame(200, self::get('api', '/mt-ping')['status']);

		self::proj()->oz('maintenance', 'on', '--allow=10.0.0.0/8')->mustRun();

		self::assertSame(503, self::get('api', '/mt-ping')['status']);

		$bad = self::proj()->oz('maintenance', 'on', '--allow=10.0.0.0/33');

		$bad->run();

		self::assertNotSame(0, $bad->getExitCode());
	}

	public function testTheCronRouteIsServedSoCronKeepsRunning(): void
	{
		self::proj()->oz('maintenance', 'on')->mustRun();

		$cron = self::get('api', '/oz-cron', ['Accept: application/json', 'X-OZONE-Cron-Key: ' . self::CRON_KEY]);

		self::assertSame(202, $cron['status']);
		// served by its own rule, its key: not let through as a request of no route would be
		self::assertSame(403, self::get('api', '/oz-cron')['status']);
		self::assertSame(503, self::get('api', '/mt-ping')['status']);
	}

	/**
	 * A crawler answered 503 with a `Retry-After` on `/robots.txt` stops crawling the host until
	 * then, which is what a maintenance wants of it; and the route runs the project's provider, which
	 * may read what the maintenance is changing.
	 */
	public function testRobotsTxtIsHeldSoCrawlersWait(): void
	{
		foreach (['api', 'www'] as $scope) {
			self::assertSame(200, self::get($scope, '/robots.txt', [])['status']);
		}

		self::proj()->oz('maintenance', 'on', '--retry-after=300')->mustRun();

		foreach (['api', 'www'] as $scope) {
			$robots = self::get($scope, '/robots.txt', ['User-Agent: Googlebot/2.1']);

			self::assertSame(503, $robots['status'], $scope);
			self::assertSame('300', $robots['headers']['retry-after'] ?? null, $scope);
		}
	}

	public function testStatusAndDoctorSayWhatIsOn(): void
	{
		self::proj()->oz('maintenance', 'on', '--scopes=www', '--pause-jobs', '--allow=10.0.0.0/8')->mustRun();

		$status = self::dataOf(self::proj()->oz('maintenance', 'status', '--json'))['maintenance'] ?? null;

		self::assertIsArray($status);
		self::assertSame('scopes', $status['covers'] ?? null);
		self::assertSame(['www'], $status['scopes'] ?? null);
		self::assertSame(['10.0.0.0/8'], $status['allow'] ?? null);
		self::assertTrue($status['pause_jobs'] ?? null);
		self::assertArrayNotHasKey('secret', $status);

		$doctor = self::proj()->oz('doctor', 'check', '--json');

		// The other checks of a development project may fail: only the maintenance row is read.
		$doctor->run();

		$rows = \array_values(\array_filter(
			(array) (self::dataOf($doctor)['checks'] ?? []),
			static fn (mixed $row): bool => \is_array($row) && 'Maintenance' === ($row['name'] ?? null)
		));

		self::assertCount(1, $rows);
		self::assertSame('warn', $rows[0]['status'] ?? null);
		self::assertStringContainsString('for the scopes www', (string) ($rows[0]['detail'] ?? ''));
		self::assertStringContainsString('jobs paused', (string) ($rows[0]['detail'] ?? ''));
	}

	public function testPausedJobsAreSkippedByTheRunners(): void
	{
		self::proj()->oz('maintenance', 'on', '--pause-jobs')->mustRun();

		$cron = self::proj()->oz('cron', 'run');
		$cron->mustRun();

		self::assertStringContainsString('Tick skipped: a maintenance pauses the jobs.', $cron->getOutput());

		$jobs = self::proj()->oz('jobs', 'run');
		$jobs->mustRun();

		self::assertStringContainsString('No job run: a maintenance pauses the jobs.', $jobs->getOutput());

		// A task started by hand is the same work: refused, unless the operator insists.
		$start = self::proj()->oz('cron', 'start', '--name=oz:gc');
		$start->run();

		self::assertNotSame(0, $start->getExitCode());
		self::assertStringContainsString('jobs are paused by a maintenance. Add --force', $start->getOutput());

		self::proj()->oz('cron', 'start', '--name=oz:gc', '--force')->mustRun();
	}

	public function testOffRestoresService(): void
	{
		self::proj()->oz('maintenance', 'on')->mustRun();

		self::assertSame(503, self::get('api', '/mt-ping')['status']);
		self::assertSame(503, self::get('www', '/mt-ping')['status']);

		$off = self::dataOf(self::proj()->oz('maintenance', 'off', '--json'));

		self::assertTrue($off['was_on'] ?? null);
		self::assertSame(200, self::get('api', '/mt-ping')['status']);
		self::assertSame(200, self::get('www', '/mt-ping')['status']);
		self::assertFalse(self::dataOf(self::proj()->oz('maintenance', 'off', '--json'))['was_on'] ?? null);
	}

	/**
	 * The project this class runs against, which its own setup created.
	 */
	private static function proj(): OZTestProject
	{
		$project = self::$proj;

		self::assertNotNull($project, 'The test project was not created.');

		return $project;
	}

	/**
	 * Makes a GET request to a scope's server, following no redirection.
	 *
	 * @param list<string> $headers request headers, as lines
	 *
	 * @return array{status: int, headers: array<string, string>, body: string, json: array}
	 */
	private static function get(string $scope, string $path, array $headers = ['Accept: application/json']): array
	{
		$ctx = \stream_context_create(['http' => [
			'timeout'         => 10,
			'ignore_errors'   => true,
			'follow_location' => 0,
			'header'          => \implode("\r\n", $headers) . "\r\n",
		]]);

		$url  = 'http://' . self::HOST . ':' . self::$ports[$scope] . $path;
		$body = \file_get_contents($url, false, $ctx);

		self::assertIsString($body, 'No response for ' . $url);

		$status = 0;
		$found  = [];

		foreach (\http_get_last_response_headers() ?? [] as $line) {
			if (\preg_match('~^HTTP/\S+ (\d+)~', $line, $m)) {
				$status = (int) $m[1];
			} elseif (\str_contains($line, ':')) {
				[$name, $value] = \explode(':', $line, 2);

				$found[\strtolower(\trim($name))] = \trim($value);
			}
		}

		$json = \json_decode($body, true);

		return ['status' => $status, 'headers' => $found, 'body' => $body, 'json' => \is_array($json) ? $json : []];
	}

	/**
	 * The routes the project's `RouteFound` listener was told of since the log was emptied.
	 *
	 * @return list<string>
	 */
	private static function foundRoutes(): array
	{
		$lines = \file(self::$found_log, \FILE_IGNORE_NEW_LINES | \FILE_SKIP_EMPTY_LINES);

		return false === $lines ? [] : $lines;
	}

	/**
	 * Signs the administrator in on the API scope, and returns the session cookie to send back.
	 */
	private static function signIn(): string
	{
		$ctx = \stream_context_create(['http' => [
			'method'          => 'POST',
			'timeout'         => 10,
			'ignore_errors'   => true,
			'follow_location' => 0,
			'header'          => "Accept: application/json\r\n" . self::AGENT . "\r\n"
				. "Content-Type: application/x-www-form-urlencoded\r\n",
			'content'         => \http_build_query([
				'auth_user_type'             => 'user',
				'auth_user_identifier_type'  => 'email',
				'auth_user_identifier_value' => self::ADMIN_EMAIL,
				'auth_user_password'         => 'a-strong-password',
			]),
		]]);

		$body = (string) \file_get_contents('http://' . self::HOST . ':' . self::$ports['api'] . '/login', false, $ctx);

		foreach (\http_get_last_response_headers() ?? [] as $line) {
			if (\preg_match('~^Set-Cookie:\s*(OZONE_SID=[^;]+)~i', $line, $m)) {
				return $m[1];
			}
		}

		self::fail('No session after signing in: ' . $body);
	}

	/**
	 * The `data` of a command's `--json` answer.
	 *
	 * @return array<string, mixed>
	 */
	private static function dataOf(Process $proc): array
	{
		if (!$proc->isStarted()) {
			$proc->run();
		}

		$out = \json_decode($proc->getOutput(), true);

		self::assertIsArray($out, $proc->getOutput() . $proc->getErrorOutput());

		/** @var array<string, mixed> $data */
		return \is_array($out['data'] ?? null) ? $out['data'] : [];
	}

	private static function stopServers(): void
	{
		foreach (self::$servers as $server) {
			if ($server->isRunning()) {
				$server->stop(3);
			}
		}

		self::$servers = [];
	}
}
