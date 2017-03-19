<?php

	final class OTplPlugin_Assert {
		public static function has( $data, $key, $type = null ) {
			if ( !$data )
				return false;

			return
				( empty( $type ) AND isset( $data[ $key ] ) )
				OR
				( isset( $data[ $key ] ) AND self::type( $data[ $key ], $type ) );
		}

		public static function type( $value, $type ) {

			$ans = false;

			switch ( $type ) {
				case 'string':
					$ans = is_string( $value );
					break;
				case 'list':
					$ans = is_array( $value );
					break;
				case 'numeric':
					$ans = is_numeric( $value );
					break;
			}

			return $ans;
		}
	}

	OTplUtils::addPlugin( 'has', array( 'OTplPlugin_Assert', 'has' ) );
	OTplUtils::addPlugin( 'type', array( 'OTplPlugin_Assert', 'type' ) );