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

namespace OZONE\Tests\Integration\Auth;

use OZONE\Core\Testing\OZTestProject;
use PDO;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Process\Process;

/**
 * Tests the same source session protection (`OZ_SESSION_HIJACKING_FORCE_SAME_SOURCE`) against real
 * served projects: a session cookie is replayed from another source and the answer must change.
 *
 * The source is the User-Agent (`OZ_SESSION_SOURCE_KEY`), the only one a test driving a local server
 * can change: every request comes from 127.0.0.1.
 *
 * Two projects, since the setting is read per project: one forcing the same source, one not.
 *
 * @internal
 *
 * @coversNothing
 */
final class SessionHijackingTest extends TestCase
{
	private const AGENT_OPENED  = 'User-Agent: OZoneTest/1.0 (the browser that opened the session)';
	private const AGENT_STOLEN  = 'User-Agent: OZoneTest/1.0 (another browser, with the cookie)';
	private const DB_FILE       = 'session_hijacking_test.sqlite';
	private const USER_EMAIL    = 'jane.doe@example.com';
	private const USER_PASSWORD = 'Jane_Pass_42';

	/** @var array<string, OZTestProject> projects by name */
	private static array $projects = [];

	/** @var array<string, Process> their servers */
	private static array $servers = [];

	/** @var array<string, int> their ports */
	private static array $ports = [];

	private static string $host = '127.0.0.1';

	public static function setUpBeforeClass(): void
	{
		parent::setUpBeforeClass();

		// 1 is OZone's default, passed anyway: the test says which behaviour it asks for.
		self::startProject('session-hijacking', 1);
		self::startProject('session-hijacking-off', 0);
	}

	public static function tearDownAfterClass(): void
	{
		foreach (self::$servers as $server) {
			if ($server->isRunning()) {
				$server->stop(3);
			}
		}

		foreach (self::$projects as $project) {
			$project->destroy();
		}

		self::$servers  = [];
		self::$projects = [];
		self::$ports    = [];

		parent::tearDownAfterClass();
	}

	/**
	 * A signed in session replayed from another source is refused, and left alone: its own source
	 * goes on using it.
	 */
	public function testAnAuthenticatedSessionIsRefusedWhenItsSourceChanges(): void
	{
		$project = 'session-hijacking';
		$sid     = $this->login($project);

		// The source that opened it reaches the route that requires a user.
		[$status, $body] = $this->request($project, 'GET', '/test-protected', [
			'Cookie: OZONE_SID=' . $sid,
			self::AGENT_OPENED,
		]);

		self::assertSame(200, $status, $body);
		self::assertSame('ok', \json_decode($body, true)['data']['secret'] ?? null, $body);

		// The same cookie, from another source.
		[$status, $body] = $this->request($project, 'GET', '/test-protected', [
			'Cookie: OZONE_SID=' . $sid,
			self::AGENT_STOLEN,
		]);
		$data = \json_decode($body, true);

		self::assertSame(403, $status, $body);
		self::assertSame(1, $data['error'] ?? null, $body);
		self::assertSame('OZ_SESSION_HIJACKING_DETECTED', $data['msg'] ?? null, $body);

		// The user was not logged out by someone else's request.
		[$status, $body] = $this->request($project, 'GET', '/test-protected', [
			'Cookie: OZONE_SID=' . $sid,
			self::AGENT_OPENED,
		]);

		self::assertSame(200, $status, $body);
		self::assertSame('ok', \json_decode($body, true)['data']['secret'] ?? null, $body);
	}

	/**
	 * A refused request leaves the session row alone: it may not keep a session it cannot use from
	 * expiring, while the source that owns it still refreshes it.
	 */
	public function testARefusedRequestDoesNotRefreshTheSession(): void
	{
		$project = 'session-hijacking';
		$sid     = $this->login($project);

		// An expiry of its own, far from the one a save would write (now plus the session lifetime).
		$expire_at = \time() + 600;

		self::touchSession($project, $sid, $expire_at);

		[$status, $body] = $this->request($project, 'GET', '/test-protected', [
			'Cookie: OZONE_SID=' . $sid,
			self::AGENT_STOLEN,
		]);

		self::assertSame(403, $status, $body);
		self::assertSame($expire_at, self::sessionExpiry($project, $sid), 'The refused request saved the session.');

		// The source that opened it does refresh it, so the assertion above is not a save that never runs.
		[$status, $body] = $this->request($project, 'GET', '/test-protected', [
			'Cookie: OZONE_SID=' . $sid,
			self::AGENT_OPENED,
		]);

		self::assertSame(200, $status, $body);
		self::assertGreaterThan($expire_at, self::sessionExpiry($project, $sid));
	}

	/**
	 * A session with no user attached has nothing to steal: the request is served, with a session of
	 * its own.
	 */
	public function testAnAnonymousSessionIsRestartedWhenItsSourceChanges(): void
	{
		$project = 'session-hijacking';

		// The route writes to the session store, so the session is kept and its cookie sent.
		[$status, $body, $headers] = $this->request($project, 'GET', '/test-session', [self::AGENT_OPENED]);

		self::assertSame(200, $status, $body);

		$sid = self::cookies($headers)['OZONE_SID'] ?? null;

		self::assertNotEmpty($sid, $body);

		[$status, $body, $headers] = $this->request($project, 'GET', '/test-session', [
			'Cookie: OZONE_SID=' . $sid,
			self::AGENT_STOLEN,
		]);

		self::assertSame(200, $status, $body);

		$new_sid = self::cookies($headers)['OZONE_SID'] ?? null;

		self::assertNotEmpty($new_sid, $body);
		self::assertNotSame($sid, $new_sid, 'The session should have been restarted.');
	}

	/**
	 * With the protection off, the source a session was opened from is not compared: a changed IP or
	 * browser costs nothing.
	 */
	public function testTheSourceIsNotComparedWhenTheProtectionIsOff(): void
	{
		$project = 'session-hijacking-off';
		$sid     = $this->login($project);

		[$status, $body, $headers] = $this->request($project, 'GET', '/test-protected', [
			'Cookie: OZONE_SID=' . $sid,
			self::AGENT_STOLEN,
		]);

		self::assertSame(200, $status, $body);
		self::assertSame('ok', \json_decode($body, true)['data']['secret'] ?? null, $body);

		// The same session, not a restarted one.
		$sent = self::cookies($headers)['OZONE_SID'] ?? $sid;

		self::assertSame($sid, $sent, $body);
	}

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	/**
	 * Creates a project serving the test routes, with a user to sign in.
	 */
	private static function startProject(string $name, int $force_same_source): void
	{
		$project = OZTestProject::create($name, fresh: true);

		// File-based SQLite so the schema persists across separate PHP CLI processes.
		$project->writeEnv([
			'OZ_DB_RDBMS' => 'sqlite',
			'OZ_DB_HOST'  => $project->getPath() . \DIRECTORY_SEPARATOR . self::DB_FILE,
		]);

		$ns = $project->getNamespace();
		$project->writeFileFromStub('TestRoutesProvider', 'app/TestRoutesProvider.php', ['namespace' => $ns]);
		$project->setSetting('oz.routes.api', "{$ns}\\TestRoutesProvider", true);
		$project->setSetting('oz.sessions', 'OZ_SESSION_SOURCE_KEY', 'user_agent');
		$project->setSetting('oz.sessions', 'OZ_SESSION_HIJACKING_FORCE_SAME_SOURCE', $force_same_source);

		$project->oz('db', 'build', '--build-all', '--class-only')->mustRun();
		$project->oz('migrations', 'create', '--force', '--label=initial')->mustRun();
		$project->oz('migrations', 'run', '--skip-backup')->mustRun();

		self::seedCountry($project, 'BJ');

		$project->oz(
			'users',
			'add',
			'--user_civility=Mrs',
			'--user_display_name=Jane Doe',
			'--user_first_name=Jane',
			'--user_last_name=Doe',
			'--user_email=' . self::USER_EMAIL,
			'--user_gender=Female',
			'--user_birth_date=1990-05-17',
			'--user_pass=' . self::USER_PASSWORD,
			'--user_cc2=BJ',
		)->mustRun();

		try {
			[$server, , $port] = $project->startServer('api', self::$host);
		} catch (RuntimeException $e) {
			$project->destroy();
			self::fail($e->getMessage());
		}

		self::$projects[$name] = $project;
		self::$servers[$name]  = $server;
		self::$ports[$name]    = $port;
	}

	/**
	 * Signs the user in from {@see self::AGENT_OPENED} and returns the session ID it was given.
	 */
	private function login(string $project): string
	{
		[$status, $body, $headers] = $this->request($project, 'POST', '/login', [self::AGENT_OPENED], [
			'auth_user_type'             => 'user',
			'auth_user_identifier_type'  => 'email',
			'auth_user_identifier_value' => self::USER_EMAIL,
			'auth_user_password'         => self::USER_PASSWORD,
		]);

		self::assertSame(200, $status, $body);
		self::assertSame('OZ_USER_SIGN_IN_DONE', \json_decode($body, true)['msg'] ?? null, $body);

		$sid = self::cookies($headers)['OZONE_SID'] ?? null;

		self::assertNotEmpty($sid, $body);

		return $sid;
	}

	/**
	 * Sends a request to a project's server.
	 *
	 * @param list<string>          $headers
	 * @param array<string, string> $fields
	 *
	 * @return array{0: int, 1: string, 2: list<string>}
	 */
	private function request(
		string $project,
		string $method,
		string $path,
		array $headers = [],
		array $fields = []
	): array {
		$url = 'http://' . self::$host . ':' . self::$ports[$project] . $path;

		$opts = [
			'method'          => $method,
			'timeout'         => 10,
			'ignore_errors'   => true,
			'follow_location' => false,
			'header'          => "Accept: application/json\r\n"
				. \implode('', \array_map(static fn (string $h): string => $h . "\r\n", $headers)),
		];

		if ([] !== $fields) {
			$opts['content'] = \http_build_query($fields);
			$opts['header'] .= "Content-Type: application/x-www-form-urlencoded\r\n";
		}

		$ctx  = \stream_context_create(['http' => $opts]);
		$body = @\file_get_contents($url, false, $ctx);

		if (false === $body) {
			return [0, '', []];
		}

		$status = 0;

		if (
			!empty($http_response_header[0])
			&& \preg_match('/HTTP\/\d+(?:\.\d+)? (\d+)/', $http_response_header[0], $m)
		) {
			$status = (int) $m[1];
		}

		return [$status, $body, $http_response_header ?? []];
	}

	/**
	 * The cookies a response sets, by name.
	 *
	 * @param list<string> $headers
	 *
	 * @return array<string, string>
	 */
	private static function cookies(array $headers): array
	{
		$cookies = [];

		foreach ($headers as $header) {
			if (\preg_match('~^Set-Cookie:\s*([^=;]+)=([^;]*)~i', $header, $m)) {
				$cookies[\urldecode($m[1])] = \urldecode($m[2]);
			}
		}

		return $cookies;
	}

	/**
	 * Gives a session row an expiry of its own, to tell a save from the absence of one.
	 */
	private static function touchSession(string $project, string $sid, int $expire_at): void
	{
		$pdo   = self::pdo($project);
		$table = self::sessionsTable($pdo);

		$pdo->prepare(\sprintf('UPDATE "%s" SET session_expire_at = ? WHERE session_id = ?', $table))
			->execute([(string) $expire_at, $sid]);
	}

	/**
	 * The expiry a session row holds.
	 */
	private static function sessionExpiry(string $project, string $sid): int
	{
		$pdo   = self::pdo($project);
		$table = self::sessionsTable($pdo);

		$statement = $pdo->prepare(\sprintf('SELECT session_expire_at FROM "%s" WHERE session_id = ?', $table));
		$statement->execute([$sid]);

		$expire_at = $statement->fetchColumn();

		self::assertNotFalse($expire_at, 'The session row is gone.');

		return (int) $expire_at;
	}

	/**
	 * The sessions table, whose prefix a generated project draws at random.
	 */
	private static function sessionsTable(PDO $pdo): string
	{
		$table = $pdo->query("SELECT name FROM sqlite_master WHERE type = 'table' AND name LIKE '%oz_sessions'")
			->fetchColumn();

		self::assertIsString($table);

		return $table;
	}

	/**
	 * A connection to a project's database.
	 */
	private static function pdo(string $project): PDO
	{
		return new PDO(
			'sqlite:' . self::$projects[$project]->getPath() . \DIRECTORY_SEPARATOR . self::DB_FILE,
			null,
			null,
			[PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
		);
	}

	/**
	 * Seeds a country, which `oz users add` requires.
	 */
	private static function seedCountry(OZTestProject $project, string $cc2): void
	{
		$pdo = new PDO(
			'sqlite:' . $project->getPath() . \DIRECTORY_SEPARATOR . self::DB_FILE,
			null,
			null,
			[PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
		);

		// Generated projects use a random table prefix.
		$table = $pdo->query("SELECT name FROM sqlite_master WHERE type = 'table' AND name LIKE '%oz_countries'")
			->fetchColumn();

		self::assertIsString($table);

		$now = (string) \time();

		$pdo->prepare(\sprintf(
			'INSERT INTO "%s" (country_cc2, country_calling_code, country_name, country_name_real, country_data,'
				. ' country_is_valid, country_created_at, country_updated_at, country_deleted, country_deleted_at)'
				. ' VALUES (?, ?, ?, ?, ?, 1, ?, ?, 0, NULL)',
			$table
		))->execute([$cc2, '+229', 'Benin', 'Benin', '{}', $now, $now]);
	}
}
