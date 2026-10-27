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
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Process\Process;

/**
 * Two requests of one session overlapping: each writes the keys it changed, so the one that answers
 * last does not undo what the other wrote.
 *
 * The requests are real: a route that takes its time answering holds the session it read while
 * another request runs, which is the window a page with subresources served by OZone opens on every
 * navigation away from it.
 *
 * @internal
 */
#[CoversNothing]
final class SessionConcurrencyTest extends TestCase
{
	private const string DB_FILE = 'session_concurrency_test.sqlite';

	private static ?OZTestProject $project = null;

	private static ?Process $server = null;

	private static int $port = 0;

	private static string $host = '127.0.0.1';

	public static function setUpBeforeClass(): void
	{
		parent::setUpBeforeClass();

		$project = OZTestProject::create('session-concurrency', fresh: true);

		$project->writeEnv([
			'OZ_DB_RDBMS' => 'sqlite',
			'OZ_DB_HOST'  => $project->getPath() . \DIRECTORY_SEPARATOR . self::DB_FILE,
		]);

		$ns = $project->getNamespace();
		$project->writeFileFromStub('SessionConcurrencyRoutesProvider', 'app/SessionConcurrencyRoutesProvider.php', [
			'namespace' => $ns,
		]);
		$project->setSetting('oz.routes.api', "{$ns}\\SessionConcurrencyRoutesProvider", true);

		$project->oz('db', 'build', '--build-all', '--class-only')->mustRun();
		$project->oz('migrations', 'create', '--force', '--label=initial')->mustRun();
		$project->oz('migrations', 'run', '--skip-backup')->mustRun();

		try {
			// Several workers: the built-in server is otherwise one process answering one request.
			[$server, , $port] = $project->startServer('api', self::$host, 4);
		} catch (RuntimeException $e) {
			$project->destroy();
			self::fail($e->getMessage());
		}

		self::$project = $project;
		self::$server  = $server;
		self::$port    = $port;
	}

	public static function tearDownAfterClass(): void
	{
		if (self::$server?->isRunning()) {
			self::$server->stop(3);
		}

		self::$project?->destroy();

		self::$server  = null;
		self::$project = null;

		parent::tearDownAfterClass();
	}

	/**
	 * The overlapping request only reads the session.
	 */
	public function testAReadOnlyOverlapDoesNotUndoARemoval(): void
	{
		$sid = $this->openSession();

		$slow = $this->send($sid, '/test-session-slow-read');

		// The slow request has loaded the session and is sleeping.
		\usleep(500_000);

		[$status, $body] = $this->request($sid, '/test-session-remove');
		self::assertSame(200, $status, $body);

		[$status, $body] = $this->finish($slow);
		self::assertSame(200, $status, $body);
		self::assertSame('u1', $this->data($body)['seen'] ?? null, 'the slow request did not see the key');

		self::assertNull($this->storedData($sid)['uid'] ?? null, 'a read-only request undid the removal');
	}

	/**
	 * The overlapping request writes a key of its own.
	 */
	public function testAConcurrentWriteDoesNotUndoARemoval(): void
	{
		$sid = $this->openSession();

		$slow = $this->send($sid, '/test-session-slow-write');

		\usleep(500_000);

		[$status, $body] = $this->request($sid, '/test-session-remove');
		self::assertSame(200, $status, $body);

		[$status, $body] = $this->finish($slow);
		self::assertSame(200, $status, $body);

		$data = $this->storedData($sid);

		self::assertSame('fr', $data['lang'] ?? null, 'the slow request lost its own write');
		self::assertNull($data['uid'] ?? null, 'the removal was undone by the concurrent write');
	}

	/**
	 * The same overlap, against a sign-out that rotates the session ID.
	 */
	public function testAConcurrentWriteCannotUndoARotatingSignOut(): void
	{
		$sid = $this->openSession();

		$slow = $this->send($sid, '/test-session-slow-write');

		\usleep(500_000);

		[$status, $body, $headers] = $this->request($sid, '/test-session-remove-rotate');
		self::assertSame(200, $status, $body);

		$new_sid = self::cookies($headers)['OZONE_SID'] ?? null;
		self::assertNotEmpty($new_sid, $body);
		self::assertNotSame($sid, $new_sid);

		[$status, $body] = $this->finish($slow);
		self::assertSame(200, $status, $body);

		// Neither the session the client is given nor the one it held carries the key.
		self::assertNull($this->storedData($new_sid)['uid'] ?? null, 'the rotated session carries the key');
		self::assertNull($this->storedData($sid)['uid'] ?? null, 'the old session still carries the key');
	}

	/**
	 * A request that only reads the session leaves its data alone and slides its expiry.
	 */
	public function testAReadOnlyRequestDoesNotChangeTheData(): void
	{
		$sid    = $this->openSession();
		$before = self::sessionRow($sid);

		// time() has a second's resolution, and the columns are written from it.
		\usleep(1_100_000);

		[$status, $body] = $this->request($sid, '/test-session-slow-read');
		self::assertSame(200, $status, $body);

		$after = self::sessionRow($sid);

		self::assertSame($before['session_data'], $after['session_data'], 'the data changed');
		self::assertGreaterThan(
			(int) $before['session_updated_at'],
			(int) $after['session_updated_at'],
			'a read-only request did not write the row'
		);
	}

	/**
	 * Opens a session holding `uid` and returns its ID.
	 */
	private function openSession(): string
	{
		[$status, $body, $headers] = $this->request(null, '/test-session-set');

		self::assertSame(200, $status, $body);

		$sid = self::cookies($headers)['OZONE_SID'] ?? null;

		self::assertNotEmpty($sid, $body);
		self::assertSame('u1', $this->storedData($sid)['uid'] ?? null, 'the session was not stored');

		return $sid;
	}

	/**
	 * What the session store holds, as the server answers it.
	 */
	private function storedData(string $sid): array
	{
		[$status, $body] = $this->request($sid, '/test-session-dump');

		self::assertSame(200, $status, $body);

		$data = $this->data($body)['data'] ?? [];

		return \is_array($data) ? $data : [];
	}

	private function data(string $body): array
	{
		$decoded = \json_decode($body, true);

		return \is_array($decoded['data'] ?? null) ? $decoded['data'] : [];
	}

	/**
	 * A request, start to finish.
	 *
	 * @return array{0: int, 1: string, 2: list<string>}
	 */
	private function request(?string $sid, string $path): array
	{
		return $this->finish($this->send($sid, $path));
	}

	/**
	 * Sends a request and returns its socket, without reading the answer.
	 *
	 * @return resource
	 */
	private function send(?string $sid, string $path)
	{
		$socket = @\fsockopen(self::$host, self::$port, $errno, $errstr, 5.0);

		if (!\is_resource($socket)) {
			self::fail(\sprintf('Unable to reach the server: %s (%d)', $errstr, $errno));
		}

		$request = \sprintf("GET %s HTTP/1.1\r\n", $path)
			. \sprintf("Host: %s:%d\r\n", self::$host, self::$port)
			. "Accept: application/json\r\n"
			. "User-Agent: OZoneTest/1.0\r\n"
			. (null === $sid ? '' : \sprintf("Cookie: OZONE_SID=%s\r\n", $sid))
			. "Connection: close\r\n\r\n";

		\fwrite($socket, $request);

		return $socket;
	}

	/**
	 * Reads the answer of a sent request.
	 *
	 * @param resource $socket
	 *
	 * @return array{0: int, 1: string, 2: list<string>}
	 */
	private function finish($socket): array
	{
		\stream_set_timeout($socket, 15);

		$raw = '';

		while (!\feof($socket)) {
			$chunk = \fread($socket, 8192);

			if (false === $chunk || '' === $chunk) {
				break;
			}

			$raw .= $chunk;
		}

		\fclose($socket);

		$parts   = \explode("\r\n\r\n", $raw, 2);
		$headers = \explode("\r\n", $parts[0]);
		$body    = $parts[1] ?? '';
		$status  = 0;

		if (\preg_match('/HTTP\/\d+(?:\.\d+)? (\d+)/', $headers[0] ?? '', $m)) {
			$status = (int) $m[1];
		}

		return [$status, $body, $headers];
	}

	/**
	 * A session's row, as the database holds it.
	 *
	 * @return array<string, null|string>
	 */
	private static function sessionRow(string $sid): array
	{
		$pdo = new PDO(
			'sqlite:' . self::$project->getPath() . \DIRECTORY_SEPARATOR . self::DB_FILE,
			null,
			null,
			[PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
		);

		// Generated projects use a random table prefix.
		$table = $pdo->query("SELECT name FROM sqlite_master WHERE type = 'table' AND name LIKE '%oz_sessions'")
			->fetchColumn();

		self::assertIsString($table);

		$statement = $pdo->prepare(\sprintf('SELECT * FROM "%s" WHERE session_id = ?', $table));
		$statement->execute([$sid]);

		$row = $statement->fetch(PDO::FETCH_ASSOC);

		self::assertIsArray($row, 'the session row is gone');

		return $row;
	}

	/**
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
}
