<?php

	final class OTplPlugin_Html {
		public static function SetAttr( $data ) {
			$arr = array();

			foreach ( $data as $key => $val ) {
				$attr = $key;

				if ( strlen( $val ) ) {
					$attr .= '="' . self::Escape( $val ) . '"';
				}

				array_push( $arr, $attr );
			}

			return join( $arr, ' ' );
		}

		public static function Escape( $str ) {
			$str = htmlentities( $str, ENT_QUOTES, 'UTF-8' );

			return str_replace( "&amp;", "&", $str );
		}
	}

	OTplUtils::addPlugin( 'HtmlSetAttr', array( 'OTplPlugin_Html', 'SetAttr' ) );
	OTplUtils::addPlugin( 'HtmlEscape', array( 'OTplPlugin_Html', 'Escape' ) );