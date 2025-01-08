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

namespace OZONE\Core\Utils;

use InvalidArgumentException;
use OZONE\Core\Exceptions\RuntimeException;
use Throwable;

/**
 * Class Random.
 */
final class Random
{
	public const CHARS_ALPHA = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';

	public const CHARS_NUM = '0123456789';

	public const CHARS_SYMBOLS = '~!@#$£µ§²¨%^&()_-+={}[]:";\'<>?,./\\';

	public const CHARS_ALPHA_NUM = self::CHARS_ALPHA . self::CHARS_NUM;

	public const CHARS_ALL = self::CHARS_ALPHA_NUM . self::CHARS_SYMBOLS;

	/**
	 * Generate random file name.
	 *
	 * @param string $prefix
	 * @param bool   $readable_date
	 *
	 * @return string
	 */
	public static function fileName(string $prefix = 'oz', bool $readable_date = true): string
	{
		$hash = self::string(8, self::CHARS_ALPHA_NUM);
		$date = $readable_date ? \date('Y-m-d-H-i-s') : \time();

		return \sprintf('%s-%s-%s', $prefix, $date, $hash);
	}

	/**
	 * Generate a random integer.
	 *
	 * @param int $min
	 * @param int $max
	 *
	 * @return int
	 */
	public static function int(int $min = 0, int $max = \PHP_INT_MAX): int
	{
		try {
			return \random_int($min, $max);
		} catch (Throwable $t) {
			throw new RuntimeException('Unable to generate a secure random int.', null, $t);
		}
	}

	/**
	 * Randomly return true or false.
	 *
	 * @param int $frequency
	 *
	 * @return bool
	 */
	public static function bool(int $frequency = 10): bool
	{
		return (bool) (self::int(0, \max($frequency, 1)) % 2);
	}

	/**
	 * Generate a random string with a given length.
	 *
	 * @param int    $length The desired random string length default 32 range is [1,512]
	 * @param string $chars  The chars to use
	 *
	 * @return string
	 */
	public static function string(int $length = 32, string $chars = self::CHARS_ALL): string
	{
		$min = 1;
		$max = 512;

		if ($length < $min || $length > $max) {
			throw new InvalidArgumentException(\sprintf('Random string length must be between %d and %d.', $min, $max));
		}

		if (\strlen($chars) < 2) {
			throw new InvalidArgumentException('Require at least 2 chars to generate random string.');
		}

		$chars_length = \strlen($chars) - 1;
		$string       = '';

		for ($i = 0; $i < $length; ++$i) {
			$string .= $chars[self::int(0, $chars_length)];
		}

		return $string;
	}

	/**
	 * Generate a random alpha string with a given length.
	 *
	 * @param int $length
	 *
	 * @return string
	 */
	public static function alpha(int $length = 32): string
	{
		return self::string($length, self::CHARS_ALPHA);
	}

	/**
	 * Generate a random numeric string with a given length.
	 *
	 * @param int $length
	 *
	 * @return string
	 */
	public static function num(int $length = 32): string
	{
		return self::string($length, self::CHARS_NUM);
	}

	/**
	 * Generate a random alpha numeric string with a given length.
	 *
	 * @param int $length
	 *
	 * @return string
	 */
	public static function alphaNum(int $length = 32): string
	{
		return self::string($length, self::CHARS_ALPHA_NUM);
	}
}
