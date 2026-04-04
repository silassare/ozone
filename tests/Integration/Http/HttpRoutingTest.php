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

use OZONE\Tests\Integration\Support\OZTestProject;
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
			self::markTestSkipped($e->getMessage());
		}

		self::$proj   = $proj;
		self::$server = $server;
		self::$port   = $port;
	}

	public static function tearDownAfterClass(): void
	{
		if (null !== self::$server && self::$server->isRunning()) {
			self::$server->stop(0);
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
	}

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	/**
	 * Makes an HTTP request to the running test server.
	 *
	 * @param string               $method HTTP verb (GET, POST, ...)
	 * @param string               $path   URL path (e.g. '/test-ping')
	 * @param array<string,string> $fields form fields for POST requests
	 *
	 * @return array{0: int, 1: string} [status_code, body]
	 */
	private function request(string $method, string $path, array $fields = []): array
	{
		$url = 'http://' . self::$host . ':' . self::$port . $path;

		$opts = [
			'method'          => $method,
			'timeout'         => 10,
			'ignore_errors'   => true,
			'follow_location' => false,
			'header'          => "Accept: application/json\r\n",
		];

		if ([] !== $fields) {
			$opts['content'] = \http_build_query($fields);
			$opts['header'] .= "Content-Type: application/x-www-form-urlencoded\r\n";
		}

		$ctx  = \stream_context_create(['http' => $opts]);
		$body = @\file_get_contents($url, false, $ctx);

		if (false === $body) {
			return [0, ''];
		}

		// Extract HTTP status code from $http_response_header.
		$status = 0;
		if (
			!empty($http_response_header[0])
			&& \preg_match('/HTTP\/\d+(?:\.\d+)? (\d+)/', $http_response_header[0], $m)
		) {
			$status = (int) $m[1];
		}

		return [$status, $body];
	}
}
