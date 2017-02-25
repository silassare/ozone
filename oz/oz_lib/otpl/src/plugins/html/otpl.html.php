<?php

	final class OTplPlugin_Html {
		public static function SetAttr( $data ) {
			$attr = "";

			foreach ( $data as $key => $val ) {
				$attr = " $key";
				$value = "" . $val;

				if ( strlen( $value ) ) {
					$attr .= '="' . $val . '"';
				}
			}

			return $attr . ' ';
		}
	}

	OTplUtils::addPlugin( 'HtmlSetAttr', array( 'OTplPlugin_Html', 'SetAttr' ) );