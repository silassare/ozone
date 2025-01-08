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

/**
 * Class Hasher.
 */
final class Hasher
{
	/**
	 * Returns a 32 string length hash of a given string.
	 *
	 * When string is null or empty (length === 0) a random hash is generated.
	 *
	 * @param null|string $string The string to hash
	 *
	 * @return string
	 */
	public static function hash32(?string $string = null): string
	{
		if (null === $string || '' === $string) {
			$string = Random::string(64) . \microtime();
		}

		return \md5(\hash('sha256', $string));
	}

	/**
	 * Returns a 64 string length hash of a given string.
	 *
	 * When string is null or empty (length === 0) a random hash is generated.
	 *
	 * @param null|string $string The string to hash
	 *
	 * @return string
	 */
	public static function hash64(?string $string = null): string
	{
		if (null === $string || '' === $string) {
			$string = Random::string(64) . \microtime();
		}

		return \hash('sha256', $string);
	}

	/**
	 * Shorten url (or any string).
	 *
	 * This is to shorten url string but it can also shorten any string.
	 *
	 * @param string $str
	 *
	 * @return string
	 */
	public static function shorten(string $str): string
	{
		$n         = \crc32($str);
		$chars     = Random::CHARS_ALPHA_NUM;
		$base      = \strlen($chars);
		$converted = '';

		while ($n > 0) {
			$converted = $chars[$n % $base] . $converted;
			$n         = \floor($n / $base);
		}

		return $converted;
	}
}
