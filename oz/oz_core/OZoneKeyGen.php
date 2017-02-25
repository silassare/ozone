<?php
	//SILO:: Protect from unauthorized access/include
	defined( 'OZ_SELF_SECURITY_CHECK' ) or die;

	final class OZoneKeyGen {
		//caracteres alphanumeriques : case insensitive
		const ALFA_NUM_INS = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';

		private static function getSalt( $name ) {

			return OZoneSettings::get( 'oz.keygen.salt', $name );
		}

		public static function passHash( $pass ) {

			return md5( $pass );
		}

		public static function genFileKey( $path ) {
			$key = md5_file( $path );
			$salt = self::getSalt( 'OZ_FKEY_GEN_SALT' );

			//SILO
			//make sure to make differences between each cloned file key
			//if don't all clone will have the same fkey as the original file

			$key .= time() . rand( 111111, 999999 );

			return md5( $key . $salt );
		}

		public static function genRandomString( $len = 32 ) {
			$chars = self::ALFA_NUM_INS;
			$max = strlen( $chars ) - 1;
			$s = '';

			for ( $i = 0 ; $i < $len ; ++$i ) {
				$s .= $chars[ rand( 0, $max ) ];
			}

			return $s;
		}

		public static function genSid() {
			$salt = self::getSalt( 'OZ_SID_GEN_SALT' );

			return md5( self::genRandomString() . $salt );
		}

		public static function genClid( $domain ) {

			$salt = self::getSalt( 'OZ_CLID_GEN_SALT' );
			$str = md5( $domain . $salt );

			return strtoupper( implode( '-', str_split( $str, 8 ) ) );
		}

		public static function genAuthCode() {
			return rand( 111111, 999999 );
		}

		public static function genAuthToken( $key ) {
			$salt = self::getSalt( 'OZ_AUTH_TOKEN_SALT' );

			return md5( self::genRandomString() . $key . time() . $salt );
		}
	}