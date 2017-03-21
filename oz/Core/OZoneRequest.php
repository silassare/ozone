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

	use OZONE\OZ\Exceptions\OZoneBaseException;
	use OZONE\OZ\Exceptions\OZoneForbiddenException;
	use OZONE\OZ\Exceptions\OZoneNotFoundException;
	use OZONE\OZ\OZone;

	defined( 'OZ_SELF_SECURITY_CHECK' ) or die;

	final class OZoneRequest {

		/**
		 * api key regexp:
		 *    -ozone api key is a 4 groups of 8 alphanumerics (A-Z0-9) separated with -
		 *    -length: 35 chars (32 [A-Z0-9] + 3 [-])
		 *
		 * @var string
		 */
		private static $API_KEY_REG = "#^[A-Z0-9]{8}-[A-Z0-9]{8}-[A-Z0-9]{8}-[A-Z0-9]{8}$#";

		/**
		 * @var string
		 */
		private static $REQ_CURRENT_API_KEY = '';

		/**
		 * the current client object
		 *
		 * @var OZoneClient
		 */
		private static $client;

		/**
		 * init incoming request check
		 */
		public static function initCheck() {

			//if the request uri is not ok
			if ( !OZoneUri::parseRequestUri() ) {
				$origin = $_SERVER[ 'HTTP_ORIGIN' ];

				if ( !self::isCrossSiteAllowed() AND  $origin != OZ_APP_MAIN_URL ){ /**/
					self::attackProcedure( new OZoneForbiddenException( 'OZ_CROSS_SITE_REQUEST_NOT_ALLOWED', array( 'origin' => $origin )) );
				} else if ( $_SERVER[ 'REQUEST_URI' ] === '/' ) {/* check if it is root */
					//SILO::TODO show api description page
					self::attackProcedure( new OZoneForbiddenException() );
				} else if ( empty( self::getApiKey() ) ) { /* if no api key go hard: forbid */
					self::attackProcedure( new OZoneForbiddenException() );
				} else { /* else we just not found the requested uri*/
					self::attackProcedure( new OZoneNotFoundException() );
				}
			}

			//is it a pre-filter request for CORS?
			if ( self::isOptions() ) {
				self::setInitialHeaders( true, self::getSafeOriginUrl() );
				//no more things to do, just exit after
				exit;
			} else if ( self::isClientRequired() ) { /* do we require a client for this service? */
				if ( self::isForFile() ) {
					self::initClientObjectForFile();
				} else {
					self::initClientObject();
				}
			} else {
				//this service does not require a client object
				//for now any call to \OZONE\OZ\Core\OZoneRequest::getCurrentClient() should not be tolerated
				//please be aware look in \OZONE\OZ\Core\OZoneRequest::getCurrentClient()
			}

			//let's start the session
			OZoneSessions::start();
		}

		/**
		 * init client object
		 */
		private static function initClientObject() {
			$client = null;
			$api_key = self::getApiKey();

			if ( empty( $api_key ) ) {
				self::attackProcedure();
			}

			$client = OZoneClient::getInstanceWith( $api_key, 'clid' );

			if ( empty( $client ) OR !$client->checkClient() ) {
				self::attackProcedure();
			}

			define( 'OZ_SESSION_MAX_LIFE_TIME', $client->getSessionMaxLifeTime() );

			self::$client = $client;
			self::setInitialHeaders( true, $client->getClientUrl() );
		}

		/**
		 * init client object for file request
		 */
		private static function initClientObjectForFile() {
			//SILO:: please BE AWARE!!!

			$client = null;
			$sid = OZoneSessions::getCookiesSid();

			if ( empty( $sid ) ) {
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

		/**
		 * get current client object
		 *
		 * if the 'require_client' property is set to false in the requested service's settings
		 * you should not call this otherwise an exception is raised
		 *
		 * @return \OZONE\OZ\Core\OZoneClient
		 * @throws \Exception
		 */
		public static function getCurrentClient() {

			if ( self::$client instanceof OZoneClient ) {
				return self::$client;
			}

			//dev error
			//this service does not require a client object
			//but a call to occurs
			$svc_name = $_REQUEST[ 'oz_req_service' ];

			throw new \Exception( "client not defined, maybe 'require_client' is set to false in your oz.services.list for the service '$svc_name' ." );
		}

		/**
		 * set required http headers
		 *
		 * @param bool        $allow_cors should we allow CORS
		 * @param string|null $uri        the safe origin url
		 */
		private static function setInitialHeaders( $allow_cors = false, $uri = null ) {

			// evitons le Click-jacking : qui consiste à tromper un user à partir d'un frame

			header( "X-Frame-Options: DENY" );

			if ( $allow_cors ) {

				if ( self::isOptions() ) {
					//enable self made headers
					header( "Access-Control-Allow-Headers: accept, " . OZ_APIKEY_HEADER_NAME );
					//TODO:: retrieve accepted methods from the desired service
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

		/**
		 * get the safe origin url to be sent in CORS headers
		 *
		 * @return string
		 */
		private static function getSafeOriginUrl() {
			$origin = $_SERVER[ 'HTTP_ORIGIN' ];

			if ( OZoneClientUtils::checkSafeOriginUrl( $origin ) ) {
				return $origin;
			}

			return OZ_APP_MAIN_URL;
		}

		/**
		 * get the current request api key
		 *
		 * @return string
		 */
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

			if ( preg_match( self::$API_KEY_REG, $key ) ) {
				self::$REQ_CURRENT_API_KEY = $key;
			}

			return $key;
		}

		/**
		 * check if it is an OPTIONS request method
		 *
		 * @return bool
		 */
		public static function isOptions() {
			return $_SERVER[ 'REQUEST_METHOD' ] === 'OPTIONS';
		}

		/**
		 * check if it is a POST request method
		 *
		 * @return bool
		 */
		public static function isPost() {
			return $_SERVER[ 'REQUEST_METHOD' ] === 'POST';
		}

		/**
		 * check if it is a GET request method
		 *
		 * @return bool
		 */
		public static function isGet() {
			return $_SERVER[ 'REQUEST_METHOD' ] === 'GET';
		}

		/**
		 * check if it is a PUT request method
		 *
		 * @return bool
		 */
		public static function isPut() {
			return $_SERVER[ 'REQUEST_METHOD' ] === 'PUT';
		}

		/**
		 * check if it is a DELETE request method
		 *
		 * @return bool
		 */
		public static function isDelete() {
			return $_SERVER[ 'REQUEST_METHOD' ] === 'DELETE';
		}

		/**
		 * check if it is a file request for a file service
		 *
		 * @return bool
		 */
		public static function isForFile() {
			$file_services = OZone::getFileServices();

			return isset( $_REQUEST[ 'oz_req_service' ] ) AND self::isGet() AND array_key_exists( $_REQUEST[ 'oz_req_service' ], $file_services );
		}

		/**
		 * check if the requested service allow cross site request without api key
		 *
		 * @return bool
		 */
		public static function isCrossSiteAllowed() {

			if ( isset( $_REQUEST[ 'oz_req_service' ] ) ) {

				$svc = OZoneSettings::get( 'oz.services.list', $_REQUEST[ 'oz_req_service' ] );

				if ( !empty( $svc ) AND isset( $svc[ 'cross_site' ] ) ) {
					return $svc[ 'cross_site' ];
				}
			}

			return false;
		}

		/**
		 * check if the requested service require a valid client
		 *
		 * @return bool
		 */
		public static function isClientRequired() {

			if ( isset( $_REQUEST[ 'oz_req_service' ] ) ) {

				$svc = OZoneSettings::get( 'oz.services.list', $_REQUEST[ 'oz_req_service' ] );

				if ( !empty( $svc ) AND isset( $svc[ 'require_client' ] ) ) {
					return $svc[ 'require_client' ];
				}
			}

			return true;
		}

		/**
		 * run procedure when some unhandled ozone exceptions occurs
		 *
		 * @param \OZONE\OZ\Exceptions\OZoneBaseException|null $err the current ozone exception
		 */
		public static function attackProcedure( OZoneBaseException $err = null ) {

			if ( $err == null ) {
				$err = new OZoneForbiddenException();
			}

			oz_logger( debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS ) );

			oz_logger( $_SERVER[ 'REQUEST_URI' ] );

			$err->procedure();// exit;
		}

	}
