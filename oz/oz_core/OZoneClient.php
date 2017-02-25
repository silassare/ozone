<?php
	//SILO:: Protect from unauthorized access/include
	defined( 'OZ_SELF_SECURITY_CHECK' ) or die;

	final class OZoneClient {

		private $infos = null;

		public function __construct( $clid ) {
			$this->infos = OZoneClientUtils::getInfosWith( $clid, 'clid' );
		}

		public static function getInstanceWith( $key, $key_type ) {

			$infos = OZoneClientUtils::getInfosWith( $key, $key_type );

			if ( empty( $infos ) ) {
				return null;
			}

			return new self( $infos[ 'client_clid' ] );
		}

		public function checkClient() {
			return is_array( $this->infos ) AND intval( $this->infos[ 'client_valid' ] );
		}

		public function getSessionMaxLifeTime() {
			return 86400;//1jours
		}

		public function getInfos() {
			return $this->infos;
		}

		public function getClientUrl() {
			return $this->infos[ 'client_url' ];
		}

		public function isMultyUserSupported() {
			return empty( $this->infos[ 'client_userid' ] );
		}

		public function logUser( $uid ) {

			$client_infos = $this->getInfos();

			$safe = ( $this->isMultyUserSupported() OR $client_infos[ 'client_userid' ] === $uid );

			OZoneAssert::assertAuthorizeAction( $safe, null, $uid );

			$token = OZoneKeyGen::genAuthToken( $uid );
			$sid = session_id();

			$clid = $client_infos[ 'client_clid' ];

			$sql = "
				INSERT INTO oz_clients_users (client_clid, client_userid, client_sid, client_token, client_last_check)
				VALUES(:clid,:uid,:sid,:token,:lcheck) 
				ON DUPLICATE KEY 
				UPDATE client_sid =:sid , client_token =:token , client_last_check =:lcheck";

			OZone::obj( 'OZoneDb' )->execute( $sql, array(
				'clid'   => $clid,
				'uid'    => $uid,
				'sid'    => $sid,
				'token'  => $token,
				'lcheck' => time()
			) );

			return $token;
		}

		public function removeUser( $uid ) {

			$client_infos = $this->getInfos();
			$clid = $client_infos[ 'client_clid' ];

			$sql = "DELETE FROM oz_clients_users WHERE oz_clients_users.client_clid =:clid AND oz_clients_users.client_userid =:uid";

			OZone::obj( 'OZoneDb' )->delete( $sql, array(
				'clid' => $clid,
				'uid'  => $uid
			) );
		}

		public function hasUser( $uid, $token ) {

			if ( empty( $uid ) OR !OZoneClientUtils::isTokenLike( $token ) ) {
				return false;
			}

			$client_infos = $this->getInfos();
			$clid = $client_infos[ 'client_clid' ];

			$sql = "SELECT oz_clients_users.client_userid
				FROM oz_clients_users
				WHERE oz_clients_users.client_clid =:clid
					AND oz_clients_users.client_userid =:uid
					AND oz_clients_users.client_token =:token";

			$req = OZone::obj( 'OZoneDb' )->select( $sql, array(
				'clid'  => $clid,
				'uid'   => $uid,
				'token' => $token
			) );

			$c = $req->rowCount();

			return $c > 0;
		}
	}