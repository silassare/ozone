<?php
	//SILO:: Protect from unauthorized access/include
	defined( 'OZ_SELF_SECURITY_CHECK' ) or die;

	final class OZoneAuthenticator {
		private $label = null;
		private $for = null;

		function __construct( $label, $for ) {
			$this->label = $label;
			$this->for = $for;
		}

		public function exists() {
			return !empty( $this->get() );
		}

		public function cancel() {
			$sql = "
				DELETE FROM oz_authenticator 
				WHERE auth_label =:label AND auth_for =:for";

			return OZone::obj( 'OZoneDb' )->delete( $sql, array(
				'label' => $this->label,
				'for'   => $this->for
			) );
		}

		public function genCode() {

			$try_max = intval( OZoneSettings::get( 'oz.authenticator', 'OZ_AUTH_CODE_FAIL_MAX' ) );
			$expire = time() + intval( OZoneSettings::get( 'oz.authenticator', 'OZ_AUTH_CODE_LIFE_TIME' ) );

			$code = OZoneKeyGen::genAuthCode();
			$token = OZoneKeyGen::genAuthToken( $code );

			$sql = "
				INSERT INTO
					oz_authenticator ( auth_label , auth_for , auth_code , auth_token, auth_try_max , auth_try_count , auth_expire )
				VALUES ( :label , :for , :code , :token, :try_max , :try_count , :expire )
				ON DUPLICATE KEY
				UPDATE auth_code =:code , auth_token =:token , auth_try_count =:try_count, auth_expire =:expire";

			$req = OZone::obj( 'OZoneDb' )->insert( $sql, array(
				'label'     => $this->label,
				'for'       => $this->for,
				'code'      => $code,
				'token'     => $token,
				'try_max'   => $try_max,
				'try_count' => 0,
				'expire'    => $expire
			) );

			return array( 'code' => $code, 'token' => $token );
		}

		public function validateCode( $value ) {
			return $this->validate( 'code', $value );
		}

		public function validateToken( $value ) {
			return $this->validate( 'token', $value );
		}

		private function validate( $type, $value ) {

			$this->tryUpdateCounter();

			$ok = false;
			$msg = 'OZ_AUTH_CODE_NOT_FOUND';

			$data = $this->get();

			if ( !empty( $data ) ) {

				if ( $data[ 'auth_expire' ] <= time() ) {
					$msg = 'OZ_AUTH_CODE_EXPIRED';
					$this->cancel();
				} else if ( $data[ 'auth_try_count' ] > $data[ 'auth_try_max' ] ) {
					$msg = 'OZ_OZ_AUTH_CODE_FAIL_EXCEED_MAX';
					$this->cancel();
				} else if ( $type === 'code' AND $data[ 'auth_code' ] != $value ) {
					$msg = 'OZ_AUTH_CODE_FAIL';
				} else if ( $type === 'token' AND $data[ 'auth_token' ] != $value ) {
					$msg = 'OZ_AUTH_CODE_FAIL';
				} else {
					$this->cancel();
					$ok = true;
					$msg = 'OZ_AUTH_CODE_OK';
				}
			}

			return array(
				'ok'  => $ok,
				'msg' => $msg
			);
		}

		private function get() {
			$sql = "
				SELECT * FROM oz_authenticator
				WHERE auth_label =:label AND auth_for =:for
				LIMIT 0,1";

			$req = OZone::obj( 'OZoneDb' )->select( $sql, array(
				'label' => $this->label,
				'for'   => $this->for
			) );

			if ( $req->rowCount() > 0 ) {
				$data = $req->fetch();
				$req->closeCursor();

				return $data;
			}

			return null;
		}

		private function tryUpdateCounter() {
			$sql = "
				UPDATE oz_authenticator 
				SET auth_try_count = auth_try_count + 1
				WHERE auth_label =:label AND auth_for =:for";

			return OZone::obj( 'OZoneDb' )->update( $sql, array(
				'label' => $this->label,
				'for'   => $this->for
			) );
		}
	}