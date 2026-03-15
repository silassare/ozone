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
use OZONE\Core\Columns\Types\TypePhone;
use PHPUnit\Framework\TestCase;

/**
 * Class TypePhoneTest.
 *
 * @internal
 *
 * @coversNothing
 */
final class TypePhoneTest extends TestCase
{
	// -------------------------------------------------------------------------
	// Valid values
	// -------------------------------------------------------------------------

	public function testAcceptsMinimumLengthE164(): void
	{
		// + 6 digits = 7 chars total
		$type  = new TypePhone();
		$clean = $type->validate('+123456')->getCleanValue();
		self::assertSame('+123456', $clean);
	}

	public function testAcceptsMaximumLengthE164(): void
	{
		// + 15 digits = 16 chars total -- previously rejected at max=15
		$type  = new TypePhone();
		$clean = $type->validate('+123456789012345')->getCleanValue();
		self::assertSame('+123456789012345', $clean);
	}

	public function testStripsInternalSpaces(): void
	{
		// TypePhone strips spaces before the base type pattern check
		$type  = new TypePhone();
		$clean = $type->validate('+1234567890')->getCleanValue();
		self::assertSame('+1234567890', $clean);
	}

	public function testNullableAcceptsNull(): void
	{
		$type  = (new TypePhone())->nullable();
		$clean = $type->validate(null)->getCleanValue();
		self::assertNull($clean);
	}

	// -------------------------------------------------------------------------
	// Invalid values
	// -------------------------------------------------------------------------

	public function testRejectsPhoneWithoutPlus(): void
	{
		$this->expectException(TypesInvalidValueException::class);
		(new TypePhone())->validate('1234567');
	}

	public function testRejectsTooFewDigits(): void
	{
		// + 5 digits = below the 6-digit minimum
		$this->expectException(TypesInvalidValueException::class);
		(new TypePhone())->validate('+12345');
	}

	public function testRejectsTooManyDigits(): void
	{
		// + 16 digits = 17 chars, beyond the 15-digit E.164 maximum
		$this->expectException(TypesInvalidValueException::class);
		(new TypePhone())->validate('+1234567890123456');
	}

	public function testRejectsAlphaCharacters(): void
	{
		$this->expectException(TypesInvalidValueException::class);
		(new TypePhone())->validate('+1234abc890');
	}

	public function testRejectsEmptyStringWhenNotNullable(): void
	{
		$this->expectException(TypesInvalidValueException::class);
		(new TypePhone())->validate('');
	}
}
