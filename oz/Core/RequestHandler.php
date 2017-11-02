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

	use OZONE\OZ\Exceptions\BaseException;
	use OZONE\OZ\Exceptions\ForbiddenException;
	use OZONE\OZ\Exceptions\NotFoundException;
	use OZONE\OZ\OZone;

	defined('OZ_SELF_SECURITY_CHECK') or die;

	final class RequestHandler
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
		 * @var \OZONE\OZ\Core\ClientObject
		 */
		private static $client;

		/**
		 * init incoming request check
		 */
		public static function initCheck()
		{
			// if the requested uri is not ok
			if (!URIHelper::parseRequestUri()) {
				// is it root
				if ($_SERVER['REQUEST_URI'] === '/') {
					// TODO 
					// show api usage doc when this condition are met:
					//  - we are in api mode
					//	- debugging or allowed in settings
					// show welcome friendly page when this conditions are met:
					//  - we are in web mode
					self::attackProcedure(new ForbiddenException());
				} else {
					self::attackProcedure(new NotFoundException());
				}
			}

			// reject cross site request from not allowed domain
			// header spoofing can help hacker bypass this
			// so don't be 100% sure, lol
			if (!self::isCrossSiteAllowed()) {
				$origin   = self::getRequestOriginOrReferer();
				$main_url = SettingsManager::get('oz.config', 'OZ_APP_MAIN_URL');

				if (!($origin === $main_url)) {
					self::attackProcedure(new ForbiddenException('OZ_CROSS_SITE_REQUEST_NOT_ALLOWED', ['origin' => $origin]));
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
			// for now any call to \OZONE\OZ\Core\RequestHandler::getCurrentClient() should not be tolerated
			// please be aware look in \OZONE\OZ\Core\RequestHandler::getCurrentClient()
			// }

			SessionsHandler::start();
		}

		/**
		 * init client object
		 */
		private static function initClientObject()
		{
			$client  = null;
			$api_key = self::getApiKey();

			if (empty($api_key)) {
				self::attackProcedure(new ForbiddenException('OZ_API_KEY_MISSING_IN_HEADERS'));
			}

			$client = ClientObject::getInstanceWithApiKey($api_key);

			if (is_null($client) OR !$client->getClientData()
											->getValid()) {
				self::attackProcedure(new ForbiddenException('OZ_YOUR_API_KEY_IS_NOT_VALID'));
			}

			define('OZ_SESSION_MAX_LIFE_TIME', $client->getClientData()
													  ->getSessionLifeTime());

			self::$client = $client;
			self::setInitialHeaders($client);
		}

		/**
		 * init client object for file request
		 */
		private static function initClientObjectForFile()
		{
			// please BE AWARE!!!

			$client = null;
			$sid    = session_id();

			if (empty($sid)) {
				self::attackProcedure(new ForbiddenException('OZ_SESSION_INVALID'));
			}

			$client = ClientObject::getInstanceWithSessionId($sid);

			if (is_null($client) OR !$client->getClientData()
											->getValid()) {
				self::attackProcedure(new ForbiddenException('OZ_SESSION_INVALID'));
			}

			define('OZ_SESSION_MAX_LIFE_TIME', $client->getClientData()
													  ->getSessionLifeTime()());

			self::setInitialHeaders($client);
			self::$client = $client;
		}

		/**
		 * Gets current client object
		 *
		 * if the 'require_client' property is set to false in the requested service's settings
		 * you should not call this otherwise an exception is raised
		 *
		 * @return \OZONE\OZ\Core\ClientObject
		 * @throws \Exception
		 */
		public static function getCurrentClient()
		{
			if (self::$client instanceof ClientObject) {
				return self::$client;
			}

			// this is a DEV error
			// the requested service does not require a client object
			// but a call to OZONE\OZ\Core\RequestHandler::getCurrentClient() occurs
			$svc_name = URIHelper::getUriService();

			throw new \Exception("client not defined, maybe 'require_client' is set to false in your oz.services.list for the service '$svc_name'");
		}

		/**
		 * Sets required http headers
		 *
		 * @param \OZONE\OZ\Core\ClientObject|null $client the current client
		 */
		private static function setInitialHeaders(ClientObject $client = null)
		{
			$rule        = SettingsManager::get('oz.clients', 'OZ_CORS_ALLOW_RULE');
			$default_url = SettingsManager::get('oz.config', 'OZ_APP_MAIN_URL');
			$life_time   = 86400;

			if (!empty($client)) {
				$default_url = $client->getClientData()
									  ->getUrl();
				$life_time   = $client->getClientData()
									  ->getSessionLifeTime();
			}

			$origin = self::getRequestOriginOrReferer();

			switch ($rule) {
				case 'any':
					$url = $origin;
					break;
				case 'check':
				default:
					if (ClientObject::checkSafeOriginUrl($origin)) {
						$url = $origin;
					} else {
						$url = $default_url;
					}
			}

			// let's avoid the click-jacking: which is to fool a user from a frame
			header("X-Frame-Options: DENY");

			if (self::isOptions()) {
				$api_key_name   = strtolower(SettingsManager::get('oz.config', 'OZ_APP_API_KEY_NAME'));
				$api_key_header = sprintf('x-%s', $api_key_name);

				// enable self made headers
				header(sprintf('Access-Control-Allow-Headers: accept, %s', $api_key_header));
				// TODO:: retrieve accepted methods from the desired service (add OPTIONS)
				header('Access-Control-Allow-Methods: OPTIONS, GET, POST, PATCH, PUT, DELETE');
				header(sprintf('Access-Control-Max-Age: %s', $life_time));
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
		 * Gets the request origin or referer
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
		 * Gets the current request api key
		 *
		 * @return string
		 */
		public static function getApiKey()
		{
			$key          = '';
			$api_key_name = SettingsManager::get('oz.config', 'OZ_APP_API_KEY_NAME');
			$header_key   = sprintf('HTTP_X_%s', strtoupper(str_replace('-', '_', $api_key_name)));

			// test if already verified
			// or
			// try get from http headers
			if (!empty(self::$req_header_api_key)) {
				$key = self::$req_header_api_key;
			} elseif (isset($_SERVER[$header_key])) {
				$key = $_SERVER[$header_key];
			} elseif (defined('OZ_OZONE_IS_WWW') AND defined('OZ_OZONE_DEFAULT_API_KEY')) {
				$key = OZ_OZONE_DEFAULT_API_KEY;
			}

			if (preg_match(self::$api_key_reg, $key)) {
				self::$req_header_api_key = $key;
			}

			return $key;
		}

		/**
		 * Checks if it is an OPTIONS request method
		 *
		 * @return bool
		 */
		public static function isOptions()
		{
			return $_SERVER['REQUEST_METHOD'] === 'OPTIONS';
		}

		/**
		 * Checks if it is a POST request method
		 *
		 * @return bool
		 */
		public static function isPost()
		{
			return $_SERVER['REQUEST_METHOD'] === 'POST';
		}

		/**
		 * Checks if it is a GET request method
		 *
		 * @return bool
		 */
		public static function isGet()
		{
			return $_SERVER['REQUEST_METHOD'] === 'GET';
		}

		/**
		 * Checks if it is a PATCH request method
		 *
		 * @return bool
		 */
		public static function isPatch()
		{
			return $_SERVER['REQUEST_METHOD'] === 'PATCH';
		}

		/**
		 * Checks if it is a PUT request method
		 *
		 * @return bool
		 */
		public static function isPut()
		{
			return $_SERVER['REQUEST_METHOD'] === 'PUT';
		}

		/**
		 * Checks if it is a DELETE request method
		 *
		 * @return bool
		 */
		public static function isDelete()
		{
			return $_SERVER['REQUEST_METHOD'] === 'DELETE';
		}

		/**
		 * Checks if it is a file request for a file service
		 *
		 * @return bool
		 */
		public static function isForFile()
		{
			$svc_name      = URIHelper::getUriService();
			$file_services = OZone::getFileServices();

			return !empty($svc_name) AND self::isGet() AND array_key_exists($svc_name, $file_services);
		}

		/**
		 * Checks if the requested service allow cross site request without api key
		 *
		 * @return bool
		 */
		public static function isCrossSiteAllowed()
		{
			$svc_name = URIHelper::getUriService();

			if (!empty($svc_name)) {
				$svc = SettingsManager::get('oz.services.list', $svc_name);

				if (!empty($svc) AND isset($svc['cross_site'])) {
					return $svc['cross_site'];
				}
			}

			return false;
		}

		/**
		 * Checks if the requested service require a valid client
		 *
		 * @return bool
		 */
		public static function isClientRequired()
		{
			$svc_name = URIHelper::getUriService();

			if (!empty($svc_name)) {
				$svc = SettingsManager::get('oz.services.list', $svc_name);

				if (!empty($svc) AND isset($svc['require_client'])) {
					return $svc['require_client'];
				}
			}

			return true;
		}

		/**
		 * run procedure when some unhandled ozone exceptions occurs
		 *
		 * @param \OZONE\OZ\Exceptions\BaseException|null $err  the current ozone exception
		 * @param mixed                                        $desc a short description
		 */
		public static function attackProcedure(BaseException $err = null, $desc = null)
		{
			if ($err == null) {
				$err = new ForbiddenException('Something went wrong or we are under attack.');
			}

			oz_logger($_SERVER['REQUEST_URI']);

			oz_logger($err);

			if (!empty($desc)) {
				oz_logger($desc);
			}

			// oz_logger(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS));

			$err->procedure();// exit;
		}

	}
