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

	final class OZoneSessions {

		/**
		 * session info cache: map session id to session info
		 *
		 * @var array
		 */
		private static $temp = array();

		/**
		 * @var bool
		 */
		private static $started = false;

		/**
		 * check the given session key
		 *
		 * @param string $key the session key
		 *
		 * @return mixed
		 * @throws \Exception
		 */
		private static function keyCheck( $key ) {
			$key_reg = "#^(?:[a-zA-Z_][a-zA-Z0-9_]*)(?:\:[a-zA-Z_][a-zA-Z0-9_]*)*$#";
			$max_deep = 5;

			if ( !preg_match( $key_reg, $key ) ) {
				throw new \Exception( "session key '$key' not well formed, use something like 'group:key' " );
			}

			$route = explode( ':', $key );

			if ( count( $route ) > $max_deep ) {
				throw new \Exception( "session key '$key' is too deep, maximum deep is $max_deep" );
			}

			return $route;
		}

		private static function getNext( $source, $key ) {

			if ( is_array( $source ) AND isset( $source[ $key ] ) ) {
				return $source[ $key ];
			}

			return null;
		}

		/**
		 * set session value for a given key
		 *
		 * @param string $key
		 * @param mixed  $value
		 *
		 * @throws \Exception
		 */
		public static function set( $key, $value ) {
			$parts = self::keyCheck( $key );
			$len = count( $parts );
			$next = &$_SESSION;

			foreach ( $parts as $part ) {

				$len--;
				if ( $len AND ( !isset( $next[ $part ] ) OR !is_array( $next[ $part ] ) ) ) {
					$next[ $part ] = array();
				}

				$next = &$next[ $part ];
			}

			$next = $value;
		}

		/**
		 * get session value for a given key
		 *
		 * @param string $key the session key
		 *
		 * @return mixed
		 * @throws \Exception
		 */
		public static function get( $key ) {

			$parts = self::keyCheck( $key );
			$len = count( $parts );
			$result = $_SESSION;

			foreach ( $parts as $part ) {
				$result = self::getNext( $result, $part );
				$len--;

				if ( $len AND !is_array( $result ) ) {
					$result = null;
					break;
				}
			}

			return $result;
		}

		/**
		 * remove session value for a given key
		 *
		 * @param string $key the session key
		 */
		public static function remove( $key ) {
			self::set( $key, null );
		}

		/**
		 * validate a session for a given session id
		 *
		 * @param string $sid the session id
		 *
		 * @return bool
		 */
		private static function isValidSid( $sid ) {
			$ans = self::sidPreValidation( $sid, true );

			return $ans[ 0 ];
		}

		/**
		 * pre validate a session id to prevent too much request to database
		 *
		 * @param string $sid             the session id
		 * @param bool   $force_try_again should we ignore cache data
		 *
		 * @return array
		 */
		private static function sidPreValidation( $sid, $force_try_again = false ) {
			if ( !is_string( $sid ) OR !preg_match( "#[a-z0-9]{32}#", $sid ) )
				return array( false, false );

			if ( !isset( self::$temp[ $sid ] ) OR !!$force_try_again ) {
				$sid = OZoneStr::clean( $sid );
				$sql = "SELECT sess_data FROM oz_sessions WHERE sess_sid =:sid LIMIT 0,1";

				$req = OZoneDb::getInstance()->select( $sql, array(
					'sid' => $sid
				) );

				$c = $req->rowCount();

				if ( $c < 1 ) {
					self::$temp[ $sid ] = array( false, false );
				} else {
					$data = $req->fetch();

					$data = unserialize( $data[ 'sess_data' ] );
					self::$temp[ $sid ] = array( true, $data );
				}
			}

			return self::$temp[ $sid ];
		}

		/**
		 * get the cookies session id
		 *
		 * @return string|null
		 */
		public static function getCookiesSid() {

			if ( isset( $_COOKIE[ OZ_COOKIE_SID_NAME ] ) ) {
				return $_COOKIE[ OZ_COOKIE_SID_NAME ];
			}

			return null;
		}

		/**
		 * start a new session
		 */
		public static function start() {
			if ( self::$started ) {
				throw new \Exception( 'Session already started, you may use OZoneSessions::restart' );
			}

			self::$started = true;

			$sid = self::getCookiesSid();

			if ( empty( $sid ) OR !self::isValidSid( $sid ) ) {
				$sid = OZoneKeyGen::genSid();
			}

			session_name( OZ_COOKIE_SID_NAME );

			session_id( $sid );
			//SILO:: sans cela on ne poura pas enregistrer les données dans la bd car OZoneSessions::write est appeler a la fin de l'execution du script
			//lorsque tous les objets sont detruit OZoneDb n'existent plus
			//donc on force l'execution juste avant la destruction des objets
			register_shutdown_function( 'session_write_close' );

			$self = __CLASS__;

			session_set_save_handler(
				array( $self, 'open' ),
				array( $self, 'close' ),
				array( $self, 'read' ),
				array( $self, 'write' ),
				array( $self, 'destroy' ),
				array( $self, 'gc' )
			);

			session_cache_limiter( 'none' );

			self::setSidCookieHeader();

			session_start();
		}

		/**
		 * restart the current session
		 */
		public static function restart() {
			self::$started = false;

			if ( session_id() ) {
				session_unset();
				session_destroy();
			}

			self::start();
		}

		/**
		 * set cookies header
		 */
		private static function setSidCookieHeader() {

			if ( !defined( 'OZ_SESSION_MAX_LIFE_TIME' ) ) {
				define( 'OZ_SESSION_MAX_LIFE_TIME', 24 * 60 * 60 ); //1 jour
			}

			$cookie = session_get_cookie_params();

			$cookie[ 'httponly' ] = true;
			$cookie[ 'domain' ] = OZ_COOKIE_DOMAIN;
			$cookie[ 'lifetime' ] = OZ_SESSION_MAX_LIFE_TIME;
			session_set_cookie_params( $cookie[ 'lifetime' ], $cookie[ 'path' ], $cookie[ 'domain' ], $cookie[ 'secure' ], $cookie[ 'httponly' ] );
		}

		/**
		 * called when session open
		 *
		 * @return bool
		 */
		public static function open() {
			return self::gc();
		}

		/**
		 * read session data for a given session id
		 *
		 * @param string $sid the id of the session to read
		 *
		 * @return mixed
		 */
		public static function read( $sid ) {
			$ans = self::sidPreValidation( $sid );

			return $ans[ 1 ];
		}

		/**
		 * write data to a session with the given session id
		 *
		 * @param string $sid the id of the session to write on
		 * @param        $data
		 *
		 * @return bool
		 */
		public static function write( $sid, $data ) {
			$sid = OZoneStr::clean( $sid );
			$data = serialize( $data );
			$expire = intval( time() + OZ_SESSION_MAX_LIFE_TIME );

			$sql = "
				INSERT INTO oz_sessions (sess_sid, sess_data, sess_expire) VALUES(:sid,:data,:exp)
				ON DUPLICATE KEY UPDATE sess_data =:data, sess_expire =:exp";
			OZoneDb::getInstance()->execute( $sql, array(
				'sid'  => $sid,
				'data' => $data,
				'exp'  => $expire
			) );

			return true;
		}

		/**
		 * session close
		 *
		 * @return bool
		 */
		public static function close() {
			//SILO:: ici on ferme la bdd
			//inutile on utilise pdo et OZoneDb fait deja le boullot voir sont destructeur
			return true;
		}

		/**
		 * session destroy
		 *
		 * @param string $sid the id of the session to destroy
		 *
		 * @return bool
		 */
		public static function destroy( $sid ) {
			$sid = OZoneStr::clean( $sid );

			if ( isset( $_COOKIE[ session_name() ] ) ) {
				//SILO:: force la peremption du cookies chez le client
				setcookie( session_name(), '', time() - 43200 );
			}

			$sql = "DELETE FROM oz_sessions WHERE sess_sid =:sid";
			OZoneDb::getInstance()->delete( $sql, array(
				'sid' => $sid
			) );

			return true;
		}

		/**
		 * session garbage collector
		 *
		 * @return bool
		 */
		public static function gc() {
			$sql = "DELETE FROM oz_sessions WHERE sess_expire < " . time();
			OZoneDb::getInstance()->delete( $sql );

			return true;
		}
	}