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

namespace OZONE\OZ\Core;

use InvalidArgumentException;
use OZONE\OZ\Exceptions\RuntimeException;
use Throwable;

/**
 * Class Hasher.
 */
final class Hasher
{
	public const CHARS_ALPHA = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';

	public const CHARS_NUM = '0123456789';

	public const CHARS_SYMBOLS = '~!@#$£µ§²¨%^&()_-+={}[]:";\'<>?,./\\';

	public const CHARS_ALPHA_NUM = self::CHARS_ALPHA . self::CHARS_NUM;

	public const CHARS_ALL = self::CHARS_ALPHA_NUM . self::CHARS_SYMBOLS;

	/**
	 * Generate file key.
	 *
	 * @return string
	 */
	public static function genFileKey(): string
	{
		// make sure to make differences between each cloned file key
		// if not, all clone will have the same file_key as the original file
		return self::hash32(self::randomString() . \microtime() . self::getSalt('OZ_FILE_SALT'));
	}

	/**
	 * Generate random file name.
	 *
	 * @param string $prefix
	 * @param bool   $readable_date
	 *
	 * @return string
	 */
	public static function genFileName(string $prefix = 'oz', bool $readable_date = true): string
	{
		$hash = self::randomString(8, self::CHARS_ALPHA_NUM);
		$date = $readable_date ? \date('Y-m-d-H-i-s') : \time();

		return \sprintf('%s-%s-%s', $prefix, $date, $hash);
	}

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
			$string = self::randomString(64) . \microtime();
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
			$string = self::randomString(64) . \microtime();
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
		$chars     = self::CHARS_ALPHA_NUM;
		$base      = \strlen($chars);
		$converted = '';

		while ($n > 0) {
			$converted = $chars[$n % $base] . $converted;
			$n         = \floor($n / $base);
		}

		return $converted;
	}

	/**
	 * Generate a random integer.
	 *
	 * @param int $min
	 * @param int $max
	 *
	 * @return int
	 */
	public static function randomInt(int $min = 0, int $max = \PHP_INT_MAX): int
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
	public static function randomBool(int $frequency = 10): bool
	{
		return (bool) (self::randomInt(0, \max($frequency, 1)) % 2);
	}

	/**
	 * Generate a random string with a given length.
	 *
	 * @param int    $length The desired random string length default 32 range is [1,512]
	 * @param string $chars  The chars to use
	 *
	 * @return string
	 */
	public static function randomString(int $length = 32, string $chars = self::CHARS_ALL): string
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
			$string .= $chars[self::randomInt(0, $chars_length)];
		}

		return $string;
	}

	/**
	 * Generate session id.
	 *
	 * @return string
	 */
	public static function genSessionID(): string
	{
		$salt = self::getSalt('OZ_SESSION_SALT');

		return self::hash64(\serialize($_SERVER) . self::randomString(128) . \microtime() . $salt);
	}

	/**
	 * Generate session token.
	 *
	 * @return string
	 */
	public static function genSessionToken(): string
	{
		$salt = self::getSalt('OZ_SESSION_SALT');

		return self::hash64(\serialize($_SERVER) . self::randomString(128) . \microtime() . $salt);
	}

	/**
	 * Generate client id for a given client url.
	 *
	 * @param string $url the client url
	 *
	 * @return string
	 */
	public static function genClientID(string $url): string
	{
		$salt = self::getSalt('OZ_DEFAULT_SALT');
		$hash = self::hash32($url . \microtime() . $salt);

		return \implode('-', \str_split(\strtoupper($hash), 8));
	}

	/**
	 * Generate auth code.
	 *
	 * @param int  $length    the auth code length
	 * @param bool $alpha_num whether to use digits or alpha_num
	 *
	 * @return string
	 */
	public static function genAuthCode(int $length = 4, bool $alpha_num = false): string
	{
		$min = 4;
		$max = 32;

		if ($length < $min || $length > $max) {
			throw new InvalidArgumentException(\sprintf('Auth code length must be between %d and %d.', $min, $max));
		}

		return self::randomString($length, $alpha_num ? self::CHARS_ALPHA_NUM : self::CHARS_NUM);
	}

	/**
	 * Generate auth token.
	 *
	 * @return string
	 */
	public static function genAuthToken(): string
	{
		$salt = self::getSalt('OZ_AUTH_SALT');

		$str = self::randomString() . $salt;

		return self::hash64($str);
	}

	/**
	 * Generate auth refresh key.
	 *
	 * @param string $auth_ref the auth reference
	 *
	 * @return string
	 */
	public static function genAuthRefreshKey(string $auth_ref): string
	{
		$salt = self::getSalt('OZ_AUTH_SALT');

		$str = $auth_ref . self::randomString() . $salt;

		return self::hash64($str);
	}

	/**
	 * Generate auth ref.
	 *
	 * @return string
	 */
	public static function genAuthReference(): string
	{
		$salt = self::getSalt('OZ_AUTH_SALT');

		$str = self::randomString() . $salt;

		return self::hash64($str);
	}

	/**
	 * Returns a salt with a given name from settings.
	 *
	 * @param string $name The 'oz.keygen.salt' settings salt key name.
	 *
	 * @return string
	 */
	public static function getSalt(string $name): string
	{
		$salt = Configs::get('oz.keygen.salt', $name);

		if (null === $salt) {
			throw new RuntimeException(\sprintf('Missing salt %s in %s', $name, 'oz.keygen.salt'));
		}

		return $salt;
	}
}
