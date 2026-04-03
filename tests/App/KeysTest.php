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

use InvalidArgumentException;
use OZONE\Core\App\Keys;
use PHPUnit\Framework\TestCase;

/**
 * Class KeysTest.
 *
 * @internal
 *
 * @coversNothing
 */
final class KeysTest extends TestCase
{
	public function testId32KeyReturns32HexChars(): void
	{
		$key = Keys::id32();
		self::assertSame(32, \strlen($key));
		self::assertMatchesRegularExpression('/^[0-9a-f]{32}$/', $key);
	}

	public function testId32KeyIsUnique(): void
	{
		self::assertNotSame(Keys::id32(), Keys::id32());
	}

	public function testId64KeyReturns64HexChars(): void
	{
		$key = Keys::id64();
		self::assertSame(64, \strlen($key));
		self::assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $key);
	}

	public function testId64KeyIsUnique(): void
	{
		self::assertNotSame(Keys::id64(), Keys::id64());
	}

	public function testNewFileKeyReturns32HexChars(): void
	{
		$key = Keys::newFileKey();
		self::assertSame(32, \strlen($key));
		self::assertMatchesRegularExpression('/^[0-9a-f]{32}$/', $key);
	}

	public function testNewFileKeyIsUnique(): void
	{
		self::assertNotSame(Keys::newFileKey(), Keys::newFileKey());
	}

	public function testNewSessionIDReturns64HexChars(): void
	{
		$id = Keys::newSessionID();
		self::assertSame(64, \strlen($id));
		self::assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $id);
	}

	public function testNewSessionIDIsUnique(): void
	{
		self::assertNotSame(Keys::newSessionID(), Keys::newSessionID());
	}

	public function testNewSessionTokenReturns64HexChars(): void
	{
		$tok = Keys::newSessionToken();
		self::assertSame(64, \strlen($tok));
		self::assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $tok);
	}

	public function testNewAuthCodeNumericDefault(): void
	{
		$code = Keys::newAuthCode(6);
		self::assertSame(6, \strlen($code));
		self::assertMatchesRegularExpression('/^[0-9]{6}$/', $code);
	}

	public function testNewAuthCodeAlphaNum(): void
	{
		$code = Keys::newAuthCode(8, true);
		self::assertSame(8, \strlen($code));
		self::assertMatchesRegularExpression('/^[a-zA-Z0-9]{8}$/', $code);
	}

	public function testNewAuthCodeDefaultLengthIsFour(): void
	{
		$code = Keys::newAuthCode();
		self::assertSame(4, \strlen($code));
	}

	/**
	 * @dataProvider provideNewAuthCodeThrowsForInvalidLengthCases
	 */
	public function testNewAuthCodeThrowsForInvalidLength(int $length): void
	{
		$this->expectException(InvalidArgumentException::class);
		Keys::newAuthCode($length);
	}

	public static function provideNewAuthCodeThrowsForInvalidLengthCases(): iterable
	{
		return [[3], [33], [0], [-1]];
	}

	public function testNewAuthTokenReturns64HexChars(): void
	{
		$tok = Keys::newAuthToken();
		self::assertSame(64, \strlen($tok));
		self::assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $tok);
	}

	public function testNewAuthTokenIsUnique(): void
	{
		self::assertNotSame(Keys::newAuthToken(), Keys::newAuthToken());
	}

	public function testNewAuthRefreshKeyReturns64HexChars(): void
	{
		$key = Keys::newAuthRefreshKey('some-auth-ref');
		self::assertSame(64, \strlen($key));
		self::assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $key);
	}

	public function testNewAuthReferenceReturns64HexChars(): void
	{
		$ref = Keys::newAuthReference();
		self::assertSame(64, \strlen($ref));
		self::assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $ref);
	}

	public function testNewSaltReturnsA64CharString(): void
	{
		$salt = Keys::newSalt();
		self::assertSame(64, \strlen($salt));
	}

	public function testNewSecretReturnsA64CharString(): void
	{
		$secret = Keys::newSecret();
		self::assertSame(64, \strlen($secret));
	}
}
