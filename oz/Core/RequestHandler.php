<?php
	/**
	 * Copyright (c) Emile Silas Sare <emile.silas@gmail.com>
	 *
	 * This file is part of OZone (O'Zone) package.
	 *
	 * For the full copyright and license information, please view the LICENSE
	 * file that was distributed with this source code.
	 */

	namespace OZONE\OZ\Core;

	use OZONE\OZ\Db\OZClient;
	use OZONE\OZ\Exceptions\BaseException;
	use OZONE\OZ\Exceptions\ForbiddenException;
	use OZONE\OZ\Exceptions\NotFoundException;
	use OZONE\OZ\Exceptions\RuntimeException;
	use OZONE\OZ\OZone;

	defined('OZ_SELF_SECURITY_CHECK') or die;

	final class RequestHandler
	{
		/**
		 * @var string
		 */
		private static $req_header_api_key = '';

		/**
		 * the current client object
		 *
		 * @var \OZONE\OZ\Db\OZClient
		 */
		private static $client;

		/**
		 * init incoming request check
		 *
		 * @throws \OZONE\OZ\Exceptions\InternalErrorException
		 * @throws \Exception
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
				$main_url = SettingsManager::get('oz.config', 'OZ_API_MAIN_URL');

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

			// we start a session only if the service require a session
			if (self::isSessionRequired()) {
				if (self::isForFile()) {
					self::startSessionForFile();
				} else {
					self::startSessionDefault();
				}
			}
		}

		/**
		 * init client
		 *
		 * @throws \Exception
		 */
		private static function startSessionDefault()
		{
			$client  = null;
			$api_key = self::getRequestApiKey();

			if (empty($api_key)) {
				self::attackProcedure(new ForbiddenException('OZ_API_KEY_MISSING_IN_HEADERS'));
			}

			$client = ClientManager::getClientWithApiKey($api_key);

			if (!$client OR !$client->getValid()) {
				self::attackProcedure(new ForbiddenException('OZ_YOUR_API_KEY_IS_NOT_VALID', ['api_key' => $api_key]));
			}

			define('OZ_SESSION_MAX_LIFE_TIME', $client->getSessionLifeTime());

			self::$client = $client;
			self::setInitialHeaders($client);

			SessionsHandler::start();
		}

		/**
		 * init client for file request
		 *
		 * @throws \OZONE\OZ\Exceptions\InternalErrorException
		 * @throws \Exception
		 */
		private static function startSessionForFile()
		{
			// please BE AWARE!!!

			$client   = null;
			$sid      = null;
			$sid_name = SettingsManager::get('oz.config', 'OZ_API_SESSION_ID_NAME');

			if (isset($_COOKIE[$sid_name])) {
				$sid = $_COOKIE[$sid_name];
			}

			if (empty($sid)) {
				self::attackProcedure(new ForbiddenException('OZ_SESSION_INVALID'));
			}

			$client = ClientManager::getClientWithSessionId($sid);

			if (is_null($client) OR !$client->getValid()) {
				self::attackProcedure(new ForbiddenException('OZ_SESSION_INVALID'));
			}

			define('OZ_SESSION_MAX_LIFE_TIME', $client->getSessionLifeTime());

			self::$client = $client;
			self::setInitialHeaders($client);

			SessionsHandler::start();
		}

		/**
		 * Gets current client object
		 *
		 * if the 'require_session' property is set to false in the requested service's settings
		 * you should not call this otherwise an exception is raised
		 *
		 * @param bool $required
		 *
		 * @return null|\OZONE\OZ\Db\OZClient
		 * @throws \OZONE\OZ\Exceptions\RuntimeException
		 */
		public static function getCurrentClient($required = true)
		{
			if (self::$client instanceof OZClient) {
				return self::$client;
			}

			if ($required) {
				$svc_name = URIHelper::getUriService();
				throw new RuntimeException("client not defined, maybe 'require_session' is set to false in your oz.services.list for the service '$svc_name'");
			}

			return null;
		}

		/**
		 * Sets required http headers
		 *
		 * @param null|\OZONE\OZ\Db\OZClient $client the current client
		 *
		 * @throws \OZONE\OZ\Exceptions\InternalErrorException
		 * @throws \OZONE\OZ\Exceptions\RuntimeException
		 */
		private static function setInitialHeaders(OZClient $client = null)
		{
			$rule        = SettingsManager::get('oz.clients', 'OZ_CORS_ALLOW_RULE');
			$default_url = SettingsManager::get('oz.config', 'OZ_API_MAIN_URL');
			$life_time   = 86400;

			if (!empty($client)) {
				$default_url = $client->getUrl();
				$life_time   = $client->getSessionLifeTime();
			}

			$origin = self::getRequestOriginOrReferer();

			switch ($rule) {
				case 'any':
					$url = $origin;
					break;
				case 'check':
				default:
					if (ClientManager::checkSafeOriginUrl($origin)) {
						$url = $origin;
					} else {
						$url = $default_url;
					}
			}

			// let's avoid the click-jacking: which is to fool a user from a frame
			header("X-Frame-Options: DENY");

			if (self::isOptions()) {
				$allow_real_method_header = SettingsManager::get('oz.config', 'OZ_API_ALLOW_REAL_METHOD_HEADER');
				$custom_headers[]         = sprintf('x-%s', strtolower(SettingsManager::get('oz.config', 'OZ_API_KEY_HEADER_NAME')));

				if ($allow_real_method_header) {
					$custom_headers[] = sprintf('x-%s', strtolower(SettingsManager::get('oz.config', 'OZ_API_REAL_METHOD_HEADER_NAME')));
				}

				// enable self made headers
				header(sprintf('Access-Control-Allow-Headers: accept, %s', implode(', ', $custom_headers)));
				// TODO:: retrieve accepted methods from the desired service (add OPTIONS)
				header('Access-Control-Allow-Methods: OPTIONS, GET, POST, PATCH, PUT, DELETE');
				header(sprintf('Access-Control-Max-Age: %s', $life_time));
			}

			if (!empty($url)) {
				// Remember: CORS is not security. Do not rely on CORS to secure your web site/application.
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
		 * @throws \OZONE\OZ\Exceptions\InternalErrorException
		 * @throws \OZONE\OZ\Exceptions\RuntimeException
		 */
		public static function getRequestApiKey()
		{
			$key          = '';
			$api_key_name = SettingsManager::get('oz.config', 'OZ_API_KEY_HEADER_NAME');
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

			if (!empty($key) AND self::isApiKeyLike($key)) {
				self::$req_header_api_key = $key;
			}

			return $key;
		}

		/**
		 * Check if a given string is like an api key
		 *
		 * - old version api key:
		 *    -ozone api key is a 4 groups of 8 alphanumerics (A-Z0-9) separated with -
		 *    -length: 35 chars (32 [A-Z0-9] + 3 [-])
		 *
		 * @param string $str
		 *
		 * @return bool
		 */
		public static function isApiKeyLike($str)
		{
			// TODO change to uuid
			$api_key_reg = '#^[A-Z0-9]{8}-[A-Z0-9]{8}-[A-Z0-9]{8}-[A-Z0-9]{8}$#';

			return preg_match($api_key_reg, $str);
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
		 * Checks if the request method is correct.
		 *
		 * For server that does not support HEAD, PATCH, PUT, DELETE...
		 * request methods you need to make sure that
		 * - "OZ_API_ALLOW_REAL_METHOD_HEADER" is set to "true" in "oz.config" file
		 * - the real method header (default x-ozone-real-method) is set to the real http method you want
		 * - you use POST to send your request
		 *
		 * @param string $method the http request method
		 *
		 * @return bool
		 * @throws \OZONE\OZ\Exceptions\InternalErrorException
		 * @throws \OZONE\OZ\Exceptions\RuntimeException
		 */
		private static function isMethodOrRealMethod($method)
		{
			if ($_SERVER['REQUEST_METHOD'] === $method) {
				return true;
			}

			$allowed = SettingsManager::get('oz.config', 'OZ_API_ALLOW_REAL_METHOD_HEADER');

			if ($allowed) {
				$real_method_header_name = SettingsManager::get('oz.config', 'OZ_API_REAL_METHOD_HEADER_NAME');
				$header_key              = sprintf('HTTP_X_%s', strtoupper(str_replace('-', '_', $real_method_header_name)));

				if (self::isPost() AND isset($_SERVER[$header_key])) {
					return $_SERVER[$header_key] === $method;
				}
			}

			return false;
		}

		/**
		 * Checks if it is a PATCH request method
		 *
		 * @return bool
		 * @throws \OZONE\OZ\Exceptions\InternalErrorException
		 * @throws \OZONE\OZ\Exceptions\RuntimeException
		 */
		public static function isPatch()
		{
			return self::isMethodOrRealMethod('PATCH');
		}

		/**
		 * Checks if it is a PUT request method
		 *
		 * @return bool
		 * @throws \OZONE\OZ\Exceptions\InternalErrorException
		 * @throws \OZONE\OZ\Exceptions\RuntimeException
		 */
		public static function isPut()
		{
			return self::isMethodOrRealMethod('PUT');
		}

		/**
		 * Checks if it is a DELETE request method
		 *
		 * @return bool
		 * @throws \OZONE\OZ\Exceptions\InternalErrorException
		 * @throws \OZONE\OZ\Exceptions\RuntimeException
		 */
		public static function isDelete()
		{
			return self::isMethodOrRealMethod('DELETE');
		}

		/**
		 * Checks if it is a file request for a file service
		 *
		 * @return bool
		 * @throws \OZONE\OZ\Exceptions\InternalErrorException
		 * @throws \OZONE\OZ\Exceptions\RuntimeException
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
		 * @throws \OZONE\OZ\Exceptions\InternalErrorException
		 * @throws \OZONE\OZ\Exceptions\RuntimeException
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
		 * Checks if the requested service require a session
		 *
		 * @return bool
		 * @throws \OZONE\OZ\Exceptions\InternalErrorException
		 * @throws \OZONE\OZ\Exceptions\RuntimeException
		 */
		public static function isSessionRequired()
		{
			$svc_name = URIHelper::getUriService();

			if (!empty($svc_name)) {
				$svc = SettingsManager::get('oz.services.list', $svc_name);

				if (!empty($svc) AND isset($svc['require_session'])) {
					return $svc['require_session'];
				}
			}

			return true;
		}

		/**
		 * Runs procedure when illegal access is detected
		 *
		 * @param \OZONE\OZ\Exceptions\BaseException|null $err  the current ozone exception
		 * @param mixed                                   $desc a short description
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
