<?php
	/**
	 * Copyright (c) Silas E. Sare <emile.silas@gmail.com>
	 *
	 * This file is part of the OZone package.
	 *
	 * For the full copyright and license information, please view the LICENSE
	 * file that was distributed with this source code.
	 */

	namespace OZONE\OZ\Ofv;

	final class OFormUtils {

		/**
		 * check the given fields 'a' and 'b' fields are equals
		 *
		 * @param \OZONE\OZ\Ofv\OFormValidator $ofv The form validator object
		 * @param  string                      $a   the field 'a' name
		 * @param  string                      $b   the field 'b' name
		 *
		 * @return bool
		 */
		public static function equalFields( OFormValidator $ofv, $a, $b ) {
			$form = $ofv->getForm();

			if ( isset( $form[ $a ] ) AND isset( $form[ $b ] ) AND $form[ $a ] === $form[ $b ] ) {
				return true;
			}

			return false;
		}

		/**
		 * load form validators in a given directory
		 *
		 * @param string $dir the directory path
		 *
		 * @throws \Exception    when we couldn't access the directory
		 */
		public static function loadValidators( $dir ) {

			if ( DIRECTORY_SEPARATOR == "\\" ) {
				$dir = strtr( $dir, '/', "\\" );
			}

			$ofv_validators_func_reg = '#^ofv\.[a-z0-9_]+\.php$#';

			if ( file_exists( $dir ) AND is_dir( $dir ) AND $res = @opendir( $dir ) ) {

				while ( false !== ( $filename = readdir( $res ) ) ) {

					if ( $filename !== '.' AND $filename !== '..' ) {

						$c_path = $dir . DIRECTORY_SEPARATOR . $filename;

						if ( is_file( $c_path ) AND preg_match( $ofv_validators_func_reg, $filename ) ) {
							require_once $c_path;
						}
					}
				}

			} else {
				throw new \Exception( "OFormUtils > $dir not found or is not a valid directory." );
			}
		}

		/**
		 * check if a given form has all required field
		 *
		 * @param array $form            the form
		 * @param array $required_fields the required fields
		 *
		 * @return bool
		 */
		public static function isFormComplete( array $form, array $required_fields ) {
			$ans = true;

			foreach ( $required_fields as $field_name ) {
				if ( !isset( $form[ $field_name ] ) ) {
					$ans = false;
					break;
				}
			}

			return $ans;
		}

		/**
		 * check if a given date is valid
		 *
		 * @param int $month the month
		 * @param int $day   the day of the month
		 * @param int $year  the year
		 *
		 * @return bool
		 */
		public static function isValidDate( $month, $day, $year ) {
			// depending on the year, calculate the number of days in the month
			if ( ( $year % 4 ) == 0 ) {
				$days_in_month = array( 31, 29, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31 );
			} else {
				$days_in_month = array( 31, 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31 );
			}

			// first, check the incoming month and year are valid.
			if ( !$month || !$day || !$year ) {
				return false;
			}

			if ( 1 > $month || $month > 12 ) {
				return false;
			}

			if ( $year < 0 ) {
				return false;
			}

			if ( 1 > $day || $day > $days_in_month[ $month - 1 ] ) {
				return false;
			}

			return true;
		}

		/**
		 * check if a given date is a valid birth date
		 *
		 * @param int $month   the month
		 * @param int $day     the day of the month
		 * @param int $year    the year
		 * @param int $min_age the min user age
		 * @param int $max_age the max user age
		 *
		 * @return bool
		 */
		public static function isBirthDate( $month, $day, $year, $min_age = 0, $max_age = INF ) {
			if ( !OFormUtils::isValidDate( $month, $day, $year ) ) {
				return false;
			}

			// get current year
			$c_year = date( 'Y' );
			$age = $c_year - $year;

			if ( ( $age < 0 ) || ( $age < $min_age ) || ( $age > $max_age ) ) {
				return false;
			}

			return true;
		}
	}