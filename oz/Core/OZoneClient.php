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

	final class OZoneClient {
		/**
		 * this client info cache
		 *
		 * @var array|null
		 */
		private $client_info = null;

		/**
		 * OZoneClient constructor.
		 *
		 * @param string $clid The client id
		 */
		public function __construct( $clid ) {
			$this->client_info = OZoneClientUtils::getInfoWith( $clid, 'clid' );
		}

		/**
		 * get client instance with a given key type
		 *
		 * @param string $key_value The key value
		 * @param string $key_type  The key type
		 *
		 * @return null|\OZONE\OZ\Core\OZoneClient
		 */
		public static function getInstanceWith( $key_value, $key_type ) {

			$info = OZoneClientUtils::getInfoWith( $key_value, $key_type );

			if ( empty( $info ) ) {
				return null;
			}

			return new self( $info[ 'client_clid' ] );
		}

		/**
		 * check whether the client is valid or not
		 *
		 * @return bool
		 */
		public function checkClient() {
			return is_array( $this->client_info ) AND intval( $this->client_info[ 'client_valid' ] );
		}

		/**
		 * get this client session max life time in seconds
		 *
		 * @return int
		 */
		public function getSessionMaxLifeTime() {
			return 86400;//1day
		}

		/**
		 * get this client infos
		 *
		 * @return array|null
		 */
		public function getClientInfo() {
			return $this->client_info;
		}

		/**
		 * get this client url
		 *
		 * @return string
		 */
		public function getClientUrl() {
			return $this->client_info[ 'client_url' ];
		}

		/**
		 * check if whether this client support multi user or not
		 *
		 * @return bool
		 */
		public function isMultiUserSupported() {
			return empty( $this->client_info[ 'client_userid' ] );
		}

		/**
		 * log that the user with the given id is using this client
		 *
		 * @param string|int $uid the user id
		 *
		 * @return string
		 * @throws \OZONE\OZ\Exceptions\OZoneBaseException
		 * @throws \OZONE\OZ\Exceptions\OZoneUnauthorizedActionException
		 * @throws string
		 */
		public function logUser( $uid ) {

			$client_infos = $this->getClientInfo();

			$safe = ( $this->isMultiUserSupported() OR $client_infos[ 'client_userid' ] === $uid );

			OZoneAssert::assertAuthorizeAction( $safe, null, $uid );

			$token = OZoneKeyGen::genAuthToken( $uid );
			$sid = session_id();

			$clid = $client_infos[ 'client_clid' ];

			$sql = "
				INSERT INTO oz_clients_users (client_clid, client_userid, client_sid, client_token, client_last_check)
				VALUES(:clid,:uid,:sid,:token,:lcheck) 
				ON DUPLICATE KEY 
				UPDATE client_sid =:sid , client_token =:token , client_last_check =:lcheck";

			OZoneDb::getInstance()->execute( $sql, array(
				'clid'   => $clid,
				'uid'    => $uid,
				'sid'    => $sid,
				'token'  => $token,
				'lcheck' => time()
			) );

			return $token;
		}

		/**
		 * remove the user with the given id that no longer use this client
		 *
		 * @param string|int $uid the user id
		 */
		public function removeUser( $uid ) {

			$client_infos = $this->getClientInfo();
			$clid = $client_infos[ 'client_clid' ];

			$sql = "
				DELETE FROM oz_clients_users
				WHERE oz_clients_users.client_clid =:clid AND oz_clients_users.client_userid =:uid";

			OZoneDb::getInstance()->delete( $sql, array(
				'clid' => $clid,
				'uid'  => $uid
			) );
		}

		/**
		 * check if an user with a given id and token is using this client
		 *
		 * @param string|int $uid   the user id
		 * @param string     $token the user token
		 *
		 * @return bool
		 */
		public function hasUser( $uid, $token ) {

			if ( empty( $uid ) OR !OZoneClientUtils::isTokenLike( $token ) ) {
				return false;
			}

			$client_infos = $this->getClientInfo();
			$clid = $client_infos[ 'client_clid' ];

			$sql = "SELECT oz_clients_users.client_userid
				FROM oz_clients_users
				WHERE oz_clients_users.client_clid =:clid
					AND oz_clients_users.client_userid =:uid
					AND oz_clients_users.client_token =:token";

			$req = OZoneDb::getInstance()->select( $sql, array(
				'clid'  => $clid,
				'uid'   => $uid,
				'token' => $token
			) );

			$c = $req->rowCount();

			return $c > 0;
		}
	}