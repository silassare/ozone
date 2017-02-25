<?php
	//SILO:: Protect from unauthorized access/include
	defined( 'OZ_SELF_SECURITY_CHECK' ) or die;

	final class OZoneUri {

		//SILO::match url like /service/path/to/ressources/sub_string_like_params

		const SERVICE_URL_REG = "#/([a-zA-Z][a-zA-Z0-9_]+)(?:/(.*))?$#";

		public static function checkRequestedServiceName() {
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

		public static function parseServiceUriExtra( $extra_reg, array $extra_map, array &$extra_out ) {
			$extra = $_REQUEST[ 'oz_req_service_extra' ];
			$in = array();
			$c = 1;

			if ( !empty( $extra ) AND preg_match( $extra_reg, $extra, $in ) ) {

				foreach ( $extra_map as $value ) {
					if ( !isset( $in[ $c ] ) )
						break;

					$extra_out[ $value ] = $in[ $c ];
					$c++;
				}

				return true;
			}

			return false;
		}
	}