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

namespace OZONE\Tests\Integration\Forms;

use OZONE\Tests\Integration\Support\OZTestProject;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Process\Process;

/**
 * Integration tests for the session abandonment / expiry hook (Plan D).
 *
 * Verifies:
 *  - onAbandon() is called when a session entry expires passively (TTL elapses
 *    and the cache garbage collector fires on the next FinishHook).
 *  - onAbandon() is NOT called when a session is explicitly cancelled (cancel
 *    calls delete() directly, bypassing the TTL path).
 *
 * @internal
 *
 * @coversNothing
 */
final class ResumableFormAbandonTest extends TestCase
{
	private static OZTestProject $proj;

	/** @var null|Process background oz project serve process */
	private static ?Process $server = null;

	private static string $host = '127.0.0.1';
	private static int $port    = 0;

	/** Path to the flag file written by TestFormAbandonProvider::onAbandon(). */
	private static string $flagFile;

	public static function setUpBeforeClass(): void
	{
		parent::setUpBeforeClass();

		self::$flagFile = \sys_get_temp_dir() . '/oz_form_abandon_flag_' . \getmypid();

		// Remove any leftover flag from a previous run.
		if (\is_file(self::$flagFile)) {
			\unlink(self::$flagFile);
		}

		$proj    = OZTestProject::create('resumable-abandon', fresh: true);
		$db_path = $proj->getPath() . '/abandon_test.sqlite';
		$proj->writeEnv([
			'OZ_DB_RDBMS' => 'sqlite',
			'OZ_DB_HOST'  => $db_path,
		]);

		$ns = $proj->getNamespace();

		// Inject the stub provider (onAbandon writes to self::$flagFile).
		$proj->writeFileFromStub('TestFormAbandonProvider', 'app/TestFormAbandonProvider.php', [
			'namespace' => $ns,
			'flag_file' => self::$flagFile,
		]);

		// Register provider in settings.
		$proj->setSetting('oz.forms.providers', 'test-abandon', "{$ns}\\TestFormAbandonProvider");

		// Build ORM classes and install the schema.
		$proj->oz('db', 'build', '--build-all', '--class-only')->mustRun();
		$proj->oz('migrations', 'create', '--force', '--label=initial')->mustRun();
		$proj->oz('migrations', 'run', '--skip-backup')->mustRun();

		try {
			[$server,, $port] = $proj->startServer('api', self::$host);
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
			self::$server->stop(3);
		}
		if (isset(self::$proj)) {
			self::$proj->destroy();
		}
		if (\is_file(self::$flagFile)) {
			\unlink(self::$flagFile);
		}
		parent::tearDownAfterClass();
	}

	// -------------------------------------------------------------------------
	// onAbandon on TTL expiry
	// -------------------------------------------------------------------------

	public function testOnAbandonCalledWhenSessionExpiresByTTL(): void
	{
		// 1. Start a session (provider TTL = 1 second, done immediately).
		[, $body] = $this->request('POST', '/form/test-abandon/init');
		$data     = \json_decode($body, true);

		self::assertSame(0, $data['error'] ?? -1, 'Init failed: ' . $body);

		// 2. Wait long enough for the 1-second TTL to elapse.
		\sleep(2);

		// 3. Make a request -- the FinishHook fires on the server side after the
		//    response is sent, which triggers the cache garbage collector.
		//    Any request will do; a non-existent resource is fine.
		$this->request('POST', '/form/test-abandon/init');

		// 4. The flag file is written synchronously inside the server process (same
		//    PHP request handling the FinishHook), so by the time file_get_contents
		//    (step 3 above) returns, the flag must already exist.
		self::assertFileExists(
			self::$flagFile,
			'onAbandon() should be called by the GC when the session expires by TTL.'
		);

		// Cleanup for other tests.
		\unlink(self::$flagFile);
	}

	// -------------------------------------------------------------------------
	// onAbandon NOT called on explicit cancel
	// -------------------------------------------------------------------------

	public function testOnAbandonNotCalledWhenSessionExplicitlyCancelled(): void
	{
		// 1. Start a session.
		[, $body]  = $this->request('POST', '/form/test-abandon/init');
		$data      = \json_decode($body, true);
		$resumeRef = $data['data']['resume_ref'] ?? '';

		self::assertSame(0, $data['error'] ?? -1, 'Init failed: ' . $body);
		self::assertNotEmpty($resumeRef);

		// 2. Explicitly cancel the session -- calls delete() directly.
		[, $body] = $this->request('POST', '/form/test-abandon/cancel', [], [
			'X-OZONE-Form-Resume-Ref' => $resumeRef,
		]);
		self::assertSame(0, \json_decode($body, true)['error'] ?? -1, 'Cancel failed: ' . $body);

		// 3. Trigger a FinishHook via another request so the GC can run.
		//    The GC must not find the already-deleted entry.
		$this->request('POST', '/form/test-abandon/init');

		// 4. The flag file must NOT exist.
		self::assertFileDoesNotExist(
			self::$flagFile,
			'onAbandon() must NOT be called when the session is explicitly cancelled.'
		);
	}

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	/**
	 * Makes an HTTP request to the running test server.
	 *
	 * @param string               $method  HTTP verb
	 * @param string               $path    URL path
	 * @param array<string,string> $fields  form fields for POST requests
	 * @param array<string,string> $headers extra HTTP headers
	 *
	 * @return array{0: int, 1: string} [status_code, body]
	 */
	private function request(string $method, string $path, array $fields = [], array $headers = []): array
	{
		$url = 'http://' . self::$host . ':' . self::$port . $path;

		$headerStr = "Accept: application/json\r\n";
		foreach ($headers as $name => $value) {
			$headerStr .= "{$name}: {$value}\r\n";
		}

		$opts = [
			'method'          => $method,
			'timeout'         => 10,
			'ignore_errors'   => true,
			'follow_location' => false,
			'header'          => $headerStr,
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
