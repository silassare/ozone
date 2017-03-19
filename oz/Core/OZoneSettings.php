<?php
	/**
	 * Copyright (c) Silas E. Sare <emile.silas@gmail.com>
	 *
	 * This file is part of the OZone package.
	 *
	 * For the full copyright and license information, please view the LICENSE
	 * file that was distributed with this source code.
	 */

	namespace OZONE\OZ\Core;

	use OZONE\OZ\Exceptions\OZoneInternalError;

	defined( 'OZ_SELF_SECURITY_CHECK' ) or die;

	final class OZoneSettings {

		/**
		 * loaded settings cache array
		 *
		 * @var array
		 */
		private static $loaded = array();

		/**
		 * try loads settings file.
		 *
		 * @param string $settings_name settings file name without extension
		 */
		private static function tryLoad( $settings_name ) {
			if ( !array_key_exists( $settings_name, self::$loaded ) ) {
				$oz_settings_file = OZ_OZONE_SETTINGS_DIR . $settings_name . '.php';
				$app_settings_file = OZ_APP_SETTINGS_DIR . $settings_name . '.php';

				if ( file_exists( $oz_settings_file ) )
					include $oz_settings_file;
				if ( file_exists( $app_settings_file ) )
					include $app_settings_file;
			}
		}

		/**
		 * returns settings key value or settings
		 *
		 * @param string $settings_name settings file name without extension
		 * @param string $key           settings key name
		 *
		 * @return mixed|null
		 * @throws \OZONE\OZ\Exceptions\OZoneInternalError    when your settings or key are not defined
		 */
		public static function get( $settings_name, $key = null ) {

			OZoneSettings::tryLoad( $settings_name );

			if ( array_key_exists( $settings_name, self::$loaded ) ) {

				$data = self::$loaded[ $settings_name ];

				if ( empty( $key ) ) {
					return $data;
				}

				if ( array_key_exists( $key, $data ) ) {
					return $data[ $key ];
				}

				return null;
			}

			throw new OZoneInternalError( 'OZ_SETTINGS_UNDEFINED', array( $settings_name, $key ) );
		}

		/**
		 * used in settings files to add settings
		 *
		 * @param string $settings_name settings name without extension
		 * @param array  $data
		 */
		public static function set( $settings_name, array $data ) {

			if ( !array_key_exists( $settings_name, self::$loaded ) ) {
				self::$loaded[ $settings_name ] = $data;
			} else {
				self::$loaded[ $settings_name ] = array_merge( self::$loaded[ $settings_name ], $data );
			}
		}
	}
