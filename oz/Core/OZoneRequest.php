<?php
	/**
	 * Copyright (c) Emile Silas Sare <emile.silas@gmail.com>
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

	defined('OZ_SELF_SECURITY_CHECK') or die;

	final class OZoneRequest
	{

		/**
		 * api key regexp:
		 *    -ozone api key is a 4 groups of 8 alphanumerics (A-Z0-9) separated with -
		 *    -length: 35 chars (32 [A-Z0-9] + 3 [-])
		 *
		 * @var string
		 */
		private static $api_key_reg = '#^[A-Z0-9]{8}-[A-Z0-9]{8}-[A-Z0-9]{8}-[A-Z0-9]{8}$#';

		/**
		 * @var string
		 */
		private static $req_header_api_key = '';

		/**
		 * the current client object
		 *
		 * @var OZoneClient
		 */
		private static $client;

		/**
		 * init incoming request check
		 */
		public static function initCheck()
		{
			// if the requested uri is not ok
			if (!OZoneUri::parseRequestUri()) {
				// is it root
				if ($_SERVER['REQUEST_URI'] === '/') {
					// TODO 
					// show api usage doc when this condition are met:
					//  - we are in api mode
					//	- debuging or allowed in settings
					// show welcome friendly page when this conditions are met:
					//  - we are in web mode
					self::attackProcedure(new OZoneForbiddenException());
				} else {
					self::attackProcedure(new OZoneNotFoundException());
				}
			}

			// reject cross site request from not allowed domain
			// SILO:: header spoofing can help hacker bypass this
			// so don't be 100% sure, lol
			if (!self::isCrossSiteAllowed()) {
				$origin   = self::getRequestOriginOrReferer();
				$main_url = OZoneSettings::get('oz.config', 'OZ_APP_MAIN_URL');

				if (!($origin === $main_url)) {
					self::attackProcedure(new OZoneForbiddenException('OZ_CROSS_SITE_REQUEST_NOT_ALLOWED', ['origin' => $origin]));
				}
			}

			// is it a pre-filter request for CORS?
			if (self::isOptions()) {
				self::setInitialHeaders();
				// no more things to do, just exit after
				exit;
			}

			if (self::isClientRequired()) { /* do we require a client for this service? */
				if (self::isForFile()) {
					self::initClientObjectForFile();
				} else {
					self::initClientObject();
				}
			}// else {
			// this service does not require a client object
			// for now any call to \OZONE\OZ\Core\OZoneRequest::getCurrentClient() should not be tolerated
			// please be aware look in \OZONE\OZ\Core\OZoneRequest::getCurrentClient()
			// }

			// let's start the session
			OZoneSessions::start();
		}

		/**
		 * init client object
		 */
		private static function initClientObject()
		{
			$client  = null;
			$api_key = self::getApiKey();

			if (empty($api_key)) {
				self::attackProcedure(new OZoneForbiddenException('OZ_APIKEY_MISSING_IN_HEADERS'));
			}

			$client = OZoneClient::getInstanceWith($api_key, 'clid');

			if (!($client instanceof OZoneClient) OR !$client->checkClient()) {
				self::attackProcedure(new OZoneForbiddenException('OZ_YOUR_APIKEY_IS_NOT_VALID'));
			}

			define('OZ_SESSION_MAX_LIFE_TIME', $client->getSessionMaxLifeTime());

			self::$client = $client;
			self::setInitialHeaders($client);
		}

		/**
		 * init client object for file request
		 */
		private static function initClientObjectForFile()
		{
			// SILO:: please BE AWARE!!!

			$client = null;
			$sid    = OZoneSessions::getCookiesSid();

			if (empty($sid)) {
				self::attackProcedure(new OZoneForbiddenException('OZ_SESSION_INVALID'));
			}

			$client = OZoneClient::getInstanceWith($sid, 'sid');

			if (empty($client) OR !$client->checkClient()) {
				self::attackProcedure(new OZoneForbiddenException('OZ_SESSION_INVALID'));
			}

			define('OZ_SESSION_MAX_LIFE_TIME', $client->getSessionMaxLifeTime());

			self::setInitialHeaders($client);
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
		public static function getCurrentClient()
		{
			if (self::$client instanceof OZoneClient) {
				return self::$client;
			}

			// this is a DEV error
			// the requested service does not require a client object
			// but a call to OZONE\OZ\Core\OZoneRequest::getCurrentClient() occurs
			$svc_name = OZoneUri::getUriPart('oz_uri_service');

			throw new \Exception("client not defined, maybe 'require_client' is set to false in your oz.services.list for the service '$svc_name'");
		}

		/**
		 * set required http headers
		 *
		 * @param \OZONE\OZ\Core\OZoneClient|null $client the current client
		 */
		private static function setInitialHeaders(OZoneClient $client = null)
		{
			$cors_rule   = OZoneSettings::get('oz.clients', 'OZ_CORS_ALLOW_RULE');
			$default_url = OZoneSettings::get('oz.config', 'OZ_APP_MAIN_URL');

			if (!empty($client)) {
				$default_url = $client->getClientUrl();
			}

			$origin = self::getRequestOriginOrReferer();

			switch ($cors_rule) {
				case "any":
					$url = $origin;
					break;
				case "check":
				default:
					if (OZoneClientUtils::checkSafeOriginUrl($origin)) {
						$url = $origin;
					} else {
						$url = $default_url;
					}
			}

			// evitons le Click-jacking : qui consiste à tromper un user à partir d'un frame
			header("X-Frame-Options: DENY");

			if (self::isOptions()) {
				$api_key_name   = strtolower(OZoneSettings::get('oz.config', 'OZ_APP_APIKEY_NAME'));
				$api_key_header = sprintf('x-%s', $api_key_name);

				// enable self made headers
				header(sprintf('Access-Control-Allow-Headers: accept, %s', $api_key_header));
				// TODO:: retrieve accepted methods from the desired service
				header('Access-Control-Allow-Methods: OPTIONS, GET, POST, PUT, DELETE');
				// TODO:: set max age to session max life time
				header('Access-Control-Max-Age: 86400');
			}

			if (!empty($url)) {
				// Remember: CORS is not security. Do not rely on CORS to secure your web site.
				// If you are serving protected data, use cookies or OAuth tokens or something
				// other than the Origin header to secure that data. The Access-Control-Allow-Origin header 
				// in CORS only dictates which origins should be allowed to make cross-origin requests.
				// Don't rely on it for anything more.

				// allow browser to make CORS request from $uri
				header(sprintf('Access-Control-Allow-Origin: %s', $url));
				// allow browser to send CORS request with cookies
				header('Access-Control-Allow-Credentials: true');
			}
		}

		/**
		 * get the request origin or referer
		 *
		 * don't trust on what you get from this
		 *
		 * @return string
		 */
		private static function getRequestOriginOrReferer()
		{
			$origin = "";
			if (isset($_SERVER['HTTP_ORIGIN'])) {
				$origin = $_SERVER['HTTP_ORIGIN'];
			} elseif (isset($_SERVER['HTTP_REFERER'])) {// not safe at all: be aware
				$origin = $_SERVER['HTTP_REFERER'];
			}

			return $origin;
		}

		/**
		 * get the current request api key
		 *
		 * @return string
		 */
		public static function getApiKey()
		{
			$key          = '';
			$api_key_name = OZoneSettings::get('oz.config', 'OZ_APP_APIKEY_NAME');
			$header_key   = sprintf('HTTP_X_%s', strtoupper(str_replace('-', '_', $api_key_name)));

			// test if already verified
			// or
			// try get from http headers
			if (!empty(self::$req_header_api_key)) {
				$key = self::$req_header_api_key;
			} elseif (isset($_SERVER[$header_key])) {
				$key = $_SERVER[$header_key];
			} elseif (defined('OZ_OZONE_IS_WWW') AND defined('OZ_OZONE_DEFAULT_APIKEY')) {
				$key = OZ_OZONE_DEFAULT_APIKEY;
			}

			if (preg_match(self::$api_key_reg, $key)) {
				self::$req_header_api_key = $key;
			}

			return $key;
		}

		/**
		 * check if it is an OPTIONS request method
		 *
		 * @return bool
		 */
		public static function isOptions()
		{
			return $_SERVER['REQUEST_METHOD'] === 'OPTIONS';
		}

		/**
		 * check if it is a POST request method
		 *
		 * @return bool
		 */
		public static function isPost()
		{
			return $_SERVER['REQUEST_METHOD'] === 'POST';
		}

		/**
		 * check if it is a GET request method
		 *
		 * @return bool
		 */
		public static function isGet()
		{
			return $_SERVER['REQUEST_METHOD'] === 'GET';
		}

		/**
		 * check if it is a PUT request method
		 *
		 * @return bool
		 */
		public static function isPut()
		{
			return $_SERVER['REQUEST_METHOD'] === 'PUT';
		}

		/**
		 * check if it is a DELETE request method
		 *
		 * @return bool
		 */
		public static function isDelete()
		{
			return $_SERVER['REQUEST_METHOD'] === 'DELETE';
		}

		/**
		 * check if it is a file request for a file service
		 *
		 * @return bool
		 */
		public static function isForFile()
		{
			$svc_name      = OZoneUri::getUriPart('oz_uri_service');
			$file_services = OZone::getFileServices();

			return !empty($svc_name) AND self::isGet() AND array_key_exists($svc_name, $file_services);
		}

		/**
		 * check if the requested service allow cross site request without api key
		 *
		 * @return bool
		 */
		public static function isCrossSiteAllowed()
		{
			$svc_name = OZoneUri::getUriPart('oz_uri_service');

			if (!empty($svc_name)) {
				$svc = OZoneSettings::get('oz.services.list', $svc_name);

				if (!empty($svc) AND isset($svc['cross_site'])) {
					return $svc['cross_site'];
				}
			}

			return false;
		}

		/**
		 * check if the requested service require a valid client
		 *
		 * @return bool
		 */
		public static function isClientRequired()
		{
			$svc_name = OZoneUri::getUriPart('oz_uri_service');

			if (!empty($svc_name)) {
				$svc = OZoneSettings::get('oz.services.list', $svc_name);

				if (!empty($svc) AND isset($svc['require_client'])) {
					return $svc['require_client'];
				}
			}

			return true;
		}

		/**
		 * run procedure when some unhandled ozone exceptions occurs
		 *
		 * @param \OZONE\OZ\Exceptions\OZoneBaseException|null $err  the current ozone exception
		 * @param mixed                                        $desc a short description
		 */
		public static function attackProcedure(OZoneBaseException $err = null, $desc = null)
		{
			if ($err == null) {
				$err = new OZoneForbiddenException('Something went wrong or we are under attack.');
			}

			oz_logger($err);

			if (!empty($desc)) {
				oz_logger($desc);
			}

			oz_logger(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS));

			oz_logger($_SERVER['REQUEST_URI']);

			$err->procedure();// exit;
		}

	}
