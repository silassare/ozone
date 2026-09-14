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

use OZONE\Core\Http\TrustedProxies;
use PHPUnit\Framework\TestCase;

/**
 * Class TrustedProxiesTest.
 *
 * @internal
 *
 * @covers \OZONE\Core\Http\TrustedProxies
 */
final class TrustedProxiesTest extends TestCase
{
	public function testExactAndCidrEntries(): void
	{
		$proxies = new TrustedProxies([
			'203.0.113.7'   => true,
			'10.0.0.0/8'    => true,
			'2001:db8::/32' => true,
		]);

		self::assertTrue($proxies->isTrusted('203.0.113.7'));
		self::assertFalse($proxies->isTrusted('203.0.113.8'));
		self::assertTrue($proxies->isTrusted('10.200.3.4'));
		self::assertFalse($proxies->isTrusted('11.0.0.1'));
		self::assertTrue($proxies->isTrusted('2001:db8:1::5'));
		self::assertFalse($proxies->isTrusted('2001:db9::5'));
		self::assertFalse($proxies->isTrusted('not-an-ip'));
	}

	public function testExplicitFalseOverridesARange(): void
	{
		$proxies = new TrustedProxies(['10.0.0.0/8' => true, '10.6.6.6' => false]);

		self::assertTrue($proxies->isTrusted('10.1.1.1'));
		self::assertFalse($proxies->isTrusted('10.6.6.6'));
	}

	public function testOddPrefixLengths(): void
	{
		$proxies = new TrustedProxies(['173.245.48.0/20' => true, '192.0.2.0/abc' => true]);

		self::assertTrue($proxies->isTrusted('173.245.63.255'));
		self::assertFalse($proxies->isTrusted('173.245.64.0'));
		self::assertFalse($proxies->isTrusted('192.0.2.1'));
	}

	public function testHeadersFromAnUntrustedPeerAreIgnored(): void
	{
		$proxies = new TrustedProxies([]);

		self::assertSame('198.51.100.9', $proxies->resolveClientIp('198.51.100.9', null, '6.6.6.6'));
	}

	public function testRightMostUntrustedHopIsTheClient(): void
	{
		$proxies = new TrustedProxies(['10.0.0.0/8' => true]);

		// The client wrote 6.6.6.6 itself; the proxy appended the real peer, 198.51.100.9.
		self::assertSame(
			'198.51.100.9',
			$proxies->resolveClientIp('10.0.0.2', null, '6.6.6.6, 198.51.100.9, 10.0.0.1')
		);
	}

	public function testPortsAndBracketsAreStripped(): void
	{
		$proxies = new TrustedProxies(['10.0.0.1' => true]);

		self::assertSame('198.51.100.9', $proxies->resolveClientIp('10.0.0.1', null, '198.51.100.9:5678'));
		self::assertSame('2001:db8::7', $proxies->resolveClientIp('10.0.0.1', null, '[2001:db8::7]:443'));
	}

	public function testForwardedHeaderWinsOverXForwardedFor(): void
	{
		$proxies = new TrustedProxies(['10.0.0.1' => true]);

		self::assertSame(
			'2001:db8::1',
			$proxies->resolveClientIp('10.0.0.1', 'for=192.0.2.60;proto=http, for="[2001:db8::1]:4711"', '6.6.6.6')
		);
	}

	public function testClientIpHeaderFromATrustedPeerWins(): void
	{
		$proxies = new TrustedProxies(['173.245.48.0/20' => true]);

		self::assertSame('198.51.100.9', $proxies->resolveClientIp('173.245.48.1', null, '6.6.6.6', '198.51.100.9'));
		self::assertSame('2001:db8::7', $proxies->resolveClientIp('173.245.48.1', null, null, '2001:db8::7'));
	}

	public function testClientIpHeaderFromAnUntrustedPeerIsIgnored(): void
	{
		$proxies = new TrustedProxies([]);

		self::assertSame('203.0.113.5', $proxies->resolveClientIp('203.0.113.5', null, null, '6.6.6.6'));
	}

	public function testInvalidClientIpHeaderFallsBackToTheChain(): void
	{
		$proxies = new TrustedProxies(['10.0.0.1' => true]);

		self::assertSame('198.51.100.9', $proxies->resolveClientIp('10.0.0.1', null, '198.51.100.9', 'unknown'));
		self::assertSame('198.51.100.9', $proxies->resolveClientIp('10.0.0.1', null, '198.51.100.9', ''));
	}

	public function testSchemeFromATrustedProxy(): void
	{
		$proxies = new TrustedProxies(['10.0.0.1' => true]);

		self::assertSame('https', $proxies->resolveScheme('10.0.0.1', null, 'https'));
		self::assertSame('https', $proxies->resolveScheme('10.0.0.1', 'for=192.0.2.1;proto=https', 'http'));
		self::assertSame('http', $proxies->resolveScheme('10.0.0.1', 'for=192.0.2.1', 'HTTP, https'));
		self::assertNull($proxies->resolveScheme('10.0.0.1', null, 'ftp'));
		self::assertNull($proxies->resolveScheme('10.0.0.1', null, null));
	}

	public function testSchemeFromAnUntrustedPeerIsIgnored(): void
	{
		self::assertNull((new TrustedProxies([]))->resolveScheme('203.0.113.9', null, 'https'));
	}

	public function testHostFromATrustedProxy(): void
	{
		$proxies = new TrustedProxies(['10.0.0.1' => true]);

		self::assertSame('app.example.com', $proxies->resolveHost('10.0.0.1', null, 'App.Example.com'));
		self::assertSame(
			'app.example.com:8443',
			$proxies->resolveHost('10.0.0.1', null, 'evil.example, app.example.com:8443')
		);
		self::assertSame(
			'app.example.com',
			$proxies->resolveHost('10.0.0.1', 'for=192.0.2.1;host=app.example.com', 'other.example')
		);
		self::assertNull($proxies->resolveHost('10.0.0.1', null, 'bad host/'));
		self::assertNull($proxies->resolveHost('10.0.0.1', null, null));
	}

	public function testHostFromAnUntrustedPeerIsIgnored(): void
	{
		self::assertNull((new TrustedProxies([]))->resolveHost('203.0.113.9', null, 'app.example.com'));
	}

	public function testUnparsableHopStopsAtTheNearestTrustedProxy(): void
	{
		$proxies = new TrustedProxies(['10.0.0.0/8' => true]);

		self::assertSame('10.0.0.5', $proxies->resolveClientIp('10.0.0.1', null, '198.51.100.9, unknown, 10.0.0.5'));
		self::assertSame('10.0.0.1', $proxies->resolveClientIp('10.0.0.1', null, ''));
	}
}
