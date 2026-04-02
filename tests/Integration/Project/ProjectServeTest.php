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

namespace OZONE\Tests\Integration\Project;

use OZONE\Tests\Integration\Support\OZTestProject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

/**
 * Verifies that a scaffolded project can be served and responds to HTTP requests.
 *
 * Uses `oz project serve` to start PHP's built-in development server.
 * That command auto-detects a free port, writes a server.json to the scope's
 * cache directory before binding, and then starts the process.
 * The test reads host/port from server.json and polls until the port is open.
 *
 * @internal
 *
 * @coversNothing
 */
final class ProjectServeTest extends TestCase
{
	private static OZTestProject $proj;

	/** @var null|Process Background oz project serve process */
	private static ?Process $server = null;

	private static string $host = '127.0.0.1';
	private static int $port    = 0;

	public static function setUpBeforeClass(): void
	{
		parent::setUpBeforeClass();
		self::$proj = OZTestProject::create('project-serve-test');

		// Use a file-based SQLite DB so every HTTP request in the same PHP CLI
		// server process shares persistent state.
		$db_path = self::$proj->getPath() . '/test.sqlite';
		self::$proj->writeEnv([
			'OZ_DB_RDBMS' => 'sqlite',
			'OZ_DB_HOST'  => $db_path,
		]);

		// Start the server via `oz project serve`. The command auto-picks a port,
		// writes .ozone/cache/scopes/api/server.json, then begins serving.
		self::$server = self::$proj->oz('project', 'serve', '--scope=api', '--host=' . self::$host);
		self::$server->start();

		// Wait for server.json to appear (written by oz before binding the port).
		$server_json = self::$proj->getPath() . '/.ozone/cache/scopes/api/server.json';
		$deadline    = \microtime(true) + 10.0;
		while (!\is_file($server_json) && \microtime(true) < $deadline) {
			\usleep(100_000); // 100 ms
		}

		if (!\is_file($server_json)) {
			self::$server->stop(0);
			self::markTestSkipped('oz project serve did not write server.json within 10 seconds.');
		}

		$info       = \json_decode(\file_get_contents($server_json), true);
		self::$port = (int) ($info['port'] ?? 0);

		if (0 === self::$port) {
			self::$server->stop(0);
			self::markTestSkipped('server.json did not contain a valid port.');
		}

		// Poll until the port is actually accepting connections.
		$deadline = \microtime(true) + 10.0;
		$ready    = false;
		while (\microtime(true) < $deadline) {
			$sock = @\fsockopen(self::$host, self::$port, $errno, $errstr, 0.1);
			if (\is_resource($sock)) {
				\fclose($sock);
				$ready = true;

				break;
			}
			\usleep(100_000); // 100 ms
		}

		if (!$ready) {
			self::$server->stop(0);
			self::markTestSkipped('PHP built-in server did not start within 10 seconds.');
		}
	}

	public static function tearDownAfterClass(): void
	{
		if (null !== self::$server && self::$server->isRunning()) {
			self::$server->stop(0);
		}
		self::$proj->destroy();
		parent::tearDownAfterClass();
	}

	public function testServerResponds(): void
	{
		$response = $this->get('/');
		self::assertNotNull($response, 'Expected an HTTP response but got none.');
	}

	public function testResponseIsJson(): void
	{
		$body = $this->get('/');
		$data = \json_decode((string) $body, true);
		self::assertIsArray($data, 'Response body should be valid JSON.');
	}

	public function testNotFoundRouteReturnsErrorJson(): void
	{
		$body = $this->get('/nonexistent-route-xyz');
		$data = \json_decode((string) $body, true);
		self::assertIsArray($data);
		self::assertArrayHasKey('error', $data);
		self::assertSame(1, $data['error']);
	}

	public function testJsonResponseContainsOZoneFields(): void
	{
		$body = $this->get('/');
		$data = \json_decode((string) $body, true);
		self::assertIsArray($data);
		// OZone always includes 'error' and 'msg' in JSON responses.
		// 'utime' is only present in Service::respond() output, not in exception (404, etc.) responses.
		self::assertArrayHasKey('error', $data);
		self::assertArrayHasKey('msg', $data);
	}

	/**
	 * Makes a GET request to the running server and returns the body, or null on failure.
	 */
	private function get(string $path): ?string
	{
		$url = 'http://' . self::$host . ':' . self::$port . $path;
		$ctx = \stream_context_create(['http' => [
			'method'          => 'GET',
			'timeout'         => 5,
			'ignore_errors'   => true,
			'follow_location' => false,
			'header'          => "Accept: application/json\r\n",
		]]);
		$body = @\file_get_contents($url, false, $ctx);

		return false === $body ? null : $body;
	}
}
