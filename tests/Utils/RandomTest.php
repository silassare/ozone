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

namespace OZONE\Tests\Utils;

use InvalidArgumentException;
use OZONE\Core\Utils\Random;
use PHPUnit\Framework\TestCase;

/**
 * Class RandomTest.
 *
 * @internal
 *
 * @coversNothing
 */
final class RandomTest extends TestCase
{
	public function testStringReturnsCorrectLength(): void
	{
		for ($len = 1; $len <= 64; $len += 7) {
			self::assertSame($len, \strlen(Random::string($len)));
		}
	}

	public function testStringOnlyContainsRequestedChars(): void
	{
		$chars  = 'abc123';
		$result = Random::string(32, $chars);
		self::assertMatchesRegularExpression('/^[abc123]+$/', $result);
	}

	public function testStringThrowsForInvalidLength(): void
	{
		$this->expectException(InvalidArgumentException::class);
		Random::string(0);
	}

	public function testStringThrowsForLengthAboveMax(): void
	{
		$this->expectException(InvalidArgumentException::class);
		Random::string(513);
	}

	public function testStringThrowsForSingleCharSet(): void
	{
		$this->expectException(InvalidArgumentException::class);
		Random::string(8, 'a');
	}

	public function testAlphaReturnsOnlyAlphaChars(): void
	{
		$result = Random::alpha(32);
		self::assertSame(32, \strlen($result));
		self::assertMatchesRegularExpression('/^[a-zA-Z]+$/', $result);
	}

	public function testNumReturnsOnlyDigits(): void
	{
		$result = Random::num(16);
		self::assertSame(16, \strlen($result));
		self::assertMatchesRegularExpression('/^[0-9]+$/', $result);
	}

	public function testAlphaNumReturnsOnlyAlphanumChars(): void
	{
		$result = Random::alphaNum(32);
		self::assertSame(32, \strlen($result));
		self::assertMatchesRegularExpression('/^[a-zA-Z0-9]+$/', $result);
	}

	public function testIntReturnsValueWithinRange(): void
	{
		for ($i = 0; $i < 20; ++$i) {
			$value = Random::int(10, 20);
			self::assertGreaterThanOrEqual(10, $value);
			self::assertLessThanOrEqual(20, $value);
		}
	}

	public function testBoolReturnsBooleanType(): void
	{
		// run enough times to exercise both branches
		$trueFound  = false;
		$falseFound = false;

		for ($i = 0; $i < 200; ++$i) {
			$v = Random::bool();

			if ($v) {
				$trueFound = true;
			} else {
				$falseFound = true;
			}

			if ($trueFound && $falseFound) {
				break;
			}
		}

		self::assertTrue($trueFound, 'bool() never returned true in 200 tries');
		self::assertTrue($falseFound, 'bool() never returned false in 200 tries');
	}

	public function testFileNameDefaultFormat(): void
	{
		$name = Random::fileName();
		// expected: oz-YYYY-MM-DD-HH-II-SS-xxxxxxxx
		self::assertMatchesRegularExpression(
			'/^oz-\d{4}-\d{2}-\d{2}-\d{2}-\d{2}-\d{2}-[a-zA-Z0-9]{8}$/',
			$name
		);
	}

	public function testFileNameWithCustomPrefix(): void
	{
		$name = Random::fileName('upload');
		self::assertStringStartsWith('upload-', $name);
	}

	public function testFileNameWithoutReadableDate(): void
	{
		$name = Random::fileName('oz', false);
		// format: oz-<unix_timestamp>-xxxxxxxx
		self::assertMatchesRegularExpression('/^oz-\d+-[a-zA-Z0-9]{8}$/', $name);
	}
}
