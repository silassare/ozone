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

namespace OZONE\Tests\CSRF;

use OZONE\Core\App\Settings;
use OZONE\Core\CSRF\CSRF;
use OZONE\Core\Http\Enums\RequestScope;
use PHPUnit\Framework\TestCase;

/**
 * Class CSRFTest.
 *
 * @internal
 *
 * @covers \OZONE\Core\CSRF\CSRF
 */
final class CSRFTest extends TestCase
{
	public function testGeneratedTokenIsValid(): void
	{
		$csrf = self::csrf();

		self::assertTrue($csrf->isValid($csrf->generateToken()));
	}

	public function testTokenExpires(): void
	{
		$csrf     = self::csrf();
		$token    = $csrf->generateToken();
		$lifetime = (int) Settings::get('oz.request', 'OZ_CSRF_TOKEN_LIFETIME');

		self::assertTrue($csrf->isValid($token, \time() + $lifetime - 5));
		self::assertFalse($csrf->isValid($token, \time() + $lifetime + 5));
	}

	public function testTokenFromTheFutureIsRejected(): void
	{
		$csrf = self::csrf();

		self::assertFalse($csrf->isValid($csrf->generateToken(), \time() - 3600));
	}

	public function testTamperedOrMalformedTokensAreRejected(): void
	{
		$csrf                   = self::csrf();
		[$id, $issued_at, $mac] = \explode(CSRF::TOKEN_SEP, $csrf->generateToken());

		self::assertFalse($csrf->isValid($id . '.' . ((int) $issued_at + 1) . '.' . $mac));
		self::assertFalse($csrf->isValid($id . 'x.' . $issued_at . '.' . $mac));
		self::assertFalse($csrf->isValid($id . '.' . $mac));
		self::assertFalse($csrf->isValid(''));
		self::assertFalse($csrf->isValid('a.b.c'));
	}

	public function testTokenIsBoundToItsScope(): void
	{
		$token = self::csrf(RequestScope::HOST)->generateToken();

		self::assertFalse(self::csrf(RequestScope::USER_IP)->isValid($token));
	}

	private static function csrf(RequestScope $scope = RequestScope::HOST): CSRF
	{
		return new CSRF(context(), $scope);
	}
}
