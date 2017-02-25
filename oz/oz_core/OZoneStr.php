<?php
	//SILO:: Protect from unauthorized access/include
	defined( 'OZ_SELF_SECURITY_CHECK' ) or die;

	final class OZoneStr {
		public static function clean( $text = null, $clean_oml = true ) {
			if ( is_numeric( $text ) )
				return $text;
			if ( !is_string( $text ) )
				return '';

			//on prend le soin d'effacer les espaces de fins
			//mais on laisse les espaces de debuts car utiles dans le cas des dessin a partie du texte ex: arbre de noel....

			$text = rtrim( $text );
			$text = htmlentities( $text, ENT_QUOTES, 'UTF-8' );
			$text = str_replace( "&amp;", "&", $text );

			//evitons que user utilise le langage OML dans l'editeur

			if ( $clean_oml ) {
				$text = str_replace( "{", "{&#x0000;", $text );
				$text = str_replace( "&#x0000;&#x0000;", "&#x0000;", $text );
			}

			return self::emoji_fix( $text );
		}

		private static function emoji_fix( $text ) {
			//json_encode nous permet de regler certain probleme d'encodage au niveau des emoji
			$text = json_encode( $text );
			//on enleve le "" mis par json_encode en debut et fin
			$text = substr( $text, 1, strlen( $text ) - 2 );
			$htmlentities_emoji = array(
				array( "&trade;", "\u2122" ),
				array( "&harr;", "\u2194" ),
				array( "&spades;", "\u2660" ),
				array( "&clubs;", "\u2663" ),
				array( "&hearts;", "\u2665" ),
				array( "&diams;", "\u2666" ),
				array( "&copy;", "\u00a9" ),
				array( "&reg;", "\u00ae" )
			);

			foreach ( $htmlentities_emoji as $key => $emoji ) {
				$text = str_replace( $emoji[ 0 ], $emoji[ 1 ], $text );
			}

			$text = str_replace( "\\\\u", "\\u", $text );

			return $text;
		}

		public static function arrayToList( array $list = null ) {
			if ( $list == null )
				return $list;

			return "( " . implode( ',', $list ) . " )";
		}

		public static function encodeFix( $input, $encode_keys = false ) {
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
