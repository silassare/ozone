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

	use OZONE\OZ\Utils\StringUtils;
	use OZONE\OZ\WebRoute\WebRoute;

	defined('OZ_SELF_SECURITY_CHECK') or die;

	final class URIHelper
	{
		/**
		 * ozone uri regex
		 *
		 * uri have two parts: service name and service extra
		 *        - service name:
		 *            - must have minimum length of 1
		 *            - must contains only alphanumerics and _
		 *            - must start with a-zA-Z
		 *        - service extra
		 *            - minimum length of 0
		 *            - any valid uri characters are allowed
		 *
		 * for uri like: /foo_service/path/to/res/sub_string_like_params
		 *  _______________________________________________________
		 * |   service name   |        service extra               |
		 * |__________________|____________________________________|
		 * |    foo_service   | path/to/res/sub_string_like_params |
		 * |__________________|____________________________________|
		 *
		 * @var string
		 */
		const SERVICE_URL_REG = "#^/?([a-zA-Z][a-zA-Z0-9_-]+)(?:/(.*))?$#";

		private static $parsed_uri_parts = ['oz_uri_service' => '', 'oz_uri_extra' => ''];

		/**
		 * parse request uri
		 *
		 * @return bool        true if valid uri, false if not valid
		 * @throws \OZONE\OZ\Exceptions\InternalErrorException
		 */
		public static function parseRequestUri()
		{
			$install_root  = dirname($_SERVER['PHP_SELF']);
			$uri_full      = StringUtils::removePrefix($_SERVER['REQUEST_URI'], $install_root);
			$uri           = StringUtils::removeSuffix($uri_full, '?' . $_SERVER['QUERY_STRING']);
			$service       = null;
			$service_extra = null;
			$route         = null;
			$in            = [];

			if (!defined('OZ_OZONE_IS_WWW')) {
				if (preg_match(URIHelper::SERVICE_URL_REG, $uri, $in)) {
					$service = $in[1];

					if (isset($in[2])) {
						$service_extra = $in[2];
					}
				}

				if (empty($service)) {
					return false;
				}

				$req_svc = SettingsManager::get('oz.services.list', $service);

				if (empty($req_svc)) {
					return false;
				}
			} else {
				if (0 !== strpos($uri, '/')) {
					$uri = '/' . $uri;
				}

				$route_id = WebRoute::findRoute($uri);

				if (empty($route_id)) {
					return false;
				}

				// prevent request to any route like oz:error, oz:...
				if (WebRoute::isInternalRoute($route_id)) {
					return false;
				}

				$service       = 'oz_web_route';
				$service_extra = $uri;
			}

			self::$parsed_uri_parts['oz_uri_service'] = $service;
			self::$parsed_uri_parts['oz_uri_extra']   = $service_extra;

			return true;
		}

		/**
		 * Gets parsed uri service part
		 *
		 * @return mixed
		 */
		public static function getUriService()
		{
			if (!empty(self::$parsed_uri_parts['oz_uri_service'])) {
				return self::$parsed_uri_parts['oz_uri_service'];
			}

			return null;
		}

		/**
		 * Gets parsed uri extra part
		 *
		 * @return mixed
		 */
		public static function getUriExtra()
		{
			if (!empty(self::$parsed_uri_parts['oz_uri_extra'])) {
				return self::$parsed_uri_parts['oz_uri_extra'];
			}

			return null;
		}

		/**
		 * parse uri extra with a given custom service extra regexp and a given key map
		 *
		 * @param string      $extra_reg  the service extra regexp to be used
		 * @param array       $extra_map  the key map of regexp catch group
		 * @param array       &$extra_out the array in which the result will be stored
		 * @param string|null $extra      the extra string to use, if null the current
		 *                                request uri extra will be used
		 *
		 * @return bool true if valid extra, false if not valid
		 */
		public static function parseUriExtra($extra_reg, array $extra_map, array &$extra_out, $extra = null)
		{
			$extra = is_null($extra) ? self::getUriExtra() : $extra;
			$in    = [];
			$c     = 1; // start from $1

			if (!empty($extra) AND preg_match($extra_reg, $extra, $in)) {
				$stop = false;

				while (!$stop AND isset($in[$c])) {
					if (isset($extra_map[$c - 1])) {
						$key             = $extra_map[$c - 1];
						$extra_out[$key] = $in[$c];
					} else {
						$stop = true;
					}

					$c++;
				}

				return true;
			}

			return false;
		}

		/**
		 * Build an URL based on URL fragments.
		 *
		 * @param string $protocol    the protocol
		 * @param string $domain_name the domain name
		 * @param string $path        the path name
		 * @param array  $query       the query params
		 * @param bool   $merge_query merge current $_GET with the query params or not
		 *
		 * @return string
		 */
		public static function buildURL($protocol = '', $domain_name = '', $path = '/', $query = [], $merge_query = false)
		{
			if (empty($protocol)) {
				$protocol = (empty($_SERVER['HTTPS']) OR $_SERVER['HTTPS'] === 'off') ? 'http' : 'https';
			}

			$protocol    .= '://';
			$domain_name = empty($domain_name) ? $_SERVER['HTTP_HOST'] : $domain_name;

			if ($merge_query) {
				$query = array_merge($_GET, $query);
			}

			if (count($query) AND !empty($query = http_build_query($query))) {
				$query = '?' . $query;
			} else {
				$query = '';
			}

			$domain_name = rtrim($domain_name) . '/';
			$path        = ltrim($path, '/');

			return $protocol . $domain_name . $path . $query;
		}

		/**
		 * Gets the current request url.
		 *
		 * @param bool $append_query append query string or not
		 *
		 * @return string
		 */
		public static function getRequestURL($append_query = false)
		{
			$url = self::buildURL(null, $_SERVER['HTTP_HOST'], $_SERVER['REQUEST_URI']);

			if ($append_query AND !empty($_SERVER['QUERY_STRING'])) {
				$url .= '?' . $_SERVER['QUERY_STRING'];
			}

			return $url;
		}

		/**
		 * Gets the base url.
		 *
		 * @return string
		 */
		public static function getBaseURL()
		{
			return self::buildURL(null, $_SERVER['HTTP_HOST']);
		}
	}