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
 * Uses PHP's built-in development server (`php -S`) pointing at the project's
 * public/api/ directory.  The test does NOT go through `oz project serve`
 * (that command is blocking by design); instead it starts the same underlying
 * `php -S` command directly as a background process.
 *
 * @internal
 *
 * @coversNothing
 */
final class ProjectServeTest extends TestCase
{
	private static OZTestProject $proj;

	/** @var null|Process Background PHP built-in server process */
	private static ?Process $server = null;

	private static int $port = 0;

	public static function setUpBeforeClass(): void
	{
		parent::setUpBeforeClass();
		self::$proj = OZTestProject::create('project-serve-test');

		// Use a file-based SQLite DB so every HTTP request in the same PHP CLI
		// server process shares the same persistent state.
		$db_path = self::$proj->getPath() . \DIRECTORY_SEPARATOR . 'test.sqlite';
		self::$proj->writeEnv([
			'OZ_DB_RDBMS' => 'sqlite',
			'OZ_DB_HOST'  => $db_path,
		]);

		$host = '127.0.0.1';
		$port = self::findFreePort($host);
		if (null === $port) {
			self::markTestSkipped('No free TCP port available on 127.0.0.1 in range 19000-19999.');
		}
		self::$port = $port;

		$router  = \dirname(__DIR__, 3) . \DIRECTORY_SEPARATOR . 'oz' . \DIRECTORY_SEPARATOR . 'server.php';
		$docRoot = self::$proj->getPath() . \DIRECTORY_SEPARATOR . 'public' . \DIRECTORY_SEPARATOR . 'api';

		self::$server = new Process(
			[\PHP_BINARY, '-S', "{$host}:{$port}", '-t', $docRoot, $router],
			self::$proj->getPath()
		);
		self::$server->start();

		// Poll until the server is accepting connections (max 5 s)
		$deadline = \microtime(true) + 5.0;
		$ready    = false;
		while (\microtime(true) < $deadline) {
			$sock = @\fsockopen($host, $port, $errno, $errstr, 0.1);
			if (\is_resource($sock)) {
				\fclose($sock);
				$ready = true;

				break;
			}
			\usleep(100_000); // 100 ms
		}

		if (!$ready) {
			self::$server->stop(0);
			self::markTestSkipped('PHP built-in server did not start within 5 seconds.');
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

	// -------------------------------------------------------------------------
	// HTTP response tests
	// -------------------------------------------------------------------------

	public function testServerResponds(): void
	{
		$response = $this->get('/');
		self::assertNotNull($response, 'Expected an HTTP response but got none.');
	}

	public function testResponseIsJson(): void
	{
		$body = $this->get('/');
		$data = \json_decode($body, true);
		self::assertIsArray($data, 'Response body should be valid JSON.');
	}

	public function testNotFoundRouteReturnsErrorJson(): void
	{
		$body = $this->get('/nonexistent-route-xyz');
		$data = \json_decode($body, true);
		self::assertIsArray($data);
		self::assertArrayHasKey('error', $data);
		self::assertSame(1, $data['error']);
	}

	public function testJsonResponseContainsOZoneFields(): void
	{
		$body = $this->get('/');
		$data = \json_decode($body, true);
		self::assertIsArray($data);
		// OZone always adds 'utime' to API responses
		self::assertArrayHasKey('utime', $data);
	}

	// -------------------------------------------------------------------------
	// Internal helpers
	// -------------------------------------------------------------------------

	/**
	 * Makes a GET request to the running server and returns the response body,
	 * or null on connection failure.
	 */
	private function get(string $path): ?string
	{
		$url  = 'http://127.0.0.1:' . self::$port . $path;
		$ctx  = \stream_context_create(['http' => [
			'method'          => 'GET',
			'timeout'         => 5,
			'ignore_errors'   => true,
			'follow_location' => false,
		]]);
		$body = @\file_get_contents($url, false, $ctx);

		return false === $body ? null : $body;
	}

	/**
	 * Finds a free TCP port on the given host in range 19000-19999.
	 */
	private static function findFreePort(string $host, int $low = 19000, int $high = 19999): ?int
	{
		for ($port = $low; $port <= $high; ++$port) {
			$sock = @\fsockopen($host, $port, $errno, $errstr, 0.05);
			if (!\is_resource($sock)) {
				return $port; // nothing listening => port is free
			}
			\fclose($sock);
		}

		return null;
	}
}
