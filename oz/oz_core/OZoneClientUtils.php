<?php
	//SILO:: Protect from unauthorized access/include
	defined( 'OZ_SELF_SECURITY_CHECK' ) or die;

	class OZoneClientUtils {

		private static $temp_clients_infos = array();

		public static function isTokenLike( $token ) {
			$token_reg = "#^[a-zA-Z0-9]{32}$#";

			return is_string( $token ) AND preg_match( $token_reg, $token );
		}

		public static function checkSafeOriginUrl( $url ) {
			$sql = "SELECT oz_clients.* FROM oz_clients WHERE oz_clients.client_url =:cliurl AND oz_clients.client_valid = 1";
			$select = OZone::obj( 'OZoneDb' )->select( $sql, array(
				'cliurl' => $url
			) );

			return $select->rowCount() > 0;
		}

		public static function getInfosWith( $key, $key_type ) {

			if ( empty( $key ) OR !is_string( $key ) OR !in_array( $key_type, array( 'clid', 'sid', 'token' ) ) )
				return null;

			$sql = null;

			switch ( $key_type ) {

				case 'clid' :

					if ( array_key_exists( $key, self::$temp_clients_infos ) ) {
						return self::$temp_clients_infos[ $key ];
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

			$select = OZone::obj( 'OZoneDb' )->select( $sql, array(
				'key' => OZoneStr::clean( $key )
			) );

			if ( $select->rowCount() > 0 ) {
				$infos = $select->fetch();

				self::$temp_clients_infos[ $infos[ 'client_clid' ] ] = $infos;

				return $infos;
			}

			return null;
		}
	}