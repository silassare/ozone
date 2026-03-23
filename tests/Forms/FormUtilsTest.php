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

namespace OZONE\Tests\Forms;

use InvalidArgumentException;
use OZONE\Core\Forms\FormUtils;
use PHPUnit\Framework\TestCase;

/**
 * Class FormUtilsTest.
 *
 * @internal
 *
 * @coversNothing
 */
final class FormUtilsTest extends TestCase
{
	/**
	 * @dataProvider provideAssertValidFieldNamePassesForValidNamesCases
	 */
	public function testAssertValidFieldNamePassesForValidNames(string $name): void
	{
		// Should not throw
		FormUtils::assertValidFieldName($name);
		$this->addToAssertionCount(1);
	}

	public static function provideAssertValidFieldNamePassesForValidNamesCases(): iterable
	{
		return [
			['name'],
			['user_name'],
			['user.name'],
			['address.street'],
			['a.b.c'],
			['level1.level2.level3'],
		];
	}

	/**
	 * @dataProvider provideAssertValidFieldNameThrowsForInvalidNamesCases
	 */
	public function testAssertValidFieldNameThrowsForInvalidNames(string $name): void
	{
		$this->expectException(InvalidArgumentException::class);
		FormUtils::assertValidFieldName($name);
	}

	public static function provideAssertValidFieldNameThrowsForInvalidNamesCases(): iterable
	{
		return [
			[''],
			['.'],
			['foo.'],
			['.foo'],
			['foo..bar'],
		];
	}
}
