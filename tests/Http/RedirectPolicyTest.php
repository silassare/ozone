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

use OZONE\Core\Http\RedirectPolicy;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Class RedirectPolicyTest.
 *
 * @internal
 */
#[CoversClass(RedirectPolicy::class)]
final class RedirectPolicyTest extends TestCase
{
	private const string CURRENT_HOST = 'app.example.com';

	public function testTheCurrentHostIsAllowedAndNothingElseIs(): void
	{
		$policy = RedirectPolicy::fromSetting([], self::CURRENT_HOST);

		self::assertFalse($policy->allowsAnyHost());
		self::assertTrue($policy->allows('https://app.example.com/next'));
		self::assertFalse($policy->allows('https://evil.example/next'));
	}

	public function testATargetNamingNoHostStaysOnThisServer(): void
	{
		$policy = RedirectPolicy::fromSetting([], self::CURRENT_HOST);

		self::assertTrue($policy->allows('/next'));
		self::assertTrue($policy->allows('?q=1'));
		self::assertTrue($policy->allows('#f'));
	}

	public function testAListedHostIsAllowedWhicheverWayItIsWritten(): void
	{
		$policy = RedirectPolicy::fromSetting(
			['partner.example', 'https://shop.example:8443/ignored'],
			self::CURRENT_HOST
		);

		self::assertTrue($policy->allows('https://partner.example/p'));
		self::assertTrue($policy->allows('https://shop.example/p'));
		self::assertTrue($policy->allows('https://app.example.com/p'));
		self::assertFalse($policy->allows('https://evil.example/p'));
	}

	public function testAHostIsComparedWithoutRegardToCase(): void
	{
		$policy = RedirectPolicy::fromSetting(['PARTNER.example'], 'APP.example.com');

		self::assertTrue($policy->allows('https://partner.EXAMPLE/p'));
		self::assertTrue($policy->allows('https://app.example.COM/p'));
	}

	public function testTheWildcardAllowsAnyHost(): void
	{
		$policy = RedirectPolicy::fromSetting([RedirectPolicy::ANY_HOST], self::CURRENT_HOST);

		self::assertTrue($policy->allowsAnyHost());
		self::assertTrue($policy->allows('https://evil.example/p'));
		self::assertSame([], $policy->hosts());
	}

	public function testOneHostCanBeGivenWithoutAList(): void
	{
		$policy = RedirectPolicy::fromSetting('partner.example', self::CURRENT_HOST);

		self::assertTrue($policy->allows('https://partner.example/p'));
		self::assertFalse($policy->allows('https://evil.example/p'));
	}

	public function testTheHostsAreTheOnesAFormAsksFor(): void
	{
		$policy = RedirectPolicy::fromSetting(['partner.example', '', '  '], self::CURRENT_HOST);

		self::assertSame([self::CURRENT_HOST, 'partner.example'], $policy->hosts());
	}

	/**
	 * A target OZone cannot read is a target it cannot vouch for, and the host of a URL a client
	 * reads differently is exactly what an open redirect is built on.
	 */
	#[DataProvider('provideATargetThatIsNotAUrlIsRefusedCases')]
	public function testATargetThatIsNotAUrlIsRefused(string $target): void
	{
		$policy = RedirectPolicy::fromSetting([], self::CURRENT_HOST);

		self::assertFalse($policy->allows($target));
	}

	public static function provideATargetThatIsNotAUrlIsRefusedCases(): iterable
	{
		return [
			'a scheme with no authority' => ['https://'],
			'a port out of range'        => ['https://app.example.com:99999/p'],
			'not a URL at all'           => ['http://'],
		];
	}

	/**
	 * `http://evil.example\@app.example.com/` is a request to **evil.example**, which is the host
	 * the policy must decide on.
	 */
	public function testTheHostIsTheOneAClientWouldReach(): void
	{
		$policy = RedirectPolicy::fromSetting([], self::CURRENT_HOST);

		self::assertFalse($policy->allows('http://evil.example\@app.example.com/'));
	}
}
