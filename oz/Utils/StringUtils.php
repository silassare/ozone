<?php
	/**
	 * Copyright (c) Emile Silas Sare <emile.silas@gmail.com>
	 *
	 * This file is part of the OZone package.
	 *
	 * For the full copyright and license information, please view the LICENSE
	 * file that was distributed with this source code.
	 */

	namespace OZONE\OZ\Utils;

	defined('OZ_SELF_SECURITY_CHECK') or die;

	final class StringUtils
	{
		/**
		 * clean a given text
		 *
		 * @param string $text        the text to clean
		 * @param bool   $disable_oml should we disable oml
		 *
		 * @return string
		 */
		public static function clean($text = null, $disable_oml = true)
		{
			if (is_numeric($text)) return '' . $text;
			if (!is_string($text)) return '';

			$text = rtrim($text);
			$text = htmlentities($text, ENT_QUOTES, 'UTF-8');
			$text = str_replace("&amp;", "&", $text);

			if ($disable_oml) {
				$text = str_replace("{", "{&#x0000;", $text);
				$text = str_replace("&#x0000;&#x0000;", "&#x0000;", $text);
			}

			return self::emoFix($text);
		}

		/**
		 * try to fix some emo unicode problem,
		 *
		 * @param string $text text to fix
		 *
		 * @return string
		 */
		private static function emoFix($text)
		{
			$text              = json_encode($text);
			$text              = substr($text, 1, strlen($text) - 2);
			$html_entities_emo = [
				["&trade;", "\u2122"],
				["&harr;", "\u2194"],
				["&spades;", "\u2660"],
				["&clubs;", "\u2663"],
				["&hearts;", "\u2665"],
				["&diams;", "\u2666"],
				["&copy;", "\u00a9"],
				["&reg;", "\u00ae"]
			];

			foreach ($html_entities_emo as $key => $emo) {
				$text = str_replace($emo[0], $emo[1], $text);
			}

			$text = str_replace("\\\\u", "\\u", $text);

			return $text;
		}

		/**
		 * remove a given prefix from a given string
		 *
		 * @param string $str
		 * @param string $prefix
		 *
		 * @return string
		 */
		public static function removePrefix($str, $prefix)
		{
			if (strlen($str) AND strlen($prefix) AND 0 === strpos($str, $prefix)) {
				$str = substr($str, strlen($prefix));
			}

			return $str;
		}

		/**
		 * remove a given suffix from a given string
		 *
		 * @param string $str
		 * @param string $suffix
		 *
		 * @return string
		 */
		public static function removeSuffix($str, $suffix)
		{
			if (strlen($str) AND strlen($suffix) AND 0 < strpos($str, $suffix)) {
				$str = substr($str, 0, strlen($str) - strlen($suffix));
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
		 * @return string|boolean    false if error
		 *
		 * @throws \Exception        when some required modules not found
		 */
		public static function convertEncoding($data, $from, $to = 'UTF-8')
		{
			if (function_exists('mb_convert_encoding')) {
				// alternatives
				$alt = ['windows-949' => 'EUC-KR', 'Windows-31J' => 'SJIS'];

				$from = isset($alt[$from]) ? $alt[$from] : $from;
				$to   = isset($alt[$to]) ? $alt[$to] : $to;

				return @mb_convert_encoding($data, $to, $from);
			}

			if (function_exists('iconv')) {
				return @iconv($from, $to, $data);
			}

			throw new \Exception('Make sure PHP module "iconv" or "mbstring" is installed.');
		}

		/**
		 * convert to utf8
		 *
		 * @param mixed $input the string to encode
		 *
		 * @return mixed
		 */
		public static function toUtf8($input)
		{
			$from = null;

			if (function_exists('mb_detect_encoding')) {
				$from = mb_detect_encoding($input);
			}

			return self::convertEncoding($input, $from, 'UTF-8');
		}

		/**
		 * fix some encoding problems as we only use UTF-8
		 *
		 * @param mixed $input       the input to fix
		 * @param bool  $encode_keys whether to encode keys if input is array or object
		 *
		 * @return mixed
		 */
		public static function encodeFix($input, $encode_keys = false)
		{
			$result = null;

			if (is_string($input)) {
				$result = self::toUtf8($input);
			} elseif (is_array($input)) {
				$result = [];
				foreach ($input as $k => $v) {
					$key          = ($encode_keys) ? self::toUtf8($k) : $k;
					$result[$key] = self::encodeFix($v, $encode_keys);
				}
			} elseif (is_object($input)) {
				$vars = array_keys(get_object_vars($input));

				foreach ($vars as $var) {
					$input->$var = self::encodeFix($input->$var);
				}
			} else {
				return $input;
			}

			return $result;
		}
	}