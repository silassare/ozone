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

namespace OZONE\Tests\Crypt;

use OZONE\Core\Crypt\Password;
use PHPUnit\Framework\TestCase;

/**
 * Class PasswordTest.
 *
 * @internal
 *
 * @coversNothing
 */
final class PasswordTest extends TestCase
{
	public function testIsHashReturnsFalseForPlainText(): void
	{
		self::assertFalse(Password::isHash('my-plain-password'));
		self::assertFalse(Password::isHash(''));
		self::assertFalse(Password::isHash('1234'));
	}

	public function testIsHashReturnsTrueForBcryptHash(): void
	{
		$hash = \password_hash('secret', \PASSWORD_BCRYPT);
		self::assertTrue(Password::isHash($hash));
	}

	public function testHashReturnsAValidBcryptHash(): void
	{
		$pass = 'my-super-secret';
		$hash = Password::hash($pass);

		self::assertTrue(Password::isHash($hash));
		self::assertTrue(\password_verify($pass, $hash));
	}

	public function testHashProducesDifferentHashesForSamePassword(): void
	{
		$pass  = 'same-password';
		$hash1 = Password::hash($pass);
		$hash2 = Password::hash($pass);

		// bcrypt produces different hashes due to random salt
		self::assertNotSame($hash1, $hash2);
	}

	public function testVerifyReturnsTrueForCorrectPassword(): void
	{
		$pass = 'correct-horse-battery-staple';
		$hash = Password::hash($pass);

		self::assertTrue(Password::verify($pass, $hash));
	}

	public function testVerifyReturnsFalseForWrongPassword(): void
	{
		$hash = Password::hash('real-password');

		self::assertFalse(Password::verify('wrong-password', $hash));
	}

	public function testVerifyReturnsFalseForPlainTextHash(): void
	{
		self::assertFalse(Password::verify('any', 'not-a-hash'));
	}

	public function testHashHandlesLongPasswordBeyond72Chars(): void
	{
		// bcrypt truncates at 72 bytes; Password class resizes to avoid collisions
		$base      = \str_repeat('a', 72);
		$passLong1 = $base . 'X';
		$passLong2 = $base . 'Y';

		$hash1 = Password::hash($passLong1);
		$hash2 = Password::hash($passLong2);

		self::assertTrue(Password::verify($passLong1, $hash1));
		self::assertTrue(Password::verify($passLong2, $hash2));
		// They must NOT match each other despite sharing first 72 chars
		self::assertFalse(Password::verify($passLong1, $hash2));
		self::assertFalse(Password::verify($passLong2, $hash1));
	}
}
