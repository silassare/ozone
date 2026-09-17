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

namespace OZONE\Tests\Integration\Http;

use OZONE\Core\Testing\OZTestProject;
use PDO;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Process\Process;

/**
 * Tests HTTP routing: custom routes, form validation, auth guards, and built-in
 * auth endpoints -- all exercised against a real served OZone project.
 *
 * A single shared project is created once (setUpBeforeClass) with:
 *  - A file-based SQLite DB so each server request shares persistent state.
 *  - DB schema installed (migrations run).
 *  - A custom TestRoutesProvider injected into oz.routes.api.
 *  - The PHP built-in server running for the 'api' scope.
 *
 * @internal
 *
 * @coversNothing
 */
final class HttpRoutingTest extends TestCase
{
	private static OZTestProject $proj;

	/** @var null|Process Background oz project serve process */
	private static ?Process $server = null;

	private static string $host = '127.0.0.1';
	private static int $port    = 0;

	public static function setUpBeforeClass(): void
	{
		parent::setUpBeforeClass();

		$proj = OZTestProject::create('http-routing', fresh: true);

		// File-based SQLite so the schema persists across separate PHP CLI processes.
		$db_path = $proj->getPath() . '/http_routing_test.sqlite';
		$proj->writeEnv([
			'OZ_DB_RDBMS' => 'sqlite',
			'OZ_DB_HOST'  => $db_path,
		]);

		// Inject the custom test route provider.
		$ns = $proj->getNamespace(); // 'HttpRouting'
		$proj->writeFileFromStub('TestRoutesProvider', 'app/TestRoutesProvider.php', [
			'namespace' => $ns,
		]);
		$proj->setSetting('oz.routes.api', "{$ns}\\TestRoutesProvider", true);

		// Build ORM classes and install the schema.
		$proj->oz('db', 'build', '--build-all', '--class-only')->mustRun();
		$proj->oz('migrations', 'create', '--force', '--label=initial')->mustRun();
		$proj->oz('migrations', 'run', '--skip-backup')->mustRun();

		try {
			[$server, $host, $port] = $proj->startServer('api', self::$host);
		} catch (RuntimeException $e) {
			$proj->destroy();
			self::fail($e->getMessage());
		}

		self::$proj   = $proj;
		self::$server = $server;
		self::$port   = $port;
	}

	public static function tearDownAfterClass(): void
	{
		if (null !== self::$server && self::$server->isRunning()) {
			self::$server->stop(3);
		}
		if (isset(self::$proj)) {
			self::$proj->destroy();
		}
		parent::tearDownAfterClass();
	}

	// -------------------------------------------------------------------------
	// Custom route tests
	// -------------------------------------------------------------------------

	public function testCustomGetRouteReturnsSuccess(): void
	{
		[$status, $body] = $this->request('GET', '/test-ping');
		$data            = \json_decode($body, true);

		self::assertSame(200, $status);
		self::assertIsArray($data);
		self::assertSame(0, $data['error']);
		self::assertTrue($data['data']['pong'] ?? null);
	}

	public function testFormValidationRejectsEmptyBody(): void
	{
		[$status, $body] = $this->request('POST', '/test-echo');
		$data            = \json_decode($body, true);

		self::assertIsArray($data);
		self::assertSame(1, $data['error'], 'Missing required field should yield error response.');
	}

	public function testFormValidationAcceptsValidInput(): void
	{
		[$status, $body] = $this->request('POST', '/test-echo', ['message' => 'hello world']);
		$data            = \json_decode($body, true);

		self::assertIsArray($data);
		self::assertSame(0, $data['error']);
		self::assertSame('hello world', $data['data']['message'] ?? null);
	}

	public function testAuthGuardRejectsUnauthenticated(): void
	{
		[$status, $body] = $this->request('GET', '/test-protected');
		$data            = \json_decode($body, true);

		self::assertIsArray($data);
		self::assertSame(1, $data['error'], 'Unauthenticated request to guarded route should fail.');
	}

	// -------------------------------------------------------------------------
	// Built-in routing behaviour
	// -------------------------------------------------------------------------

	public function testUnknownRouteReturnsNotFoundError(): void
	{
		[$status, $body] = $this->request('GET', '/this-route-does-not-exist-xyz');
		$data            = \json_decode($body, true);

		self::assertIsArray($data);
		self::assertSame(1, $data['error']);
	}

	public function testWrongMethodReturnsError(): void
	{
		// /test-echo is POST-only; a GET should return a method-not-allowed error.
		[$status, $body] = $this->request('GET', '/test-echo');
		$data            = \json_decode($body, true);

		self::assertIsArray($data);
		self::assertSame(1, $data['error']);
	}

	// -------------------------------------------------------------------------
	// Built-in auth endpoints
	// -------------------------------------------------------------------------

	public function testLogoutAlwaysSucceeds(): void
	{
		// POST /logout works even when no session exists.
		[$status, $body] = $this->request('POST', '/logout');
		$data            = \json_decode($body, true);

		self::assertIsArray($data);
		self::assertSame(0, $data['error']);
	}

	public function testSessionPostNeedsTheCsrfTokenHandedInTheCookie(): void
	{
		// An anonymous request that uses no session gets none: no row, no cookie.
		[, , $headers] = $this->request('GET', '/test-ping');

		self::assertSame([], self::cookies($headers));

		// A request that uses the session starts one: the session cookie comes with the session's
		// CSRF token, in a cookie scripts can read.
		[, , $headers] = $this->request('GET', '/test-session');
		$cookies       = self::cookies($headers);

		self::assertArrayHasKey('OZONE_SID', $cookies);
		self::assertArrayHasKey('XSRF-TOKEN', $cookies);

		$session = 'Cookie: OZONE_SID=' . $cookies['OZONE_SID'];

		// An unsafe request riding the session cookie without the token is rejected.
		[$status, $body] = $this->request('POST', '/logout', [], [$session]);
		$data            = \json_decode($body, true);

		self::assertSame(403, $status, $body);
		self::assertSame(1, $data['error'] ?? null);

		// Sending the token back, as axios or Angular would, passes.
		[$status, $body] = $this->request('POST', '/logout', [], [
			$session,
			'X-XSRF-TOKEN: ' . $cookies['XSRF-TOKEN'],
		]);

		self::assertSame(200, $status, $body);
	}

	public function testLoginWithMissingFieldsFailsValidation(): void
	{
		// POST /login with no body: form validation fails before any DB lookup.
		[$status, $body] = $this->request('POST', '/login');
		$data            = \json_decode($body, true);

		self::assertIsArray($data);
		self::assertSame(1, $data['error']);
	}

	public function testLoginWithWrongCredentialsReturnsError(): void
	{
		// Provide all required form fields but with non-existent user.
		[$status, $body] = $this->request('POST', '/login', [
			'auth_user_type'             => 'user',
			'auth_user_identifier_value' => 'nobody@example.com',
			'auth_user_identifier_type'  => 'email',
			'auth_user_password'         => 'Wrong_Password1!',
		]);
		$data = \json_decode($body, true);

		self::assertIsArray($data);
		self::assertSame(1, $data['error'], 'Login with non-existent user should fail.');
		self::assertSame('OZ_AUTH_INVALID_CREDENTIALS', $data['msg'] ?? null, $body);
	}

	public function testLoginWithRightCredentialsOpensAnAuthenticatedSession(): void
	{
		self::seedCountry('BJ');

		self::$proj->oz(
			'users',
			'add',
			'--user_civility=Mrs',
			'--user_display_name=Jane Doe',
			'--user_first_name=Jane',
			'--user_last_name=Doe',
			'--user_email=jane.doe@example.com',
			'--user_gender=Female',
			'--user_birth_date=1990-05-17',
			'--user_pass=Jane_Pass_42',
			'--user_cc2=BJ',
		)->mustRun();

		[$status, $body, $headers] = $this->request('POST', '/login', [
			'auth_user_type'             => 'user',
			'auth_user_identifier_type'  => 'email',
			'auth_user_identifier_value' => 'jane.doe@example.com',
			'auth_user_password'         => 'Jane_Pass_42',
		]);
		$data = \json_decode($body, true);

		self::assertSame(200, $status, $body);
		self::assertSame(0, $data['error'] ?? null, $body);
		self::assertSame('OZ_USER_SIGN_IN_DONE', $data['msg'] ?? null, $body);

		$cookies = self::cookies($headers);

		self::assertArrayHasKey('OZONE_SID', $cookies);

		// The session opened by the login reaches a route that requires a user.
		[$status, $body] = $this->request('GET', '/test-protected', [], [
			'Cookie: OZONE_SID=' . $cookies['OZONE_SID'],
		]);
		$data = \json_decode($body, true);

		self::assertSame(200, $status, $body);
		self::assertSame('ok', $data['data']['secret'] ?? null, $body);
	}

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	/**
	 * Makes an HTTP request to the running test server.
	 *
	 * @param string               $method  HTTP verb (GET, POST, ...)
	 * @param string               $path    URL path (e.g. '/test-ping')
	 * @param array<string,string> $fields  form fields for POST requests
	 * @param list<string>         $headers extra request headers ("Name: value")
	 *
	 * @return array{0: int, 1: string, 2: list<string>} [status_code, body, response_headers]
	 */
	private function request(string $method, string $path, array $fields = [], array $headers = []): array
	{
		$url = 'http://' . self::$host . ':' . self::$port . $path;

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

		// Extract HTTP status code from $http_response_header.
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
	 * The cookies set by a response.
	 *
	 * @param list<string> $headers
	 *
	 * @return array<string, string> cookie name => decoded value
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
	 * Adds an allowed country: a user needs one (`user_cc2`) and no command creates countries.
	 */
	private static function seedCountry(string $cc2): void
	{
		$pdo = new PDO(
			'sqlite:' . self::$proj->getPath() . '/http_routing_test.sqlite',
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
