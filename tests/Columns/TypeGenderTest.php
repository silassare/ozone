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
use OZONE\Core\Columns\Types\TypeGender;
use PHPUnit\Framework\TestCase;

/**
 * Class TypeGenderTest.
 *
 * @internal
 *
 * @coversNothing
 */
final class TypeGenderTest extends TestCase
{
	// Default OZ_USER_ALLOWED_GENDERS: ['Male', 'Female', 'None', 'Other']

	// -------------------------------------------------------------------------
	// Valid values
	// -------------------------------------------------------------------------

	public function testAcceptsMale(): void
	{
		$type  = new TypeGender();
		$clean = $type->validate('Male')->getCleanValue();
		self::assertSame('Male', $clean);
	}

	public function testAcceptsFemale(): void
	{
		$type  = new TypeGender();
		$clean = $type->validate('Female')->getCleanValue();
		self::assertSame('Female', $clean);
	}

	public function testAcceptsNone(): void
	{
		$type  = new TypeGender();
		$clean = $type->validate('None')->getCleanValue();
		self::assertSame('None', $clean);
	}

	public function testAcceptsOther(): void
	{
		$type  = new TypeGender();
		$clean = $type->validate('Other')->getCleanValue();
		self::assertSame('Other', $clean);
	}

	public function testNullableAcceptsNull(): void
	{
		$type  = (new TypeGender())->nullable();
		$clean = $type->validate(null)->getCleanValue();
		self::assertNull($clean);
	}

	// -------------------------------------------------------------------------
	// Invalid values
	// -------------------------------------------------------------------------

	public function testRejectsCaseMismatch(): void
	{
		// The allowed list is case-sensitive
		$this->expectException(TypesInvalidValueException::class);
		(new TypeGender())->validate('male');
	}

	public function testRejectsArbitraryString(): void
	{
		$this->expectException(TypesInvalidValueException::class);
		(new TypeGender())->validate('Unknown');
	}

	public function testRejectsEmptyStringWhenNotNullable(): void
	{
		$this->expectException(TypesInvalidValueException::class);
		(new TypeGender())->validate('');
	}
}
