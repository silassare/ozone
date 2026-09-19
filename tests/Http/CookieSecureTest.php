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

use OZONE\Core\App\Context;
use OZONE\Core\App\Settings;
use OZONE\Core\Http\Cookie;
use OZONE\Core\Http\HTTPEnvironment;
use PHPUnit\Framework\TestCase;

/**
 * Class CookieSecureTest.
 *
 * Tests for the `Secure` flag of {@see Cookie::create()}.
 *
 * @internal
 *
 * @covers \OZONE\Core\Http\Cookie
 */
final class CookieSecureTest extends TestCase
{
	protected function tearDown(): void
	{
		Settings::unset('oz.cookie', 'OZ_COOKIE_SECURE');
	}

	public function testAutoFollowsTheRequestScheme(): void
	{
		self::assertFalse(Cookie::create(self::context([]), 'c')->secure);
		self::assertTrue(Cookie::create(self::context(['HTTPS' => 'on']), 'c')->secure);
	}

	public function testSecureCanBeForced(): void
	{
		Settings::set('oz.cookie', 'OZ_COOKIE_SECURE', true);

		self::assertTrue(Cookie::create(self::context([]), 'c')->secure);
	}

	/**
	 * A cookie of the default domain is host-only: it names no domain.
	 *
	 * Naming the request host sent the session to every subdomain of it (RFC 6265), and behind a proxy
	 * that rewrites `Host` it named a host the browser never saw, which the browser refused: the
	 * session was lost on every request.
	 */
	public function testTheDefaultCookieIsHostOnly(): void
	{
		$cookie = Cookie::create(self::context(['HTTP_HOST' => 'app.example.com']), 'sid', 'v');

		self::assertNull($cookie->domain);
		self::assertStringNotContainsString('Domain=', (string) $cookie);
	}

	public function testAnExplicitDomainIsStillNamed(): void
	{
		Settings::set('oz.cookie', 'OZ_COOKIE_DOMAIN', 'example.com');

		try {
			$cookie = Cookie::create(self::context(['HTTP_HOST' => 'app.example.com']), 'sid', 'v');

			self::assertStringContainsString('; Domain=example.com', (string) $cookie);
		} finally {
			Settings::set('oz.cookie', 'OZ_COOKIE_DOMAIN', 'self');
		}
	}

	private static function context(array $env): Context
	{
		return new Context(HTTPEnvironment::mock($env), null, Context::root());
	}
}
