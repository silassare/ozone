<?php
	/**
	 * Copyright (c) Silas E. Sare <emile.silas@gmail.com>
	 *
	 * This file is part of the OZone package.
	 *
	 * For the full copyright and license information, please view the LICENSE
	 * file that was distributed with this source code.
	 */

	namespace OZONE\OZ\User;

	use OZONE\OZ\Core\OZoneDb;
	use OZONE\OZ\Core\OZoneRequest;
	use OZONE\OZ\Core\OZoneSessions;
	use OZONE\OZ\Core\OZoneSettings;
	use OZONE\OZ\Crypt\DoCrypt;
	use OZONE\OZ\Exceptions\OZoneUnverifiedUserException;
	use OZONE\OZ\OZone;

	defined( 'OZ_SELF_SECURITY_CHECK' ) or die;

	/**
	 * Class OZoneUserUtils
	 * @package OZONE\OZ\User
	 */
	final class OZoneUserUtils {

		/**
		 * @param $uid
		 *
		 * @return \OZONE\OZ\User\OZoneUserBase
		 *
		 * @throws \OZONE\OZ\Exceptions\OZoneInternalError    when can't load 'oz.user' setting
		 * @throws \Exception                                when user class does not extends \OZONE\OZ\User\OZoneUserBase
		 */
		public static function getUserObject( $uid ) {
			$user_class = OZoneSettings::get( 'oz.user', 'OZ_USER_CLASS' );
			$user_obj = OZone::obj( $user_class, $uid );
			$user_base_class = 'OZONE\OZ\User\OZoneUserBase';

			if ( is_subclass_of( $user_obj, $user_base_class ) ) {
				return $user_obj;
			}

			throw new \Exception( "your custom user class $user_class should extends $user_base_class" );
		}

		/**
		 * check if the current user is verified
		 *
		 * @return bool    true when user is verified, false otherwise
		 */
		public static function userVerified() {
			$user_verified = OZoneSessions::get( 'ozone_user:verified' );

			//toute modification de la methode de verification pourait remettre en cause la politique de securite
			return !empty( $user_verified ) AND $user_verified === true;
		}

		/**
		 * logon the user that have the give user id
		 *
		 * @param string|int $uid the user id
		 *
		 * @return array the user data
		 * @throws \Exception
		 * @throws \OZONE\OZ\Exceptions\OZoneUnverifiedUserException when user not found in database or something went wrong
		 */
		public static function logOn( $uid ) {
			//TODO
			//when user change his password, force login again, by deleting :
			//	- all oz_sessions rows associated with user
			//	- all oz_clients_users rows associated with user

			$saved_data = array();
			$current_uid = OZoneSessions::get( 'ozone_user:user_id' );

			//si l'utilisateur courant est le precedent on sauvegarde les donnees de la precedente session
			if ( !empty( $current_uid ) AND $current_uid === $uid ) {
				$saved_data = array_merge( $saved_data, $_SESSION );
			}

			//on demarre une nouvelle session
			OZoneSessions::restart();

			$result = self::getUserObject( $uid )->getUserData( array( $uid ) );

			if ( !isset( $result[ $uid ] ) ) {
				//compte supprimer ou disfonctionnement
				throw new OZoneUnverifiedUserException();
			}

			//on restore les valeurs de la precedente session si possible
			foreach ( $saved_data as $key => $val ) {
				$_SESSION[ $key ] = $val;
			}

			$user_data = $result[ $uid ];
			//TODO why not ask if user really want to attach his account to this client?
			$token = OZoneRequest::getCurrentClient()->logUser( $uid );

			OZoneSessions::set( 'ozone_user:data', $user_data );
			//shortcuts
			OZoneSessions::set( 'ozone_user:user_id', $uid );
			OZoneSessions::set( 'ozone_user:verified', true );
			OZoneSessions::set( 'ozone_user:token', $token );

			$user_data[ 'token' ] = $token;

			return $user_data;
		}

		/**
		 * logout the current user
		 *
		 * @throws \Exception
		 */
		public static function logOut() {
			$current_uid = OZoneSessions::get( 'ozone_user:user_id' );

			//SILO:: just unset don't destroy because we could need to store some data in $_SESSION after loging out
			session_unset();

			if ( !empty( $current_uid ) ) {
				OZoneRequest::getCurrentClient()->removeUser( $current_uid );
				//peut etre utile
				OZoneSessions::set( 'ozone_user:user_id', $current_uid );
			}
		}

		/**
		 * check if a password match a given field value and password
		 *
		 * @param string $field the field name
		 * @param string $value the field value
		 * @param string $pass  the password
		 *
		 * @return bool    true if password is ok, false otherwise
		 */
		public static function passOk( $field, $value, $pass ) {

			$data = self::searchRegisteredUserWith( $field, $value );

			if ( empty( $data ) ) {
				return false;
			}

			$crypt_obj = new DoCrypt();

			return $crypt_obj->passCheck( $pass, $data[ 'user_pass' ] );
		}

		/**
		 * try to logon a user with a given field value and password
		 *
		 * @param string $field the field name
		 * @param string $value the field value
		 * @param string $pass  the password
		 *
		 * @return array|false the user data
		 */
		public static function tryLogOnWith( $field, $value, $pass ) {

			$data = self::searchRegisteredUserWith( $field, $value );
			$crypt_obj = new DoCrypt();

			if ( empty( $data ) OR !$crypt_obj->passCheck( $pass, $data[ 'user_pass' ] ) ) {
				return false;
			}

			return self::logOn( $data[ 'user_id' ] );
		}

		/**
		 * search for registered user with a given field value and password
		 *
		 * @param string $field the field name
		 * @param string $value the field value
		 *
		 * @return array|null the user data if successful, null otherwise
		 * @throws
		 */

		private static function searchRegisteredUserWith( $field, $value ) {

			if ( $field === 'phone' ) {
				$sql = "SELECT * FROM oz_users WHERE user_phone =:v LIMIT 0,1";
			} else if ( $field === 'email' ) {
				$sql = "SELECT * FROM oz_users WHERE user_email =:v LIMIT 0,1";
			} else {
				throw new \InvalidArgumentException( "you should provide 'phone' or 'email'" );
			}

			$req = OZoneDb::getInstance()->select( $sql, array(
				'v' => $value
			) );

			$data = null;

			if ( $req->rowCount() ) {
				$data = $req->fetch();
			}

			$req->closeCursor();

			return $data;
		}

		/**
		 * check if an user is already registered with a given field value (phone or email)
		 *
		 * @param string $field the field name
		 * @param string $value the filed value
		 *
		 * @return bool true if an user is using this value, false otherwise
		 */
		public static function registered( $field, $value ) {
			$data = self::searchRegisteredUserWith( $field, $value );

			return !empty( $data );
		}

		/**
		 * check if the phone could be a valid phone number
		 *
		 * @param string $phone the phone number
		 *
		 * @return bool
		 */
		public static function isPhoneNumberPossible( $phone ) {
			$number_reg = '#^\+\d{6,14}$#';

			return !!preg_match( $number_reg, $phone );
		}

		/**
		 * check if the country with a given this cc2 (country code 2) is authorized
		 *
		 * @param string $cc2 the country code 2
		 *
		 * @return bool    true if country is authorized, false otherwise
		 */
		public static function authorizedCountry( $cc2 ) {
			$sql = "SELECT * FROM oz_countries WHERE country_cc2 = :c AND country_ok = :ok";
			$req = OZoneDb::getInstance()->select( $sql, array(
				'c'  => $cc2,
				'ok' => 1
			) );

			return ( $req->rowCount() > 0 ) ? true : false;
		}
	}
