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

namespace OZONE\Tests\Http;

use OZONE\Core\Http\HTTPEnvironment;
use OZONE\Core\Http\TrustedProxies;
use OZONE\Core\Http\Uri;
use PHPUnit\Framework\TestCase;

/**
 * Class UriFromEnvironmentTest.
 *
 * Tests for {@see Uri::createFromEnvironment()} behind proxies.
 *
 * @internal
 *
 * @covers \OZONE\Core\Http\Uri
 */
final class UriFromEnvironmentTest extends TestCase
{
	public function testSchemeFromATrustedTlsProxy(): void
	{
		$uri = self::uri(['HTTP_X_FORWARDED_PROTO' => 'https']);

		self::assertSame('https', $uri->getScheme());
		self::assertNull($uri->getPort());
		self::assertStringStartsWith('https://localhost/', (string) $uri);
	}

	public function testForwardedHeaderAndPort(): void
	{
		$uri = self::uri(['HTTP_FORWARDED' => 'for=192.0.2.1;proto=https', 'HTTP_X_FORWARDED_PORT' => '8443']);

		self::assertSame('https', $uri->getScheme());
		self::assertSame(8443, $uri->getPort());
	}

	public function testHostFromATrustedProxy(): void
	{
		$uri = self::uri([
			'HTTP_X_FORWARDED_HOST'  => 'app.example.com',
			'HTTP_X_FORWARDED_PROTO' => 'https',
			'SERVER_PORT'            => 8080,
		]);

		self::assertSame('app.example.com', $uri->getHost());
		self::assertNull($uri->getPort());
		self::assertStringStartsWith('https://app.example.com/', (string) $uri);
	}

	public function testForwardedHostIgnoresTheServerPort(): void
	{
		$uri = self::uri(['HTTP_X_FORWARDED_HOST' => 'app.example.com', 'SERVER_PORT' => 8080]);

		self::assertSame('http', $uri->getScheme());
		self::assertNull($uri->getPort());
	}

	public function testHeadersFromAnUntrustedPeerAreIgnored(): void
	{
		$uri = self::uri([
			'REMOTE_ADDR'            => '203.0.113.9',
			'HTTP_X_FORWARDED_PROTO' => 'https',
			'HTTP_X_FORWARDED_HOST'  => 'app.example.com',
		]);

		self::assertSame('http', $uri->getScheme());
		self::assertSame('localhost', $uri->getHost());
		self::assertNull($uri->getPort());
	}

	private static function uri(array $env): Uri
	{
		return Uri::createFromEnvironment(
			HTTPEnvironment::mock($env + ['REMOTE_ADDR' => '10.0.0.1', 'SERVER_PORT' => 80]),
			new TrustedProxies(['10.0.0.1' => true])
		);
	}
}
