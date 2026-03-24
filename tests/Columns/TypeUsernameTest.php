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
use OZONE\Core\Columns\Types\TypeUsername;
use PHPUnit\Framework\TestCase;

/**
 * Class TypeUsernameTest.
 *
 * @internal
 *
 * @coversNothing
 */
final class TypeUsernameTest extends TestCase
{
	// OZ_USER_NAME_MIN_LENGTH defaults to 3, OZ_USER_NAME_MAX_LENGTH defaults to 60.

	public function testAcceptsValidUsername(): void
	{
		$type  = new TypeUsername();
		$clean = $type->validate('Alice')->getCleanValue();
		self::assertSame('Alice', $clean);
	}

	public function testAcceptsMinLengthUsername(): void
	{
		$type  = new TypeUsername();
		$clean = $type->validate('Bob')->getCleanValue();
		self::assertSame('Bob', $clean);
	}

	public function testNullableAcceptsNull(): void
	{
		$type  = (new TypeUsername())->nullable();
		$clean = $type->validate(null)->getCleanValue();
		self::assertNull($clean);
	}

	/**
	 * Before the fix, '  a  ' (5 chars with padding) passed the min=3 length
	 * check but would be stored as 'a' (1 char, below minimum). After the fix,
	 * trimming happens first so the actual stored length is checked.
	 */
	public function testPaddedShortUsernameIsRejectedAfterTrim(): void
	{
		// '  a  ' trims to 'a' which is below the min of 3
		$this->expectException(TypesInvalidValueException::class);
		(new TypeUsername())->validate('  a  ');
	}

	/**
	 * A padded-but-valid-length username should be trimmed and accepted.
	 * '  Alice  ' trims to 'Alice' (5 chars, above min=3).
	 */
	public function testPaddedValidUsernameIsTrimmedAndAccepted(): void
	{
		$type  = new TypeUsername();
		$clean = $type->validate('  Alice  ')->getCleanValue();
		self::assertSame('Alice', $clean);
	}

	public function testRejectsTooShortUsername(): void
	{
		// 'ab' is 2 chars, below the default min of 3
		$this->expectException(TypesInvalidValueException::class);
		(new TypeUsername())->validate('ab');
	}

	public function testRejectsTooLongUsername(): void
	{
		// 61 chars, above the default max of 60
		$this->expectException(TypesInvalidValueException::class);
		(new TypeUsername())->validate(\str_repeat('x', 61));
	}

	public function testRejectsEmptyStringWhenNotNullable(): void
	{
		$this->expectException(TypesInvalidValueException::class);
		(new TypeUsername())->validate('');
	}
}
