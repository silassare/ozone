<?php

	final class OFormUtils {

		public static function equalFields( OFormValidator $ofv, $a, $b ) {
			$form = $ofv->getForm();

			if ( isset( $form[ $a ] ) AND isset( $form[ $b ] ) AND $form[ $a ] === $form[ $b ] ) {
				return true;
			}

			return false;
		}

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
				throw new Exception( "OFormUtils > `$dir` not found or is not a valid directory." );
			}
		}

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

		public static function isValidDate( $mois, $jour, $annee ) {
			// depending on the year, calculate the number of days in the month
			if ( ( $annee % 4 ) == 0 ) {
				$jours_par_mois = array( 31, 29, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31 );
			} else {
				$jours_par_mois = array( 31, 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31 );
			}

			// first, check the incoming month and year are valid.
			if ( !$mois || !$jour || !$annee ) {
				return false;
			}

			if ( 1 > $mois || $mois > 12 ) {
				return false;
			}

			if ( $annee < 0 ) {
				return false;
			}

			if ( 1 > $jour || $jour > $jours_par_mois[ $mois - 1 ] ) {
				return false;
			}

			return true;
		}

		public static function isBirthdate( $mois, $jour, $annee, $min_age = 0, $max_age = INF ) {
			if ( !OFormUtils::isValidDate( $mois, $jour, $annee ) ) {
				return false;
			}

			// get current year
			$c_year = date( 'Y' );
			$age = $c_year - $annee;

			if ( ( $age < 0 ) || ( $age < $min_age ) || ( $age > $max_age ) ) {
				return false;
			}

			return true;
		}
	}