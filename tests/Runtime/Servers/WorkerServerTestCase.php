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

namespace OZONE\Tests\Runtime\Servers;

use CURLFile;
use OZONE\Core\Runtime\WorkerRuntime;
use OZONE\Tests\Support\Servers\RuntimeProbe;
use PHPUnit\Framework\TestCase;

/**
 * Class WorkerServerTestCase.
 *
 * OZone served by a real worker server, over real HTTP: the server containers of
 * docker/compose.yaml (profile `runtimes`) run tests/Support/Servers/ through the bridges of
 * `oz/Runtime/Bridges/`, two workers each, and these tests are their client.
 *
 * Every case is something a bridge has to carry across and nothing short of the real server proves:
 * the request as the client sent it, every value of every response header, a body larger than one
 * frame, and each way a request can end -- without the process ending with it.
 *
 * @internal
 */
abstract class WorkerServerTestCase extends TestCase
{
	protected function setUp(): void
	{
		parent::setUp();

		if (!\extension_loaded('curl')) {
			self::fail('These tests are an HTTP client: they need ext-curl (the test image has it).');
		}

		$url   = self::baseUrl();
		$error = null;

		if ('' === $url) {
			$error = static::urlVariable() . ' is not set';
		} else {
			$ping = $this->request('GET', '/runtime-probe/ping', [], null, false);

			if (null === $ping || 'pong' !== $ping['body']) {
				$error = 'nothing answers at ' . $url;
			}
		}

		if (null !== $error) {
			if (\getenv('OZ_TEST_RUNTIMES_REQUIRED')) {
				self::fail(static::loopName() . ' is unavailable: ' . $error . ' (OZ_TEST_RUNTIMES_REQUIRED is set)');
			}

			self::markTestSkipped(static::loopName() . ' is unavailable: ' . $error);
		}
	}

	public function testOZoneRunsAsTheServersWorker(): void
	{
		$info = $this->json('GET', '/runtime-probe/info');

		self::assertSame('worker', $info['runtime']);
		self::assertSame(static::loopName(), $info['loop']);
		self::assertTrue($info['persistent']);

		// A worker is not a command line, even under the `cli` SAPI (RoadRunner, Swoole): a console
		// runtime answers a 404 by printing it and exiting the process.
		self::assertFalse($info['console']);
	}

	public function testAProcessServesMoreThanOneRequest(): void
	{
		$served = [];

		// Two workers per server: five requests cannot each land on a process of their own.
		for ($i = 0; $i < 5; ++$i) {
			$info                 = $this->json('GET', '/runtime-probe/info');
			$served[$info['pid']] = \max($served[$info['pid']] ?? 0, $info['served']);
		}

		self::assertLessThanOrEqual(2, \count($served), 'more processes than workers: they are being restarted');
		self::assertGreaterThanOrEqual(2, \max($served), 'no process outlived a request');
	}

	public function testAFailureIsAnsweredEveryTime(): void
	{
		$first  = $this->request('GET', '/no-such-route', ['Accept: application/json']);
		$second = $this->request('GET', '/no-such-route', ['Accept: application/json']);

		self::assertSame(404, $first['status']);

		// The first error a process reports sets `BaseException::$just_die`: unless the request
		// releases it, every later error dies with no response.
		self::assertSame(404, $second['status']);

		$a = \json_decode($first['body'], true);
		$b = \json_decode($second['body'], true);

		self::assertIsArray($a);
		self::assertSame(1, $a['error']);
		self::assertSame($a['msg'], $b['msg'] ?? null);
	}

	public function testAHandlerThatRespondsThenThrowsIsAnswered(): void
	{
		$response = $this->request('GET', '/runtime-probe/respond-mid');

		self::assertSame(204, $response['status']);
		self::assertSame('', $response['body']);

		// ... and the worker unwound through the rest of the handler instead of dying on it.
		self::assertSame('worker', $this->json('GET', '/runtime-probe/info')['runtime']);
	}

	public function testEveryHeaderValueReachesTheClient(): void
	{
		$response = $this->request('GET', '/runtime-probe/headers');

		self::assertSame(200, $response['status']);
		self::assertSame(['yes'], $response['headers']['x-oz-probe'] ?? null);

		// Two values of one header are two header lines: a bridge that keeps only the last loses one.
		self::assertSame(['a', 'b'], $response['headers']['x-oz-multi'] ?? null);

		// `Set-Cookie` above all, which cannot be folded into one comma-separated line: the handler
		// sets two, and the session adds its ID and the XSRF token without replacing them.
		$names = \array_map(
			static fn (string $line): string => \strstr($line, '=', true) ?: $line,
			$response['headers']['set-cookie'] ?? []
		);

		self::assertContains('oz_probe_a', $names);
		self::assertContains('oz_probe_b', $names);
		self::assertContains('OZONE_SID', $names);
		self::assertContains('XSRF-TOKEN', $names);
	}

	public function testAnAnonymousRequestGetsNoSession(): void
	{
		// No credentials and nothing written to the session: no session row, no cookie. It used to
		// cost a session insert per anonymous request.
		$response = $this->request('GET', '/runtime-probe/json');

		self::assertSame(200, $response['status']);
		self::assertArrayNotHasKey('set-cookie', $response['headers']);
	}

	public function testAResponseHeaderDoesNotOutliveItsRequest(): void
	{
		$this->request('GET', '/runtime-probe/headers');

		$next = $this->request('GET', '/runtime-probe/info');

		self::assertArrayNotHasKey('x-oz-probe', $next['headers']);
	}

	public function testTheRequestArrivesAsTheClientSentIt(): void
	{
		$payload = ['n' => 42, 's' => 'Ségou', 'list' => [1, 2]];
		$echo    = $this->json('POST', '/runtime-probe/echo?a=1&b[]=x', [
			'Content-Type: application/json',
			'X-OZ-Echo: hello',
		], \json_encode($payload, \JSON_THROW_ON_ERROR));

		self::assertSame('POST', $echo['method']);
		self::assertSame(['a' => '1', 'b' => ['x']], $echo['query']);
		self::assertSame($payload, $echo['parsed']);
		self::assertSame('hello', $echo['header']);
		self::assertSame('application/json', $echo['content_type']);
		self::assertSame(\parse_url(self::baseUrl(), \PHP_URL_HOST), $echo['host']);

		// The TCP peer: the trusted-proxy check reads it, so a bridge must not leave it out.
		self::assertNotFalse(\filter_var($echo['client_ip'], \FILTER_VALIDATE_IP), 'no client IP');
	}

	public function testAFormBodyIsParsed(): void
	{
		$echo = $this->json('POST', '/runtime-probe/echo', [
			'Content-Type: application/x-www-form-urlencoded',
		], 'x=1&y%5B%5D=2&z=%C3%A9');

		self::assertSame(['x' => '1', 'y' => ['2'], 'z' => 'é'], $echo['parsed']);
	}

	public function testAnUploadedFileArrivesIntact(): void
	{
		$content = \random_bytes(150000);
		$path    = \tempnam(\sys_get_temp_dir(), 'oz_upload_');

		\file_put_contents($path, $content);

		try {
			$result = $this->json('POST', '/runtime-probe/upload', [], [
				'avatar' => new CURLFile($path, 'image/png', 'avatar.png'),
				'label'  => 'profile',
			]);
		} finally {
			\unlink($path);
		}

		self::assertSame([
			'avatar' => [
				'name'  => 'avatar.png',
				'size'  => \strlen($content),
				'error' => \UPLOAD_ERR_OK,
				'sha1'  => \sha1($content),
			],
		], $result['files']);
		self::assertSame(['label' => 'profile'], $result['fields']);
	}

	public function testALargeBodyArrivesWhole(): void
	{
		// Larger than the bridges send in one piece (1 MiB), so it is streamed in chunks.
		$size     = 3 * 1024 * 1024 + 7;
		$response = $this->request('GET', '/runtime-probe/large?size=' . $size);

		self::assertSame(200, $response['status']);
		self::assertSame($size, \strlen($response['body']));
		self::assertSame(\sha1(RuntimeProbe::pattern($size)), \sha1($response['body']));
	}

	public function testStrayOutputNeverReachesTheClient(): void
	{
		$response = $this->request('GET', '/runtime-probe/stray-output', ['Accept: application/json']);

		// The router refuses output written around the Response object, and the refusal is itself a
		// response -- under RoadRunner, bytes on the process output would break the protocol pipe.
		self::assertSame(500, $response['status']);
		self::assertStringNotContainsString('stray-bytes', $response['body']);
		self::assertSame('worker', $this->json('GET', '/runtime-probe/info')['runtime']);
	}

	/**
	 * What the bridge names its worker loop ({@see WorkerRuntime::getLoopName()}).
	 */
	abstract protected static function loopName(): string;

	/**
	 * The environment variable holding the server's URL (set by docker/compose.yaml).
	 */
	abstract protected static function urlVariable(): string;

	/**
	 * Sends a request and decodes its JSON response, which must be a 200.
	 *
	 * @param list<string>                     $headers
	 * @param null|array<string, mixed>|string $body
	 */
	protected function json(string $method, string $path, array $headers = [], array|string|null $body = null): array
	{
		$response = $this->request($method, $path, $headers, $body);

		self::assertSame(200, $response['status'], \sprintf('%s %s: %s', $method, $path, $response['body']));

		$data = \json_decode($response['body'], true);

		self::assertIsArray($data, $response['body']);

		return $data;
	}

	/**
	 * Sends a request to the server.
	 *
	 * @param list<string>                     $headers header lines
	 * @param null|array<string, mixed>|string $body    a raw body, or fields sent as multipart
	 * @param bool                             $strict  whether a transport error fails the test
	 *
	 * @return null|array{status: int, headers: array<string, list<string>>, body: string}
	 */
	protected function request(
		string $method,
		string $path,
		array $headers = [],
		array|string|null $body = null,
		bool $strict = true
	): ?array {
		$received = [];
		$curl     = \curl_init(self::baseUrl() . $path);

		\curl_setopt_array($curl, [
			\CURLOPT_CUSTOMREQUEST  => $method,
			\CURLOPT_RETURNTRANSFER => true,
			\CURLOPT_TIMEOUT        => 30,
			// No `Expect: 100-continue`: the tests are about the request, not curl's handshake.
			\CURLOPT_HTTPHEADER     => \array_merge($headers, ['Expect:']),
			\CURLOPT_HEADERFUNCTION => static function ($curl, string $line) use (&$received): int {
				$pos = \strpos($line, ':');

				if (false !== $pos) {
					$received[\strtolower(\trim(\substr($line, 0, $pos)))][] = \trim(\substr($line, $pos + 1));
				}

				return \strlen($line);
			},
		]);

		if (null !== $body) {
			\curl_setopt($curl, \CURLOPT_POSTFIELDS, $body);
		}

		$out = \curl_exec($curl);

		if (!\is_string($out)) {
			if ($strict) {
				self::fail(\sprintf('%s %s failed: %s', $method, $path, \curl_error($curl)));
			}

			return null;
		}

		return [
			'status'  => (int) \curl_getinfo($curl, \CURLINFO_RESPONSE_CODE),
			'headers' => $received,
			'body'    => $out,
		];
	}

	private static function baseUrl(): string
	{
		return \rtrim((string) \getenv(static::urlVariable()), '/');
	}
}
