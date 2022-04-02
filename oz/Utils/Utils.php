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

namespace OZONE\OZ\Utils;

use OZONE\OZ\Core\Configs;

/**
 * Class Utils.
 */
class Utils
{
	/**
	 * Cleans a given string.
	 *
	 * @param null|string $str the string to clean
	 *
	 * @return string
	 */
	public static function cleanStrForDb(string $str = null): string
	{
		if (\is_numeric($str)) {
			return $str;
		}

		if (!\is_string($str)) {
			return '';
		}

		$db_charset = Configs::get('oz.db', 'OZ_DB_CHARSET');

		if ('utf8' === \strtolower($db_charset)) {
			// https://stackoverflow.com/a/34637891/6584810
			// this will handle emoji code, if we are using utf8 as DB charset
			// and the utf8 used by MySql support characters up to U+FFFF
			// but Most emoji use code points higher than U+FFFF.
			// use json_decode (JavaScript: JSON.parse) to get the Emoji back to life
			$str = \json_encode($str);
			// remove quote added by json_encode
			$str = \substr($str, 1, -1);
		}

		// convert some chars to html entities
		// third arg is 'false' to prevent for example: &amp; to become &amp;amp;
		return \htmlentities($str, \ENT_QUOTES, 'UTF-8', false);
	}

	/**
	 * Cleans or flushes output buffers up to target level.
	 *
	 * Resulting level can be greater than target level if a non-removable buffer has been encountered.
	 */
	public static function closeOutputBuffers(int $target_level, bool $flush): void
	{
		$status = \ob_get_status(true);
		$level  = \count($status);
		$flags  = \PHP_OUTPUT_HANDLER_REMOVABLE | ($flush ? \PHP_OUTPUT_HANDLER_FLUSHABLE : \PHP_OUTPUT_HANDLER_CLEANABLE);

		while ($level-- > $target_level && ($s = $status[$level]) && ($s['del'] ?? (!isset($s['flags']) || ($s['flags'] & $flags) === $flags))) {
			if ($flush) {
				\ob_end_flush();
			} else {
				\ob_end_clean();
			}
		}
	}
}
