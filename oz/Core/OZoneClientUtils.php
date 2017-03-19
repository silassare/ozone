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

	use OZONE\OZ\Utils\OZoneStr;

	defined( 'OZ_SELF_SECURITY_CHECK' ) or die;

	class OZoneClientUtils {

		private static $temp_clients_infos = array();

		public static function isTokenLike( $token ) {
			$token_reg = "#^[a-zA-Z0-9]{32}$#";

			return is_string( $token ) AND preg_match( $token_reg, $token );
		}

		public static function checkSafeOriginUrl( $url ) {
			$sql = "
				SELECT oz_clients.*
 				FROM oz_clients
 				WHERE oz_clients.client_url =:cliurl AND oz_clients.client_valid = 1";
			$select = OZoneDb::getInstance()->select( $sql, array(
				'cliurl' => $url
			) );

			return $select->rowCount() > 0;
		}

		/**
		 * get client instance with a given key type
		 *
		 * @param string $key_value The key value
		 * @param string $key_type  The key type
		 *
		 * @return array|null
		 */
		public static function getInfoWith( $key_value, $key_type ) {

			if ( empty( $key_value ) OR !is_string( $key_value ) OR !in_array( $key_type, array( 'clid', 'sid', 'token' ) ) )
				return null;

			$sql = null;

			switch ( $key_type ) {

				case 'clid' :

					if ( array_key_exists( $key_value, self::$temp_clients_infos ) ) {
						return self::$temp_clients_infos[ $key_value ];
					}

					$sql = "SELECT oz_clients.*
							FROM oz_clients
							WHERE oz_clients.client_clid =:key AND oz_clients.client_valid = 1";

					break;

				case 'sid' :

					$sql = "SELECT oz_clients.* 
							FROM oz_clients, oz_clients_users
							WHERE oz_clients_users.client_sid =:key
								AND oz_clients.client_clid = oz_clients_users.client_clid
								AND oz_clients.client_valid = 1
							LIMIT 0,1";
					break;

				case 'token' :

					$sql = "SELECT oz_clients.* 
							FROM oz_clients, oz_clients_users
							WHERE oz_clients_users.client_token =:key
								AND oz_clients.client_clid = oz_clients_users.client_clid
								AND oz_clients.client_valid = 1
							LIMIT 0,1";
					break;
			}

			$select = OZoneDb::getInstance()->select( $sql, array(
				'key' => OZoneStr::clean( $key_value )
			) );

			if ( $select->rowCount() > 0 ) {
				$info = $select->fetch();

				self::$temp_clients_infos[ $info[ 'client_clid' ] ] = $info;

				return $info;
			}

			return null;
		}
	}