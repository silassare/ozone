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

use OZONE\Core\Http\CorsPolicy;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Class CorsPolicyTest.
 *
 * @internal
 */
#[CoversClass(CorsPolicy::class)]
final class CorsPolicyTest extends TestCase
{
	private const string REQUEST_URL = 'https://api.example.com/items?x=1';
	private const string APP_ORIGIN  = 'https://app.example.com';

	public function testWildcardAllowsAnyOriginButNeverWithCredentials(): void
	{
		$policy = CorsPolicy::fromSetting('*', self::REQUEST_URL, self::APP_ORIGIN);

		self::assertTrue($policy->allowsAnyOrigin());
		self::assertTrue($policy->allows('https://evil.example'));
		self::assertSame(['Access-Control-Allow-Origin' => '*'], $policy->headers('https://evil.example'));
	}

	public function testSelfAllowsTheAppAndTheRequestOriginOnly(): void
	{
		$policy = CorsPolicy::fromSetting('self', self::REQUEST_URL, self::APP_ORIGIN);

		self::assertTrue($policy->allows(self::APP_ORIGIN));
		self::assertTrue($policy->allows('https://api.example.com'));
		self::assertFalse($policy->allows('https://evil.example'));
	}

	public function testAllowedOriginIsReflectedWithCredentials(): void
	{
		$policy = CorsPolicy::fromSetting('self', self::REQUEST_URL, self::APP_ORIGIN);

		self::assertSame([
			'Vary'                             => 'Origin',
			'Access-Control-Allow-Origin'      => self::APP_ORIGIN,
			'Access-Control-Allow-Credentials' => 'true',
		], $policy->headers(self::APP_ORIGIN));
	}

	public function testDisallowedOrMissingOriginGetsNoAllowOrigin(): void
	{
		$policy = CorsPolicy::fromSetting('self', self::REQUEST_URL, self::APP_ORIGIN);

		self::assertSame(['Vary' => 'Origin'], $policy->headers('https://evil.example'));
		self::assertSame(['Vary' => 'Origin'], $policy->headers(null));
	}

	public function testListOfOrigins(): void
	{
		$policy = CorsPolicy::fromSetting(
			['self', 'https://admin.example.com:8443'],
			self::REQUEST_URL,
			self::APP_ORIGIN
		);

		self::assertTrue($policy->allows(self::APP_ORIGIN));
		self::assertTrue($policy->allows('https://admin.example.com:8443'));
		self::assertFalse($policy->allows('https://admin.example.com'));
	}

	public function testOriginsCompareOnSchemeHostAndPort(): void
	{
		$policy = CorsPolicy::fromSetting(self::APP_ORIGIN, self::REQUEST_URL, self::APP_ORIGIN);

		self::assertFalse($policy->allows('http://app.example.com'));
		self::assertFalse($policy->allows('https://app.example.com:444'));
		self::assertFalse($policy->allows('https://app.example.com.evil.example'));
		self::assertTrue($policy->allows('https://APP.example.com'));
		self::assertTrue($policy->allows('https://app.example.com:443'));
	}

	/**
	 * The origin an allow-list is compared against is the host the browser would talk to.
	 *
	 * `http://evil.com\@good.com` is a request to **evil.com**; reading its authority by hand
	 * answers `good.com`, so a policy listing `good.com` would have reflected the origin back and
	 * let evil.com read the answer. A browser never sends an `Origin` shaped like this, so this is
	 * the policy agreeing with the client rather than a hole anyone could have walked through --
	 * but the agreement is what the decision rests on.
	 */
	public function testAnOriginIsTheHostTheBrowserWouldTalkTo(): void
	{
		self::assertSame('http://evil.com', CorsPolicy::normalize('http://evil.com\@good.com/'));
	}

	/**
	 * One origin spelled two ways compares equal.
	 */
	public function testAnOriginHasOneSpelling(): void
	{
		self::assertSame('https://example.com', CorsPolicy::normalize('https://EXAMPLE.com:443'));
		self::assertSame('http://xn--r8jz45g.jp', CorsPolicy::normalize('http://例え.jp'));
	}

	/**
	 * A malformed origin is no origin: it used to reduce to the request root and be refused for
	 * having no host, which was the right answer by accident.
	 */
	public function testAMalformedOriginIsRefused(): void
	{
		self::assertNull(CorsPolicy::normalize('https://exa mple.com'));
		self::assertNull(CorsPolicy::normalize('http://host:80x'));
		self::assertNull(CorsPolicy::normalize('javascript://comment%0Aalert(1)'));
	}

	public function testNormalize(): void
	{
		self::assertSame('https://app.example.com', CorsPolicy::normalize('https://App.Example.com:443/path?q=1'));
		self::assertSame('http://localhost:3000', CorsPolicy::normalize('http://localhost:3000'));
		self::assertNull(CorsPolicy::normalize('null'));
		self::assertNull(CorsPolicy::normalize('ftp://files.example.com'));
	}
}
