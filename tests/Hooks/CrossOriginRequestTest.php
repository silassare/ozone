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

namespace OZONE\Tests\Hooks;

use OZONE\Core\App\Context;
use OZONE\Core\Exceptions\ForbiddenException;
use OZONE\Core\Hooks\MainBootHookReceiver;
use OZONE\Core\Http\HTTPEnvironment;
use PHPUnit\Framework\TestCase;

/**
 * Class CrossOriginRequestTest.
 *
 * Tests for {@see MainBootHookReceiver::assertOriginAllowed()}, run before routing.
 *
 * @internal
 *
 * @covers \OZONE\Core\Hooks\MainBootHookReceiver
 * @covers \OZONE\Core\Http\CorsPolicy
 */
final class CrossOriginRequestTest extends TestCase
{
	public function testDisallowedOriginIsRejected(): void
	{
		$this->expectException(ForbiddenException::class);
		$this->expectExceptionMessage('OZ_CROSS_SITE_REQUEST_NOT_ALLOWED');

		MainBootHookReceiver::assertOriginAllowed(self::context('POST', 'https://evil.example'));
	}

	/**
	 * @dataProvider provideAllowedRequestsCases
	 */
	public function testAllowedRequests(string $method, ?string $origin): void
	{
		MainBootHookReceiver::assertOriginAllowed(self::context($method, $origin));

		$this->addToAssertionCount(1);
	}

	public static function provideAllowedRequestsCases(): iterable
	{
		yield 'same origin' => ['POST', 'http://localhost'];

		yield 'no origin' => ['POST', null];

		yield 'preflight' => ['OPTIONS', 'https://evil.example'];
	}

	private static function context(string $method, ?string $origin): Context
	{
		$env = ['REQUEST_METHOD' => $method];

		if (null !== $origin) {
			$env['HTTP_ORIGIN'] = $origin;
		}

		return new Context(HTTPEnvironment::mock($env), null, Context::root());
	}
}
