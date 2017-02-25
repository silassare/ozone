<?php
	//SILO:: Protect from unauthorized access/include
	defined( 'OZ_SELF_SECURITY_CHECK' ) or die;

	final class OZoneUserUtils {

		public static function getUserObject( $uid ) {
			$user_class = OZoneSettings::get( 'oz.user', 'OZ_USER_CLASS' );
			$user_obj = OZone::obj( $user_class, $uid );

			if ( is_subclass_of( $user_obj, 'OZoneUserBase' ) ) {
				return $user_obj;
			}

			throw "your custom user class '$user_class' must extends 'OZoneUserBase'";
		}

		public static function userVerified() {
			$user_verified = OZoneSessions::get( 'ozone_user:verified' );

			//toute modification de la methode de verification doit etre aussi faite partout
			return !empty( $user_verified ) AND $user_verified === true;
		}

		public static function logOn( $uid ) {
			//SILO::TODO 
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
			OZone::obj( 'OZoneSessions' )->restart();

			$result = self::getUserObject( $uid )->getUserInfos( array( $uid ) );

			if ( !isset( $result[ $uid ] ) ) {
				//compte supprimer ou disfonctionnement
				throw new OZoneErrorUnverifiedUser();
			}

			//on restore les valeurs de la precedente session si possible
			foreach ( $saved_data as $key => $val ) {
				$_SESSION[ $key ] = $val;
			}

			$infos = $result[ $uid ];
			//SILO::TODO why not ask if user really want to attach his account to a client?
			$token = OZoneRequest::getClient()->logUser( $uid );

			OZoneSessions::set( 'ozone_user:infos', $infos );
			//shortcuts
			OZoneSessions::set( 'ozone_user:user_id', $uid );
			OZoneSessions::set( 'ozone_user:verified', true );
			OZoneSessions::set( 'ozone_user:token', $token );

			$infos[ 'token' ] = $token;

			return $infos;
		}

		public static function passOk( $field, $value, $pass ) {
			$uid = self::getUserId( $field, $value, $pass );

			return ( !empty( $uid ) );
		}

		private static function getUserId( $field, $value, $pass ) {
			if ( $field === 'phone' ) {
				$sql = "SELECT * FROM oz_users WHERE ( user_phone =:v AND user_pass =:p ) LIMIT 0,1";
			} else if ( $field === 'email' ) {
				$sql = "SELECT * FROM oz_users WHERE ( user_email =:v AND user_pass =:p ) LIMIT 0,1";
			} else {
				return null;
			}

			$req = OZone::obj( 'OZoneDb' )->select( $sql, array(
				'v' => $value,
				'p' => OZoneKeyGen::passHash( $pass )
			) );

			$data = $req->fetch();

			$req->closeCursor();

			if ( $data ) {
				return $data[ 'user_id' ];
			}

			return null;
		}

		public static function tryLogOnWith( $field, $value, $pass ) {
			$uid = self::getUserId( $field, $value, $pass );

			if ( empty( $uid ) ) {
				//compte supprimer ou disfonctionnement
				throw new OZoneErrorUnverifiedUser();
			}

			return self::logOn( $uid );
		}

		//SILO: avant toute modification verifier l'utilisation de logOut lors de l'inscription
		public static function logOut() {
			$current_uid = OZoneSessions::get( 'ozone_user:user_id' );

			//SILO:: just unset don't destroy because we could need to store some data in $_SESSION after loging out
			session_unset();

			if ( !empty( $current_uid ) ) {
				OZoneRequest::getClient()->removeUser( $current_uid );
				//peut etre utile
				OZoneSessions::set( 'ozone_user:user_id', $current_uid );
			}
		}

		public static function registered( $phone ) {
			$sql = "SELECT * FROM oz_users WHERE user_phone = :n";

			$req = OZone::obj( 'OZoneDb' )->select( $sql, array(
				'n' => $phone
			) );

			$count = $req->rowCount();

			$req->closeCursor();

			return ( $count != 0 ) ? true : false;
		}

		public static function isPhoneNumberPossible( $number ) {
			$number_reg = '#^\+\d{6,14}$#';

			return preg_match( $number_reg, $number );
		}

		public static function authorizedCountry( $cc2 ) {
			$sql = "SELECT * FROM oz_countries WHERE country_cc2 = :c AND country_ok = :ok";
			$req = OZone::obj( 'OZoneDb' )->select( $sql, array(
				'c'  => $cc2,
				'ok' => 1
			) );

			$count = $req->rowCount();

			if ( $count >= 0 )
				return true;

			return false;
		}
	}
