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
use OZONE\Core\Columns\Types\TypeEmail;
use PHPUnit\Framework\TestCase;

/**
 * Class TypeEmailTest.
 *
 * @internal
 *
 * @coversNothing
 */
final class TypeEmailTest extends TestCase
{
	// -------------------------------------------------------------------------
	// Valid values
	// -------------------------------------------------------------------------

	public function testAcceptsValidEmail(): void
	{
		$type  = new TypeEmail();
		$clean = $type->validate('user@example.com')->getCleanValue();
		self::assertSame('user@example.com', $clean);
	}

	public function testAcceptsEmailWithSubdomain(): void
	{
		$type  = new TypeEmail();
		$clean = $type->validate('u@mail.example.org')->getCleanValue();
		self::assertSame('u@mail.example.org', $clean);
	}

	public function testAcceptsEmailWithPlus(): void
	{
		$type  = new TypeEmail();
		$clean = $type->validate('user+tag@example.com')->getCleanValue();
		self::assertSame('user+tag@example.com', $clean);
	}

	public function testNullableAcceptsNull(): void
	{
		$type  = (new TypeEmail())->nullable();
		$clean = $type->validate(null)->getCleanValue();
		self::assertNull($clean);
	}

	// -------------------------------------------------------------------------
	// Invalid values
	// -------------------------------------------------------------------------

	public function testRejectsMissingAtSign(): void
	{
		$this->expectException(TypesInvalidValueException::class);
		(new TypeEmail())->validate('userexample.com');
	}

	public function testRejectsMissingDomain(): void
	{
		$this->expectException(TypesInvalidValueException::class);
		(new TypeEmail())->validate('user@');
	}

	public function testRejectsPlainString(): void
	{
		$this->expectException(TypesInvalidValueException::class);
		(new TypeEmail())->validate('not-an-email');
	}

	public function testRejectsEmptyStringWhenNotNullable(): void
	{
		$this->expectException(TypesInvalidValueException::class);
		(new TypeEmail())->validate('');
	}
}
