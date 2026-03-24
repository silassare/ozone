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

namespace OZONE\Tests\Columns;

use Gobl\DBAL\Types\Exceptions\TypesInvalidValueException;
use OZONE\Core\Columns\Types\TypePassword;
use PHPUnit\Framework\TestCase;

/**
 * Class TypePasswordTest.
 *
 * @internal
 *
 * @coversNothing
 */
final class TypePasswordTest extends TestCase
{
	// Default OZ_USER_PASS_MIN_LENGTH = 6, OZ_USER_PASS_MAX_LENGTH = 60

	public function testAcceptsValidPassword(): void
	{
		$type    = new TypePassword();
		$subject = $type->validate('secret123');
		self::assertSame('secret123', $subject->getCleanValue());
	}

	public function testAcceptsMinLengthPassword(): void
	{
		$type = new TypePassword();
		self::assertSame('sixchr', $type->validate('sixchr')->getCleanValue());
	}

	public function testNullableAcceptsNull(): void
	{
		$type  = (new TypePassword())->nullable();
		$clean = $type->validate(null)->getCleanValue();
		self::assertNull($clean);
	}

	public function testSecureModeAcceptsComplexPassword(): void
	{
		$type  = (new TypePassword())->secure();
		$clean = $type->validate('Abc1!xyz')->getCleanValue();
		self::assertSame('Abc1!xyz', $clean);
	}

	public function testSecureModeRejectsAllLowercase(): void
	{
		$this->expectException(TypesInvalidValueException::class);
		(new TypePassword())->secure()->validate('alllowercase');
	}

	public function testSecureModeRejectsNoDigit(): void
	{
		$this->expectException(TypesInvalidValueException::class);
		(new TypePassword())->secure()->validate('NoDigitHere!');
	}

	public function testSecureModeRejectsNoSpecialChar(): void
	{
		$this->expectException(TypesInvalidValueException::class);
		(new TypePassword())->secure()->validate('NoSpecial1');
	}

	public function testCustomMinEnforced(): void
	{
		$this->expectException(TypesInvalidValueException::class);
		(new TypePassword())->min(10)->validate('short');
	}

	public function testCustomMaxEnforced(): void
	{
		$this->expectException(TypesInvalidValueException::class);
		(new TypePassword())->max(8)->validate('toolongpassword');
	}

	public function testRejectsTooShortPassword(): void
	{
		// below default min of 6
		$this->expectException(TypesInvalidValueException::class);
		(new TypePassword())->validate('abc');
	}

	public function testRejectsEmptyStringWhenNotNullable(): void
	{
		$this->expectException(TypesInvalidValueException::class);
		(new TypePassword())->validate('');
	}
}
