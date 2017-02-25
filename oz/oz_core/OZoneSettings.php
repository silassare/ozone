<?php
	//SILO:: Protect from unauthorized access/include
	defined( 'OZ_SELF_SECURITY_CHECK' ) or die;

	final class OZoneSettings {

		private static $loaded = array();

		private static function tryLoad( $settings_name ) {
			if ( !array_key_exists( $settings_name, self::$loaded ) ) {
				@include OZ_OZONE_SETTINGS_DIR . $settings_name . '.php';
				@include OZ_APP_SETTINGS_DIR . $settings_name . '.php';
			}
		}

		public static function get( $settings_name, $key = null ) {

			OZoneSettings::tryLoad( $settings_name );

			if ( array_key_exists( $settings_name, self::$loaded ) ) {

				$data = self::$loaded[ $settings_name ];

				if ( empty( $key ) ) {
					return $data;
				} else if ( array_key_exists( $key, $data ) ) {
					return $data[ $key ];
				} else {
					return null;
				}
			}

			throw new OZoneErrorInternalError( 'OZ_SETTINGS_UNDEFINED', array( $settings_name, $key ) );
		}

		public static function set( $settings_name, array $data ) {

			if ( !array_key_exists( $settings_name, self::$loaded ) ) {
				self::$loaded[ $settings_name ] = $data;
			} else {
				self::$loaded[ $settings_name ] = array_merge( self::$loaded[ $settings_name ], $data );
			}
		}
	}
