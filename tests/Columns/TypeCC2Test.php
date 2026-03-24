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
use OZONE\Core\Columns\Types\TypeCC2;
use PHPUnit\Framework\TestCase;

/**
 * Class TypeCC2Test.
 *
 * @internal
 *
 * @coversNothing
 */
final class TypeCC2Test extends TestCase
{
	public function testAcceptsUpperCaseCode(): void
	{
		$type  = new TypeCC2();
		$clean = $type->validate('US')->getCleanValue();
		self::assertSame('US', $clean);
	}

	public function testNormalizesToUpperCase(): void
	{
		$type  = new TypeCC2();
		$clean = $type->validate('us')->getCleanValue();
		self::assertSame('US', $clean);
	}

	public function testAcceptsMixedCase(): void
	{
		$type  = new TypeCC2();
		$clean = $type->validate('Fr')->getCleanValue();
		self::assertSame('FR', $clean);
	}

	public function testNullableAcceptsNull(): void
	{
		$type  = (new TypeCC2())->nullable();
		$clean = $type->validate(null)->getCleanValue();
		self::assertNull($clean);
	}

	public function testRejectsSingleChar(): void
	{
		$this->expectException(TypesInvalidValueException::class);
		(new TypeCC2())->validate('U');
	}

	public function testRejectsThreeCharCode(): void
	{
		$this->expectException(TypesInvalidValueException::class);
		(new TypeCC2())->validate('USA');
	}

	public function testRejectsNumericCode(): void
	{
		$this->expectException(TypesInvalidValueException::class);
		(new TypeCC2())->validate('12');
	}

	public function testRejectsEmptyStringWhenNotNullable(): void
	{
		$this->expectException(TypesInvalidValueException::class);
		(new TypeCC2())->validate('');
	}
}
