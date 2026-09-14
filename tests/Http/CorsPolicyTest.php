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
use PHPUnit\Framework\TestCase;

/**
 * Class CorsPolicyTest.
 *
 * @internal
 *
 * @covers \OZONE\Core\Http\CorsPolicy
 */
final class CorsPolicyTest extends TestCase
{
	private const REQUEST_URL = 'https://api.example.com/items?x=1';
	private const APP_ORIGIN  = 'https://app.example.com';

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

	public function testNormalize(): void
	{
		self::assertSame('https://app.example.com', CorsPolicy::normalize('https://App.Example.com:443/path?q=1'));
		self::assertSame('http://localhost:3000', CorsPolicy::normalize('http://localhost:3000'));
		self::assertNull(CorsPolicy::normalize('null'));
		self::assertNull(CorsPolicy::normalize('ftp://files.example.com'));
	}
}
