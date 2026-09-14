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

namespace OZONE\Tests\App;

use OZONE\Core\App\Context;
use OZONE\Core\App\Settings;
use OZONE\Core\Http\HTTPEnvironment;
use PHPUnit\Framework\TestCase;

/**
 * Class ContextUserIpTest.
 *
 * Tests for {@see Context::getUserIP()}.
 *
 * @internal
 *
 * @covers \OZONE\Core\App\Context
 * @covers \OZONE\Core\Http\ClientInfo
 */
final class ContextUserIpTest extends TestCase
{
	// No dot in the key: dotted settings keys are read as paths.
	private const PROXY_RANGE = '2001:db8::/32';

	private const BEHIND_PROXY = [
		'REMOTE_ADDR'           => '2001:db8::1',
		'HTTP_X_FORWARDED_FOR'  => '198.51.100.9',
		'HTTP_CF_CONNECTING_IP' => '203.0.113.5',
	];

	protected function setUp(): void
	{
		Settings::set('oz.proxies', self::PROXY_RANGE, true);
	}

	protected function tearDown(): void
	{
		Settings::unset('oz.proxies', self::PROXY_RANGE);
		Settings::unset('oz.request', 'OZ_CLIENT_IP_HEADER');
	}

	public function testClientIpHeaderIsIgnoredUntilConfigured(): void
	{
		self::assertSame('198.51.100.9', self::userIp(self::BEHIND_PROXY));
	}

	public function testConfiguredClientIpHeaderFromATrustedProxy(): void
	{
		Settings::set('oz.request', 'OZ_CLIENT_IP_HEADER', 'CF-Connecting-IP');

		self::assertSame('203.0.113.5', self::userIp(self::BEHIND_PROXY));
	}

	public function testConfiguredClientIpHeaderFromAnUntrustedPeer(): void
	{
		Settings::set('oz.request', 'OZ_CLIENT_IP_HEADER', 'CF-Connecting-IP');

		self::assertSame('198.51.100.1', self::userIp([
			'REMOTE_ADDR'           => '198.51.100.1',
			'HTTP_CF_CONNECTING_IP' => '203.0.113.5',
		]));
	}

	private static function userIp(array $env): ?string
	{
		return (new Context(HTTPEnvironment::mock($env), null, Context::root()))->getUserIP();
	}
}
