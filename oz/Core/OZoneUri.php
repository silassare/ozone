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

	defined( 'OZ_SELF_SECURITY_CHECK' ) or die;

	final class OZoneUri {

		/**
		 * ozone uri regex
		 *
		 * uri have two parts: service name and service extra
		 *        - service name:
		 *            - must have minimum length of 1
		 *            - must contains only alphanumerics and _
		 *            - must start with a-zA-Z
		 *        - service extra
		 *            - minimum length of 0
		 *            - any valid uri characters are allowed
		 *
		 * for uri like: /foo_service/path/to/res/sub_string_like_params
		 *  _______________________________________________________
		 * |   service name   |        service extra               |
		 * |__________________|____________________________________|
		 * |    foo_service   | path/to/res/sub_string_like_params |
		 * |__________________|____________________________________|
		 *
		 * @var string
		 */
		const SERVICE_URL_REG = "#/([a-zA-Z][a-zA-Z0-9_]+)(?:/(.*))?$#";

		/**
		 * parse request uri
		 *
		 * @return bool        true if valid uri, false if not valid
		 */
		public static function parseRequestUri() {
			$uri = $_SERVER[ 'REQUEST_URI' ];
			$service = null;
			$service_extra = null;
			$in = array();

			if ( preg_match( OZoneUri::SERVICE_URL_REG, $uri, $in ) ) {
				$service = $in[ 1 ];

				if ( isset( $in[ 2 ] ) ) {
					$service_extra = $in[ 2 ];
				}
			}

			if ( empty( $service ) ) {
				return false;
			}

			$_REQUEST[ 'oz_req_service' ] = $service;
			$_REQUEST[ 'oz_req_service_extra' ] = $service_extra;

			return true;
		}

		/**
		 * parse uri extra with a given custom service extra regexp and a given key map
		 *
		 * @param string $extra_reg the service extra regexp to be used
		 * @param array  $extra_map the key map of regexp catch group
		 * @param array  $extra_out the array in which the result will be stored
		 *
		 * @return bool true if valid extra, false if not valid
		 */
		public static function parseUriExtra( $extra_reg, array $extra_map, array &$extra_out ) {
			$extra = $_REQUEST[ 'oz_req_service_extra' ];
			$in = array();
			$c = 1;//start from $1

			if ( !empty( $extra ) AND preg_match( $extra_reg, $extra, $in ) ) {

				$stop = false;

				while( !$stop AND isset($in[ $c ]) ){

					if ( isset( $extra_map[ $c ] ) ){
						$key = $extra_map[ $c ];
						$extra_out[ $key ] = $in[ $c ];
					} else {
						$stop = true;
					}

					$c++;
				}

				return true;
			}

			return false;
		}
	}