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

use PHPUnit\Framework\TestCase;

/**
 * Class FrankenPhpClassicServerTest.
 *
 * FrankenPHP in classic mode, where the script runs once per request: OZone must see a
 * one-request-per-process runtime there. FrankenPHP defines `frankenphp_handle_request()` in this
 * mode too, and taking it for worker mode made `OZone::run()` answer the mock boot request and then
 * throw its way out of the script.
 *
 * @internal
 *
 * @group frankenphp
 *
 * @covers \OZONE\Core\Runtime\WorkerRuntime
 */
final class FrankenPhpClassicServerTest extends TestCase
{
	protected function setUp(): void
	{
		parent::setUp();

		if (!\extension_loaded('curl')) {
			self::fail('This test is an HTTP client: it needs ext-curl (the test image has it).');
		}

		if ('pong' !== ($this->get('/runtime-probe/ping')['body'] ?? null)) {
			$message = 'FrankenPHP (classic) is unavailable at ' . self::baseUrl();

			\getenv('OZ_TEST_RUNTIMES_REQUIRED') ? self::fail($message) : self::markTestSkipped($message);
		}
	}

	public function testTheRuntimeIsOneRequestPerProcess(): void
	{
		$response = $this->get('/runtime-probe/info');

		self::assertSame(200, $response['status'], $response['body']);

		$info = \json_decode($response['body'], true);

		self::assertSame('cgi', $info['runtime']);
		self::assertFalse($info['persistent']);
		self::assertFalse($info['console']);
	}

	public function testTheRequestedRouteIsTheOneServed(): void
	{
		// Taken for a worker, OZone answered the mock boot request (`/`) instead of this one.
		self::assertSame(['status' => 404], \array_intersect_key(
			$this->get('/no-such-route', ['Accept: application/json']) ?? [],
			['status' => true]
		));
		self::assertSame('{"hello":"world"}', $this->get('/runtime-probe/json')['body'] ?? null);
	}

	/**
	 * @param list<string> $headers
	 *
	 * @return null|array{status: int, body: string}
	 */
	private function get(string $path, array $headers = []): ?array
	{
		$curl = \curl_init(self::baseUrl() . $path);

		\curl_setopt_array($curl, [
			\CURLOPT_RETURNTRANSFER => true,
			\CURLOPT_TIMEOUT        => 30,
			\CURLOPT_HTTPHEADER     => $headers,
		]);

		$body = \curl_exec($curl);

		if (!\is_string($body)) {
			return null;
		}

		return ['status' => (int) \curl_getinfo($curl, \CURLINFO_RESPONSE_CODE), 'body' => $body];
	}

	private static function baseUrl(): string
	{
		return \rtrim((string) \getenv('OZ_TEST_FRANKENPHP_CLASSIC_URL'), '/');
	}
}
