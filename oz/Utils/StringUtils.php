<?php

/**
 * Copyright (c) 2017-present, Emile Silas Sare
 *
 * This file is part of OZone (O'Zone) package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace OZONE\OZ\Utils;

use OZONE\OZ\Core\SettingsManager;

\defined('OZ_SELF_SECURITY_CHECK') || die;

final class StringUtils
{
	/**
	 * Cleans a given string.
	 *
	 * @param string $str the string to clean
	 *
	 * @return string
	 */
	public static function clean($str = null)
	{
		if (\is_numeric($str)) {
			return $str;
		}

		if (!\is_string($str)) {
			return '';
		}

		$db_charset = SettingsManager::get('oz.db', 'OZ_DB_CHARSET');

		if (\strtolower($db_charset) === 'utf8') {
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
		return \htmlentities($str, \ENT_QUOTES, 'UTF-8', false /* this prevent for example: &amp; to become &amp;amp; */);
	}

	/**
	 * removes a given prefix from a given string
	 *
	 * @param string $str
	 * @param string $prefix
	 *
	 * @return string
	 */
	public static function removePrefix($str, $prefix)
	{
		if ($str === $prefix) {
			return '';
		}

		if (\strlen($str) && \strlen($prefix) && 0 === \strpos($str, $prefix)) {
			$str = \substr($str, \strlen($prefix));
		}

		return $str;
	}

	/**
	 * removes a given suffix from a given string
	 *
	 * @param string $str
	 * @param string $suffix
	 *
	 * @return string
	 */
	public static function removeSuffix($str, $suffix)
	{
		if ($str === $suffix) {
			return '';
		}

		if (\strlen($str) && \strlen($suffix) && 0 < \strpos($str, $suffix)) {
			$str = \substr($str, 0, \strlen($str) - \strlen($suffix));
		}

		return $str;
	}

	/**
	 * Change a string from one encoding to another
	 *
	 * @param string $data your raw data
	 * @param string $from encoding of your data
	 * @param string $to   encoding you want
	 *
	 * @throws \Exception when some required modules not found
	 *
	 * @return bool|string false if error
	 */
	public static function convertEncoding($data, $from, $to = 'UTF-8')
	{
		if (\function_exists('mb_convert_encoding')) {
			// alternatives
			$alt = ['windows-949' => 'EUC-KR', 'Windows-31J' => 'SJIS'];

			$from = isset($alt[$from]) ? $alt[$from] : $from;
			$to   = isset($alt[$to]) ? $alt[$to] : $to;

			return @\mb_convert_encoding($data, $to, $from);
		}

		if (\function_exists('iconv')) {
			return @\iconv($from, $to, $data);
		}

		throw new \Exception('Make sure PHP module "iconv" or "mbstring" are installed.');
	}

	/**
	 * converts to utf8
	 *
	 * @param mixed $input the string to encode
	 *
	 * @throws \Exception
	 *
	 * @return mixed
	 */
	public static function toUtf8($input)
	{
		$from = null;

		if (\function_exists('mb_detect_encoding')) {
			$from = \mb_detect_encoding($input);
		}

		return self::convertEncoding($input, $from, 'UTF-8');
	}

	/**
	 * fix some encoding problems as we only use UTF-8
	 *
	 * @param mixed $input       the input to fix
	 * @param bool  $encode_keys whether to encode keys if input is array or object
	 *
	 * @throws \Exception
	 *
	 * @return mixed
	 */
	public static function encodeFix($input, $encode_keys = false)
	{
		$result = null;

		if (\is_string($input)) {
			$result = self::toUtf8($input);
		} elseif (\is_array($input)) {
			$result = [];

			foreach ($input as $k => $v) {
				$key          = ($encode_keys) ? self::toUtf8($k) : $k;
				$result[$key] = self::encodeFix($v, $encode_keys);
			}
		} elseif (\is_object($input)) {
			$vars = \array_keys(\get_object_vars($input));

			foreach ($vars as $var) {
				$input->$var = self::encodeFix($input->$var);
			}
		} else {
			return $input;
		}

		return $result;
	}

	/**
	 * Converts string to CamelCase.
	 *
	 * example:
	 *    foo_bar     => FooBar
	 *    foo-bar-baz => FooBarBaz
	 *
	 * @param string $str the string to convert
	 *
	 * @return string
	 */
	public static function toCamelCase($str)
	{
		$str    = \str_replace('-', '_', $str);
		$result = \implode('', \array_map('ucfirst', \explode('_', $str)));

		if (\strlen($str) > 3 && $str[2] === '_') {
			$result[1] = \strtoupper($result[1]);
		}

		return $result;
	}

	/**
	 * Creates URL Slug from string (ex: Post Title)
	 *
	 * @param string $string
	 *
	 * @return string
	 */
	public static function stringToURLSlug($string)
	{
		$string = \trim($string);
		$string = self::removeAccents($string);
		$string = \preg_replace('~[^a-zA-Z0-9-]+~', '-', $string);
		$string = \preg_replace('~[-]{2,}~', '-', $string);

		return \trim(\strtolower($string), '-');
	}

	/**
	 * Removes accents from string.
	 *
	 * @param string $string
	 *
	 * @return string
	 */
	public static function removeAccents($string)
	{
		if (!\preg_match('/[\x80-\xff]/', $string)) {
			return $string;
		}

		$chars = [
			// Decompositions for Latin-1 Supplement
			\chr(195) . \chr(128) => 'A',
			\chr(195) . \chr(129) => 'A',
			\chr(195) . \chr(130) => 'A',
			\chr(195) . \chr(131) => 'A',
			\chr(195) . \chr(132) => 'A',
			\chr(195) . \chr(133) => 'A',
			\chr(195) . \chr(135) => 'C',
			\chr(195) . \chr(136) => 'E',
			\chr(195) . \chr(137) => 'E',
			\chr(195) . \chr(138) => 'E',
			\chr(195) . \chr(139) => 'E',
			\chr(195) . \chr(140) => 'I',
			\chr(195) . \chr(141) => 'I',
			\chr(195) . \chr(142) => 'I',
			\chr(195) . \chr(143) => 'I',
			\chr(195) . \chr(145) => 'N',
			\chr(195) . \chr(146) => 'O',
			\chr(195) . \chr(147) => 'O',
			\chr(195) . \chr(148) => 'O',
			\chr(195) . \chr(149) => 'O',
			\chr(195) . \chr(150) => 'O',
			\chr(195) . \chr(153) => 'U',
			\chr(195) . \chr(154) => 'U',
			\chr(195) . \chr(155) => 'U',
			\chr(195) . \chr(156) => 'U',
			\chr(195) . \chr(157) => 'Y',
			\chr(195) . \chr(159) => 's',
			\chr(195) . \chr(160) => 'a',
			\chr(195) . \chr(161) => 'a',
			\chr(195) . \chr(162) => 'a',
			\chr(195) . \chr(163) => 'a',
			\chr(195) . \chr(164) => 'a',
			\chr(195) . \chr(165) => 'a',
			\chr(195) . \chr(167) => 'c',
			\chr(195) . \chr(168) => 'e',
			\chr(195) . \chr(169) => 'e',
			\chr(195) . \chr(170) => 'e',
			\chr(195) . \chr(171) => 'e',
			\chr(195) . \chr(172) => 'i',
			\chr(195) . \chr(173) => 'i',
			\chr(195) . \chr(174) => 'i',
			\chr(195) . \chr(175) => 'i',
			\chr(195) . \chr(177) => 'n',
			\chr(195) . \chr(178) => 'o',
			\chr(195) . \chr(179) => 'o',
			\chr(195) . \chr(180) => 'o',
			\chr(195) . \chr(181) => 'o',
			\chr(195) . \chr(182) => 'o',
			\chr(195) . \chr(182) => 'o',
			\chr(195) . \chr(185) => 'u',
			\chr(195) . \chr(186) => 'u',
			\chr(195) . \chr(187) => 'u',
			\chr(195) . \chr(188) => 'u',
			\chr(195) . \chr(189) => 'y',
			\chr(195) . \chr(191) => 'y',
			// Decompositions for Latin Extended-A
			\chr(196) . \chr(128) => 'A',
			\chr(196) . \chr(129) => 'a',
			\chr(196) . \chr(130) => 'A',
			\chr(196) . \chr(131) => 'a',
			\chr(196) . \chr(132) => 'A',
			\chr(196) . \chr(133) => 'a',
			\chr(196) . \chr(134) => 'C',
			\chr(196) . \chr(135) => 'c',
			\chr(196) . \chr(136) => 'C',
			\chr(196) . \chr(137) => 'c',
			\chr(196) . \chr(138) => 'C',
			\chr(196) . \chr(139) => 'c',
			\chr(196) . \chr(140) => 'C',
			\chr(196) . \chr(141) => 'c',
			\chr(196) . \chr(142) => 'D',
			\chr(196) . \chr(143) => 'd',
			\chr(196) . \chr(144) => 'D',
			\chr(196) . \chr(145) => 'd',
			\chr(196) . \chr(146) => 'E',
			\chr(196) . \chr(147) => 'e',
			\chr(196) . \chr(148) => 'E',
			\chr(196) . \chr(149) => 'e',
			\chr(196) . \chr(150) => 'E',
			\chr(196) . \chr(151) => 'e',
			\chr(196) . \chr(152) => 'E',
			\chr(196) . \chr(153) => 'e',
			\chr(196) . \chr(154) => 'E',
			\chr(196) . \chr(155) => 'e',
			\chr(196) . \chr(156) => 'G',
			\chr(196) . \chr(157) => 'g',
			\chr(196) . \chr(158) => 'G',
			\chr(196) . \chr(159) => 'g',
			\chr(196) . \chr(160) => 'G',
			\chr(196) . \chr(161) => 'g',
			\chr(196) . \chr(162) => 'G',
			\chr(196) . \chr(163) => 'g',
			\chr(196) . \chr(164) => 'H',
			\chr(196) . \chr(165) => 'h',
			\chr(196) . \chr(166) => 'H',
			\chr(196) . \chr(167) => 'h',
			\chr(196) . \chr(168) => 'I',
			\chr(196) . \chr(169) => 'i',
			\chr(196) . \chr(170) => 'I',
			\chr(196) . \chr(171) => 'i',
			\chr(196) . \chr(172) => 'I',
			\chr(196) . \chr(173) => 'i',
			\chr(196) . \chr(174) => 'I',
			\chr(196) . \chr(175) => 'i',
			\chr(196) . \chr(176) => 'I',
			\chr(196) . \chr(177) => 'i',
			\chr(196) . \chr(178) => 'IJ',
			\chr(196) . \chr(179) => 'ij',
			\chr(196) . \chr(180) => 'J',
			\chr(196) . \chr(181) => 'j',
			\chr(196) . \chr(182) => 'K',
			\chr(196) . \chr(183) => 'k',
			\chr(196) . \chr(184) => 'k',
			\chr(196) . \chr(185) => 'L',
			\chr(196) . \chr(186) => 'l',
			\chr(196) . \chr(187) => 'L',
			\chr(196) . \chr(188) => 'l',
			\chr(196) . \chr(189) => 'L',
			\chr(196) . \chr(190) => 'l',
			\chr(196) . \chr(191) => 'L',
			\chr(197) . \chr(128) => 'l',
			\chr(197) . \chr(129) => 'L',
			\chr(197) . \chr(130) => 'l',
			\chr(197) . \chr(131) => 'N',
			\chr(197) . \chr(132) => 'n',
			\chr(197) . \chr(133) => 'N',
			\chr(197) . \chr(134) => 'n',
			\chr(197) . \chr(135) => 'N',
			\chr(197) . \chr(136) => 'n',
			\chr(197) . \chr(137) => 'N',
			\chr(197) . \chr(138) => 'n',
			\chr(197) . \chr(139) => 'N',
			\chr(197) . \chr(140) => 'O',
			\chr(197) . \chr(141) => 'o',
			\chr(197) . \chr(142) => 'O',
			\chr(197) . \chr(143) => 'o',
			\chr(197) . \chr(144) => 'O',
			\chr(197) . \chr(145) => 'o',
			\chr(197) . \chr(146) => 'OE',
			\chr(197) . \chr(147) => 'oe',
			\chr(197) . \chr(148) => 'R',
			\chr(197) . \chr(149) => 'r',
			\chr(197) . \chr(150) => 'R',
			\chr(197) . \chr(151) => 'r',
			\chr(197) . \chr(152) => 'R',
			\chr(197) . \chr(153) => 'r',
			\chr(197) . \chr(154) => 'S',
			\chr(197) . \chr(155) => 's',
			\chr(197) . \chr(156) => 'S',
			\chr(197) . \chr(157) => 's',
			\chr(197) . \chr(158) => 'S',
			\chr(197) . \chr(159) => 's',
			\chr(197) . \chr(160) => 'S',
			\chr(197) . \chr(161) => 's',
			\chr(197) . \chr(162) => 'T',
			\chr(197) . \chr(163) => 't',
			\chr(197) . \chr(164) => 'T',
			\chr(197) . \chr(165) => 't',
			\chr(197) . \chr(166) => 'T',
			\chr(197) . \chr(167) => 't',
			\chr(197) . \chr(168) => 'U',
			\chr(197) . \chr(169) => 'u',
			\chr(197) . \chr(170) => 'U',
			\chr(197) . \chr(171) => 'u',
			\chr(197) . \chr(172) => 'U',
			\chr(197) . \chr(173) => 'u',
			\chr(197) . \chr(174) => 'U',
			\chr(197) . \chr(175) => 'u',
			\chr(197) . \chr(176) => 'U',
			\chr(197) . \chr(177) => 'u',
			\chr(197) . \chr(178) => 'U',
			\chr(197) . \chr(179) => 'u',
			\chr(197) . \chr(180) => 'W',
			\chr(197) . \chr(181) => 'w',
			\chr(197) . \chr(182) => 'Y',
			\chr(197) . \chr(183) => 'y',
			\chr(197) . \chr(184) => 'Y',
			\chr(197) . \chr(185) => 'Z',
			\chr(197) . \chr(186) => 'z',
			\chr(197) . \chr(187) => 'Z',
			\chr(197) . \chr(188) => 'z',
			\chr(197) . \chr(189) => 'Z',
			\chr(197) . \chr(190) => 'z',
			\chr(197) . \chr(191) => 's',
		];

		return \strtr($string, $chars);
	}
}
