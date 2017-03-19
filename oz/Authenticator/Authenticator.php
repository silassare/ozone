<?php
	/**
	 * Copyright (c) Silas E. Sare <emile.silas@gmail.com>
	 *
	 * This file is part of the OZone package.
	 *
	 * For the full copyright and license information, please view the LICENSE
	 * file that was distributed with this source code.
	 */

	namespace OZONE\OZ\Authenticator;

	use OZONE\OZ\Core\OZoneDb;
	use OZONE\OZ\Core\OZoneKeyGen;
	use OZONE\OZ\Core\OZoneSettings;

	defined( 'OZ_SELF_SECURITY_CHECK' ) or die;

	/**
	 * Class Authenticator
	 * @package OZONE\OZ\Authenticator
	 */
	final class Authenticator {
		/**
		 * @var string
		 */
		private $label = null;

		/**
		 * @var string
		 */
		private $for_value = null;

		/**
		 * contains generated authentication code, token ...
		 *
		 * @var array
		 */
		private $generated = null;

		/**
		 * the last auth message
		 *
		 * @var string|null
		 */
		private $message = null;

		/**
		 * Authenticator constructor.
		 *
		 * @param string $label     The authentication label.
		 * @param string $for_value The value to authenticate : email/phone number etc.
		 */
		function __construct( $label, $for_value ) {
			$this->label = md5( $label );
			$this->for_value = $for_value;
		}

		/**
		 * check if an authentication process was already started
		 *
		 * @return bool
		 */
		public function exists() {
			return !empty( $this->get() );
		}

		/**
		 * generate new authentication code, token ...
		 *
		 * @return \OZONE\OZ\Authenticator\Authenticator
		 *
		 * @throws \OZONE\OZ\Exceptions\OZoneInternalError
		 */
		public function generate() {

			$try_max = intval( OZoneSettings::get( 'oz.authenticator', 'OZ_AUTH_CODE_TRY_MAX' ) );
			$expire = time() + intval( OZoneSettings::get( 'oz.authenticator', 'OZ_AUTH_CODE_LIFE_TIME' ) );

			$code = OZoneKeyGen::genAuthCode();
			$token = OZoneKeyGen::genAuthToken( $code );

			$sql = "
				INSERT INTO
					oz_authenticator ( auth_label , auth_for , auth_code , auth_token, auth_try_max , auth_try_count , auth_expire )
				VALUES ( :label , :forv , :code , :token, :try_max , :try_count , :expire )
				ON DUPLICATE KEY
				UPDATE auth_code =:code , auth_token =:token , auth_try_count =:try_count, auth_expire =:expire";

			OZoneDb::getInstance()->insert( $sql, array(
				'label'     => $this->label,
				'forv'      => $this->for_value,
				'code'      => $code,
				'token'     => $token,
				'try_max'   => $try_max,
				'try_count' => 0,
				'expire'    => $expire
			) );

			$this->generated = array( 'code' => $code, 'token' => $token );

			return $this;
		}

		/**
		 * get this authenticator label
		 *
		 * @return string
		 */
		public function getLabel() {
			return $this->label;
		}

		/**
		 * get the value we want to authenticate
		 *
		 * @return string
		 */
		public function getForValue() {
			return $this->for_value;
		}

		/**
		 * get the generated authentication code token ... in an array
		 *
		 * @return array
		 */
		public function getGenerated() {
			return $this->generated;
		}

		/**
		 * get captcha image uri for authentication
		 *
		 * @return string the captcha uri
		 */
		public function getCaptcha() {

			if ( empty( $this->generated ) ) {
				$this->generate();
			}

			return ( new CaptchaCodeHelper() )->getUri( $this );
		}

		/**
		 * get token uri for authentication
		 *
		 * @return string the captcha uri
		 */
		public function getTokenUri() {

			if ( empty( $this->generated ) ) {
				$this->generate();
			}

			return ( new TokenUriHelper() )->getUri( $this );
		}

		/**
		 * validate this authentication process with code
		 *
		 * @param int $code the code value
		 *
		 * @return bool            true when successful, false otherwise
		 */
		public function validateCode( $code ) {

			$ok = false;
			$msg = 'OZ_AUTH_PROCESS_INVALID';

			$data = $this->get();

			if ( !empty( $data ) ) {

				$try_max = intval( $data[ 'auth_try_max' ] );
				$count = intval( $data[ 'auth_try_count' ] ) + 1;
				$rest = $try_max - $count;

				//check if auth process has expired
				if ( $data[ 'auth_expire' ] <= time() ) {
					$msg = 'OZ_AUTH_CODE_EXPIRED';
					$this->cancel();
				} else if ( $rest >= 0 AND $data[ 'auth_code' ] === $code ) {
					//we don't exceed the auth_try_max and the code is valid
					$ok = true;
					$msg = 'OZ_AUTH_CODE_OK';
					$this->cancel();
				} else if ( $rest <= 0 ) { /* it is our last tentative or we already exceed auth_try_max*/
					$msg = 'OZ_AUTH_CODE_EXCEED_MAX_FAIL';
					$this->cancel();
				} else { /*we have another chance*/
					$this->tryUpdateCounter();
					$msg = 'OZ_AUTH_CODE_INVALID';
				}
			}

			$this->message = $msg;

			return $ok;
		}

		/**
		 * validate this authentication process with token
		 *
		 * only one tentative per authentication process are allowed with token
		 *
		 * @param string $token the token value
		 *
		 * @return bool                true when successful, false otherwise
		 */
		public function validateToken( $token ) {

			$ok = false;
			$msg = 'OZ_AUTH_PROCESS_INVALID';

			$data = $this->get();

			if ( !empty( $data ) ) {

				if ( $data[ 'auth_expire' ] <= time() ) {
					$msg = 'OZ_AUTH_TOKEN_EXPIRED';
				} else if ( $data[ 'auth_token' ] != $token ) {
					$msg = 'OZ_AUTH_TOKEN_INVALID';
				} else {
					$ok = true;
					$msg = 'OZ_AUTH_TOKEN_OK';
				}

				//only one tentative per authentication process are allowed with token
				$this->cancel();
			}

			$this->message = $msg;

			return $ok;
		}

		/**
		 * retrieve this authentication data from database
		 *
		 * @return array|null;
		 */
		private function get() {
			$sql = "
				SELECT * FROM oz_authenticator
				WHERE auth_label =:label AND auth_for =:forv
				LIMIT 0,1";

			$req = OZoneDb::getInstance()->select( $sql, array(
				'label' => $this->label,
				'forv'  => $this->for_value
			) );

			if ( $req->rowCount() > 0 ) {
				$data = $req->fetch();
				$req->closeCursor();

				return $data;
			}

			return null;
		}

		/**
		 * cancel this authentication process
		 *
		 * @return int
		 */
		public function cancel() {
			$sql = "
				DELETE FROM oz_authenticator 
				WHERE auth_label =:label AND auth_for =:for";

			return OZoneDb::getInstance()->delete( $sql, array(
				'label' => $this->label,
				'for'   => $this->for_value
			) );
		}

		/**
		 * update the authentication counter when code fails
		 *
		 * @return int
		 */
		private function tryUpdateCounter() {
			$sql = "
				UPDATE oz_authenticator 
				SET auth_try_count = auth_try_count + 1
				WHERE auth_label =:label AND auth_for =:forv";

			return OZoneDb::getInstance()->update( $sql, array(
				'label' => $this->label,
				'forv'  => $this->for_value
			) );
		}

		/**
		 * return the last message
		 * @return null|string
		 */
		public function getMessage() {
			return $this->message;
		}
	}