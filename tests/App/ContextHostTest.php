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
 * Class ContextHostTest.
 *
 * Tests for {@see Context::getHost()} behind proxies.
 *
 * @internal
 *
 * @covers \OZONE\Core\App\Context
 * @covers \OZONE\Core\Http\ClientInfo
 */
final class ContextHostTest extends TestCase
{
	// No dot in the key: dotted settings keys are read as paths.
	private const PROXY_RANGE = '2001:db8::/32';

	protected function setUp(): void
	{
		Settings::set('oz.proxies', self::PROXY_RANGE, true);
	}

	protected function tearDown(): void
	{
		Settings::unset('oz.proxies', self::PROXY_RANGE);
	}

	public function testForwardedHostFromATrustedProxyMatchesTheRequestUri(): void
	{
		$context = self::context(['REMOTE_ADDR' => '2001:db8::1', 'HTTP_X_FORWARDED_HOST' => 'app.example.com']);

		self::assertSame('app.example.com', $context->getHost());
		self::assertSame('app.example.com', $context->getRequest()->getUri()->getHost());
	}

	public function testForwardedHostFromAnUntrustedPeerIsIgnored(): void
	{
		$context = self::context(['REMOTE_ADDR' => '198.51.100.1', 'HTTP_X_FORWARDED_HOST' => 'app.example.com']);

		self::assertSame('localhost', $context->getHost());
		self::assertSame('localhost', $context->getRequest()->getUri()->getHost());
	}

	private static function context(array $env): Context
	{
		return new Context(HTTPEnvironment::mock($env), null, Context::root());
	}
}
