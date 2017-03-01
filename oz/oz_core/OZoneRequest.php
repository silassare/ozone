<?php
	//SILO:: Protect from unauthorized access/include
	defined( 'OZ_SELF_SECURITY_CHECK' ) or die;

	final class OZoneRequest {
		private static $APIKEY_REG = "#^[A-Z0-9]{8}-[A-Z0-9]{8}-[A-Z0-9]{8}-[A-Z0-9]{8}$#";
		private static $REQ_CURRENT_API_KEY = null;

		private static $client;//SILO::<-- where client object is stored

		public static function initCheck() {
			if ( !OZoneUri::checkRequestedServiceName() ) {
				if ( $_SERVER[ 'REQUEST_URI' ] === '/' ) {
					//SILO::TODO show api description page
					self::attackProcedure( new OZoneErrorForbidden() );
				} else if ( empty( self::getApiKey() ) ) {
					self::attackProcedure( new OZoneErrorForbidden() );
				} else {
					self::attackProcedure( new OZoneErrorNotFound() );
				}
			}

			if ( self::isOptions() ) {
				//SILO:: it is a pre-filter request for CORS just exit after
				self::setInitialHeaders( true, self::getSafeOriginUrl() );
				exit;

			} else if ( self::isClientRequired() ) {
				if ( self::isForFile() ) {
					self::initClientObjectForFile();
				} else {
					self::initClientObject();
				}
			} else {
				//this service does not require a client object
				//please be aware look in self::getClient
			}

			//on demarre la session
			$sess = OZone::obj( 'OZoneSessions' );
			$sess->start();
		}

		private static function getSafeOriginUrl() {
			$origin = $_SERVER[ 'HTTP_ORIGIN' ];
			if ( OZoneClientUtils::checkSafeOriginUrl( $origin ) ) {
				return $origin;
			}

			return OZ_APP_MAIN_URL;
		}

		private static function initClientObject() {
			$client = null;
			$apikey = self::getApiKey();

			if ( empty( $apikey ) ){
				self::attackProcedure();
			}

			$client = OZoneClient::getInstanceWith( $apikey, 'clid' );

			if ( empty( $client ) OR !$client->checkClient() ) {
				self::attackProcedure();
			}

			define( 'OZ_SESSION_MAX_LIFE_TIME', $client->getSessionMaxLifeTime() );

			self::$client = $client;
			self::setInitialHeaders( true, $client->getClientUrl() );
		}

		private static function initClientObjectForFile() {
			//SILO:: please BE AWARE!!!

			$client = null;
			$sid = OZoneSessions::getCurrentSid();

			if ( empty( $sid ) ){
				self::attackProcedure();
			}

			$client = OZoneClient::getInstanceWith( $sid, 'sid' );

			if ( empty( $client ) OR !$client->checkClient() ) {

				self::attackProcedure();
			}

			define( 'OZ_SESSION_MAX_LIFE_TIME', $client->getSessionMaxLifeTime() );

			self::setInitialHeaders( true, $client->getClientUrl() );
			self::$client = $client;

		}

		public static function getClient() {

			if ( self::$client instanceof OZoneClient ) {
				return self::$client;
			}

			//dev error
			//this service does not require a client object
			//but a call of getClient occure
			$svc_name = $_REQUEST[ 'oz_req_service' ];

			throw new Exception( "client not defined, maybe 'require_client' is set to false in your oz.services.list for the service '$svc_name' ." );
		}

		//SILO:: Allow Cross Origine Ressource Sharing
		private static function setInitialHeaders( $allow_cors = false, $uri = null ) {

			//evitons le Clickjacking: qui consiste a tromper un user à partir d'un frame
			header( "X-Frame-Options: DENY" );

			if ( $allow_cors ) {

				if( self::isOptions() ) {
					//enable self made headers
					header( "Access-Control-Allow-Headers: accept, " . OZ_APIKEY_HEADER_NAME );
					//TODO:: retieve acepted methods from the desired service
					header( "Access-Control-Allow-Methods: OPTIONS, GET, POST, PUT" );
					//TODO:: set max age to session max life time
					header( "Access-Control-Max-Age: 86400" );
				}

				if ( !empty( $uri ) ) {
					//SILO:: enable browser to make CORS request from $uri
					header( "Access-Control-Allow-Origin: $uri" );
					//SILO:: enable browser to send CORS request with cookies
					header( "Access-Control-Allow-Credentials: true" );
				}
			}
		}

		public static function getApiKey() {
			$key = "";

			//test if already verified
			//or
			//try get from http headers
			if ( !empty( self::$REQ_CURRENT_API_KEY ) ) {
				$key = self::$REQ_CURRENT_API_KEY;

			} elseif ( isset( $_SERVER[ OZ_HTTP_APIKEY_HEADER_NAME ] ) ) {

				$key = $_SERVER[ OZ_HTTP_APIKEY_HEADER_NAME ];
			}

			if ( preg_match( self::$APIKEY_REG, $key ) ) {
				self::$REQ_CURRENT_API_KEY = $key;

				return $key;
			}

			return null;
		}

		public static function isPost() {
			return $_SERVER[ 'REQUEST_METHOD' ] === 'POST';
		}

		public static function isGet() {
			return $_SERVER[ 'REQUEST_METHOD' ] === 'GET';
		}

		public static function isPut() {
			return $_SERVER[ 'REQUEST_METHOD' ] === 'PUT';
		}

		public static function isOptions() {
			return $_SERVER[ 'REQUEST_METHOD' ] === 'OPTIONS';
		}

		public static function isDelete() {
			return $_SERVER[ 'REQUEST_METHOD' ] === 'DELETE';
		}

		public static function isForFile() {
			$file_services = OZone::getFileServices();
			//SILO:: Ne pas utiliser HTTP_REFERER car contient des risques de securite
			//de plus https n'envoie souvent pas HTTP_REFERER

			return isset( $_REQUEST[ 'oz_req_service' ] ) AND self::isGet() AND array_key_exists( $_REQUEST[ 'oz_req_service' ], $file_services );
		}

		public static function isClientRequired() {

			if ( isset( $_REQUEST[ 'oz_req_service' ] ) ) {

				$svc = OZone::getService( $_REQUEST[ 'oz_req_service' ] );

				if ( !empty( $svc ) AND isset( $svc[ 'require_client' ] ) ) {
					return $svc[ 'require_client' ];
				}
			}

			return true;
		}

		public static function attackProcedure( OZoneError $err = null ) {
			//SILO:: nous sommes peut-etre victime d'une attaque
			if ( $err == null ) {
				$err = new OZoneErrorForbidden();
			}

			oz_logger( debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS ) );

			oz_logger( $_SERVER[ 'REQUEST_URI' ] );

			$err->procedure();// exit;
		}

	}
