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
 * @covers \OZONE\Core\Utils\Random
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

	public function testStringIsValidUtf8WithEveryBuiltInCharSet(): void
	{
		foreach (
			[
				'CHARS_ALPHA'     => Random::CHARS_ALPHA,
				'CHARS_NUM'       => Random::CHARS_NUM,
				'CHARS_SYMBOLS'   => Random::CHARS_SYMBOLS,
				'CHARS_ALPHA_NUM' => Random::CHARS_ALPHA_NUM,
				'CHARS_ALL'       => Random::CHARS_ALL,
			] as $name => $chars
		) {
			// The charset itself must be ASCII: a random string is drawn byte by byte, so a multi-byte
			// character would be picked one byte at a time.
			self::assertSame(
				\strlen($chars),
				\mb_strlen($chars),
				\sprintf('%s must hold single-byte characters only', $name)
			);

			for ($i = 0; $i < 50; ++$i) {
				$value = Random::string(32, $chars);
				self::assertTrue(
					\mb_check_encoding($value, 'UTF-8'),
					\sprintf('%s produced a value that is not valid UTF-8', $name)
				);
				self::assertIsString(\json_encode($value, \JSON_THROW_ON_ERROR));
			}
		}
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

	public function testBoolHonoursItsFrequency(): void
	{
		self::assertTrue(Random::bool(1));
		self::assertTrue(Random::bool(0));

		$hits = 0;

		for ($i = 0; $i < 4000; ++$i) {
			$hits += Random::bool(4) ? 1 : 0;
		}

		// 1 in 4 expected (1000 hits); the bounds are ~7 standard deviations away.
		self::assertGreaterThan(800, $hits);
		self::assertLessThan(1200, $hits);
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
