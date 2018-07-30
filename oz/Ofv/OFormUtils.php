<?php
	/**
	 * Copyright (c) Emile Silas Sare <emile.silas@gmail.com>
	 *
	 * This file is part of OZone (O'Zone) package.
	 *
	 * For the full copyright and license information, please view the LICENSE
	 * file that was distributed with this source code.
	 */

	namespace OZONE\OZ\Ofv;

	final class OFormUtils
	{
		/**
		 * Checks if the fields 'a' and 'b' are equals
		 *
		 * @param \OZONE\OZ\Ofv\OFormValidator $ofv The form validator object
		 * @param  string                      $a   the field 'a' name
		 * @param  string                      $b   the field 'b' name
		 *
		 * @return bool
		 */
		public static function equalFields(OFormValidator $ofv, $a, $b)
		{
			$form = $ofv->getForm();

			if (isset($form[$a]) AND isset($form[$b]) AND $form[$a] === $form[$b]) {
				return true;
			}

			return false;
		}

		/**
		 * Loads form validators in a given directory
		 *
		 * @param string $dir         the directory path
		 * @param bool   $silent_mode whether to throw exception when directory was not found
		 *
		 * @throws \Exception    when we couldn't access the directory and $silent_mode is set to false
		 */
		public static function loadValidators($dir, $silent_mode = false)
		{
			if (DIRECTORY_SEPARATOR == "\\") {
				$dir = strtr($dir, '/', "\\");
			}

			$ofv_validators_func_reg = '#^ofv\.[a-z0-9_]+\.php$#';

			if (file_exists($dir) AND is_dir($dir) AND $res = @opendir($dir)) {
				while (false !== ($filename = readdir($res))) {
					if ($filename !== '.' AND $filename !== '..') {
						$c_path = $dir . DIRECTORY_SEPARATOR . $filename;

						if (is_file($c_path) AND preg_match($ofv_validators_func_reg, $filename)) {
							require_once $c_path;
						}
					}
				}

				closedir($res);
			} elseif (!$silent_mode) {
				throw new \Exception("OFormUtils: $dir not found or is not a valid directory.");
			}
		}

		/**
		 * Checks if a given date is valid
		 *
		 * @param string $date_string the date string
		 *
		 * @return bool
		 */
		public static function isValidDate($date_string)
		{
			$date = OFormUtils::parseDate($date_string);

			if (!$date) {
				return false;
			}

			$year  = $date['YYYY'];
			$month = $date['MM'];
			$day   = $date['DD'];

			// depending on the year, calculate the number of days in the month
			if (($year % 4) == 0) {
				$days_in_month = [31, 29, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31];
			} else {
				$days_in_month = [31, 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31];
			}

			// first, check the incoming month and year are valid.
			if (!$month || !$day || !$year) {
				return false;
			}

			if (1 > $month || $month > 12) {
				return false;
			}

			if ($year < 0) {
				return false;
			}

			if (1 > $day || $day > $days_in_month[$month - 1]) {
				return false;
			}

			return true;
		}

		/**
		 * Checks if a given date is a valid birth date
		 *
		 * @param string $date_string the date string
		 * @param int    $min_age     the min user age
		 * @param int    $max_age     the max user age
		 *
		 * @return bool
		 */
		public static function isBirthDate($date_string, $min_age = 0, $max_age = INF)
		{
			if (!OFormUtils::isValidDate($date_string)) {
				return false;
			}

			$date = OFormUtils::parseDate($date_string);
			$year = $date['YYYY'];

			// get current year
			$c_year = date('Y');
			$age    = $c_year - $year;

			if (($age < 0) || ($age < $min_age) || ($age > $max_age)) {
				return false;
			}

			return true;
		}

		/**
		 * Parse a given date string
		 *
		 * @param string $date_string the date string to parse
		 *
		 * @return array|bool    result in array when successful, false otherwise
		 */
		public static function parseDate($date_string)
		{
			$safe = !empty($date_string);
			// standard YYYY-MM-DD
			$DATE_REG_A = '#^(\d{4})[\-\/](\d{1,2})[\-\/](\d{1,2})$#';
			// other DD-MM-YYYY
			// when browser threat date field as text field (ex: in firefox) we consider valid dd/mm/yyyy
			$DATE_REG_B = '#^(\d{1,2})[\-\/](\d{1,2})[\-\/](\d{4})$#';

			$in_a = [];
			$in_b = [];

			if ($safe && preg_match($DATE_REG_A, $date_string, $in_a)) {
				$year  = intval($in_a[1]);
				$month = intval($in_a[2]);
				$day   = intval($in_a[3]);
			} elseif ($safe && preg_match($DATE_REG_B, $date_string, $in_b)) {
				$year  = intval($in_b[3]);
				$month = intval($in_b[2]);
				$day   = intval($in_b[1]);
			} else {
				return false;
			}

			$format["DD"]         = $day;
			$format["MM"]         = $month;
			$format["YYYY"]       = $year;
			$format["YYYY-MM-DD"] = $year . '-' . self::prefixZero($month) . '-' . self::prefixZero($day);
			$format["DD-MM-YYYY"] = self::prefixZero($day) . '-' . self::prefixZero($month) . '-' . $year;

			return $format;
		}

		private static function prefixZero($x){
			return ( intval($x) < 10 ? "0". $x : $x );
		}
	}