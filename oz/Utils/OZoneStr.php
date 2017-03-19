<?php
	/**
	 * Copyright (c) Silas E. Sare <emile.silas@gmail.com>
	 *
	 * This file is part of the OZone package.
	 *
	 * For the full copyright and license information, please view the LICENSE
	 * file that was distributed with this source code.
	 */

	namespace OZONE\OZ\Utils;

	defined( 'OZ_SELF_SECURITY_CHECK' ) or die;

	final class OZoneStr {

		/**
		 * alphanumerics case insensitive
		 *
		 * @var string
		 */
		const ALPHA_NUM_INS = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';

		/**
		 * symbols
		 *
		 * @var string
		 */
		const SYMBOLS = '~!@#$%^&()_-+={}[]:";\'<>?,./\\';

		/**
		 * clean a given text
		 *
		 * @param string $text        the text to clean
		 * @param bool   $disable_oml should we disable oml
		 *
		 * @return string
		 */
		public static function clean( $text = null, $disable_oml = true ) {
			if ( is_numeric( $text ) )
				return '' . $text;
			if ( !is_string( $text ) )
				return '';

			//on prend le soin d'effacer les espaces de fins
			//mais on laisse les espaces de debuts car utiles dans le cas des dessin a partie du texte ex: arbre de noel....

			$text = rtrim( $text );
			$text = htmlentities( $text, ENT_QUOTES, 'UTF-8' );
			$text = str_replace( "&amp;", "&", $text );

			//evitons que user utilise le langage OML dans l'editeur

			if ( $disable_oml ) {
				$text = str_replace( "{", "{&#x0000;", $text );
				$text = str_replace( "&#x0000;&#x0000;", "&#x0000;", $text );
			}

			return self::emo_fix( $text );
		}

		/**
		 * try to fix some emo unicode problem,
		 *
		 * @param string $text text to fix
		 *
		 * @return string
		 */
		private static function emo_fix( $text ) {
			//json_encode nous permet de regler certain probleme d'encodage au niveau des emoji
			$text = json_encode( $text );
			//on enleve le "" mis par json_encode en debut et fin
			$text = substr( $text, 1, strlen( $text ) - 2 );
			$htmlentities_emo = array(
				array( "&trade;", "\u2122" ),
				array( "&harr;", "\u2194" ),
				array( "&spades;", "\u2660" ),
				array( "&clubs;", "\u2663" ),
				array( "&hearts;", "\u2665" ),
				array( "&diams;", "\u2666" ),
				array( "&copy;", "\u00a9" ),
				array( "&reg;", "\u00ae" )
			);

			foreach ( $htmlentities_emo as $key => $emo ) {
				$text = str_replace( $emo[ 0 ], $emo[ 1 ], $text );
			}

			$text = str_replace( "\\\\u", "\\u", $text );

			return $text;
		}

		/**
		 * generate a random string for a given length
		 *
		 * @param int  $length        The desired random string length default 32 range is [1,512]
		 * @param bool $alphanum_only Should we use alphanumerics chars only
		 *
		 * @return string
		 * @throws \InvalidArgumentException
		 */
		public static function genRandomString( $length = 32, $alphanum_only = false ) {

			$min = 1;
			$max = 512;
			$chars = self::ALPHA_NUM_INS;

			if ( $length < $min OR $length > $max ) {
				throw new \InvalidArgumentException( "random string length must be between $min and $max" );
			}

			if ( !$alphanum_only ) {
				$chars .= self::SYMBOLS;
			}

			$chars_length = strlen( $chars ) - 1;
			$string = '';

			srand( microtime() * 100 );

			for ( $i = 0 ; $i < $length ; ++$i ) {
				$string .= $chars[ rand( 0, $chars_length ) ];
			}

			return $string;
		}

		/**
		 * format an array to sql list style
		 *
		 * @param array $arr the array to format
		 *
		 * @return string
		 */
		public static function arrayToList( array $arr ) {

			return self::clean( "( " . implode( ',', $arr ) . " )" );
		}

		/**
		 * fix some encoding problems
		 *
		 * @param mixed $input       the input to fix
		 * @param bool  $encode_keys whether to encode keys if input is array or object
		 *
		 * @return mixed
		 */
		public static function encodeFix( $input, $encode_keys = false ) {
			$result = null;

			if ( is_string( $input ) ) {
				$result = utf8_encode( $input );
			} elseif ( is_array( $input ) ) {
				$result = array();
				foreach ( $input as $k => $v ) {
					$key = ( $encode_keys ) ? utf8_encode( $k ) : $k;
					$result[ $key ] = self::encodeFix( $v, $encode_keys );
				}
			} elseif ( is_object( $input ) ) {
				$vars = array_keys( get_object_vars( $input ) );

				foreach ( $vars as $var ) {
					$input->$var = self::encodeFix( $input->$var );
				}
			} else {
				return $input;
			}

			return $result;
		}
	}