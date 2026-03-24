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
use OZONE\Core\Columns\Types\TypeUrl;
use PHPUnit\Framework\TestCase;

/**
 * Class TypeUrlTest.
 *
 * @internal
 *
 * @coversNothing
 */
final class TypeUrlTest extends TestCase
{
	public function testAcceptsHttpUrl(): void
	{
		$type  = new TypeUrl();
		$clean = $type->validate('http://example.com/path?q=1#frag')->getCleanValue();
		self::assertSame('http://example.com/path?q=1#frag', $clean);
	}

	public function testAcceptsHttpsUrl(): void
	{
		$type  = new TypeUrl();
		$clean = $type->validate('https://example.com/')->getCleanValue();
		self::assertSame('https://example.com/', $clean);
	}

	public function testNullableAcceptsNull(): void
	{
		$type  = (new TypeUrl())->nullable();
		$clean = $type->validate(null)->getCleanValue();
		self::assertNull($clean);
	}

	public function testAbsolutePathAcceptedWhenAllowed(): void
	{
		$type  = (new TypeUrl())->allowAbsolutePath();
		$clean = $type->validate('/some/path')->getCleanValue();
		self::assertSame('/some/path', $clean);
	}

	public function testAbsolutePathWithQueryAndFragment(): void
	{
		$type  = (new TypeUrl())->allowAbsolutePath();
		$clean = $type->validate('/search?q=hello#results')->getCleanValue();
		self::assertSame('/search?q=hello#results', $clean);
	}

	public function testAbsolutePathRejectedWhenNotAllowed(): void
	{
		$this->expectException(TypesInvalidValueException::class);
		(new TypeUrl())->validate('/absolute/path');
	}

	public function testProtocolRelativeUrlRejectedAsAbsolutePath(): void
	{
		// //example.com starts with // -> not treated as absolute path and fails FILTER_VALIDATE_URL
		$this->expectException(TypesInvalidValueException::class);
		(new TypeUrl())->allowAbsolutePath()->validate('//example.com');
	}

	public function testRejectsPlainWord(): void
	{
		$this->expectException(TypesInvalidValueException::class);
		(new TypeUrl())->validate('notaurl');
	}

	public function testRejectsMissingScheme(): void
	{
		$this->expectException(TypesInvalidValueException::class);
		(new TypeUrl())->validate('example.com/path');
	}

	public function testRejectsEmptyStringWhenNotNullable(): void
	{
		$this->expectException(TypesInvalidValueException::class);
		(new TypeUrl())->validate('');
	}

	public function testRejectsAbsolutePathWithSpaces(): void
	{
		$this->expectException(TypesInvalidValueException::class);
		(new TypeUrl())->allowAbsolutePath()->validate('/path with spaces');
	}
}
