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

	private static function context(array $env): Context
	{
		return new Context(HTTPEnvironment::mock($env), null, Context::root());
	}
}
