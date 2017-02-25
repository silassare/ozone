<?php
	//SILO:: Protect from unauthorized access/include
	defined( 'OZ_SELF_SECURITY_CHECK' ) or die;

	final class OZoneSessions {
		private static $instance = null;
		private static $temp = array();
		private static $counter = 0;

		public function __construct() {
			if ( !empty( self::$instance ) ) {
				return self::$instance;
			}

			return self::$instance = $this;
		}

		private static function keyCheck( $key ) {
			$key_reg = "#^(?:[a-zA-Z_][a-zA-Z0-9_]*)(?:\:[a-zA-Z_][a-zA-Z0-9_]*)*$#";
			$max_deep = 5;

			if ( !preg_match( $key_reg, $key ) ) {
				throw new Exception( "session key '$key' not well formed, use something like 'group:key' " );
			}

			$route = explode( ':', $key );

			if ( count( $route ) > $max_deep ) {
				throw new Exception( "session key '$key' is too deep, maximum deep is $max_deep" );
			}

			return $route;
		}

		private static function getNext( $source, $key ) {

			if ( is_array( $source ) AND isset( $source[ $key ] ) ) {
				return $source[ $key ];
			}

			return null;
		}

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

		public static function remove( $key ) {
			self::set( $key, null );
		}

		public function restart() {
			if ( session_id() ) {
				session_unset();
				session_destroy();
			}

			$this->start();
		}

		private static function isValideSid( $sid ) {
			$ans = self::sidPreValidation( $sid, true );

			return $ans[ 0 ];
		}

		private static function sidPreValidation( $sid, $force_try_again = false ) {
			if ( !is_string( $sid ) OR !preg_match( "#[a-z0-9]{32}#", $sid ) )
				return array( false, false );

			if ( !isset( self::$temp[ $sid ] ) OR !!$force_try_again ) {
				$sid = OZoneStr::clean( $sid );
				$sql = 'SELECT sess_data FROM oz_sessions WHERE sess_sid =:sid LIMIT 0,1';

				$req = OZone::obj( 'OZoneDb' )->select( $sql, array(
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

		public static function getCurrentSid() {
			$sid = null;

			if ( isset( $_COOKIE[ OZ_COOKIE_SID_NAME ] ) ) {
				$sid = $_COOKIE[ OZ_COOKIE_SID_NAME ];
			}

			return $sid;
		}

		public function start() {
			$sid = self::getCurrentSid();

			if ( empty( $sid ) OR !self::isValideSid( $sid ) ) {
				$sid = OZoneKeyGen::genSid();
			}

			session_name( OZ_COOKIE_SID_NAME );

			session_id( $sid );
			//SILO:: sans cela on ne poura pas enregistrer les donner dans la bd car write est appeler a la fin de l'execution du script
			//lorsque tous les objets sont detruit OZoneDb n'existent plus lors de l'appel de OZoneSessions->write
			//donc on force l'execution juste avant la destruction des objets
			register_shutdown_function( 'session_write_close' );

			session_set_save_handler(
				array( $this, 'open' ),
				array( $this, 'close' ),
				array( $this, 'read' ),
				array( $this, 'write' ),
				array( $this, 'destroy' ),
				array( $this, 'gc' )
			);

			session_cache_limiter( 'none' );

			self::setSidCookieHeader();

			session_start();
		}

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

		public function open() {
			return $this->gc();
		}

		public function read( $sid ) {
			$ans = self::sidPreValidation( $sid );

			return $ans[ 1 ];
		}

		public function write( $sid, $data ) {
			$sid = OZoneStr::clean( $sid );
			$data = serialize( $data );
			$expire = intval( time() + OZ_SESSION_MAX_LIFE_TIME );

			$sql = 'INSERT INTO oz_sessions (sess_sid, sess_data, sess_expire) VALUES(:sid,:data,:exp)
			ON DUPLICATE KEY UPDATE sess_data =:data, sess_expire =:exp';
			OZone::obj( 'OZoneDb' )->execute( $sql, array(
				'sid'  => $sid,
				'data' => $data,
				'exp'  => $expire
			) );

			return true;
		}

		//fermeture
		public function close() {
			//SILO:: ici on ferme la bdd
			//inutile on utilise pdo et OZoneDb fait deja le boullot voir sont destructeur
			return true;
		}

		public function destroy( $sid ) {
			$sid = OZoneStr::clean( $sid );

			if ( isset( $_COOKIE[ session_name() ] ) ) {
				//SILO:: force la peremption du cookies chez le client
				setcookie( session_name(), '', time() - 43200 );
			}

			$sql = 'DELETE FROM oz_sessions WHERE sess_sid =:sid';
			OZone::obj( 'OZoneDb' )->delete( $sql, array(
				'sid' => $sid
			) );

			return true;
		}

		public function gc() {
			$sql = 'DELETE FROM oz_sessions WHERE sess_expire < ' . time();
			OZone::obj( 'OZoneDb' )->delete( $sql );

			return true;
		}
	}