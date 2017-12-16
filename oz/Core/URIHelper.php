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

		const INTERNAL_WEB_ROUTE_PREFIX = "#^/oz:#";

		private static $parsed_uri_parts = ['oz_uri_service' => '', 'oz_uri_extra' => ''];

		/**
		 * parse request uri
		 *
		 * @return bool        true if valid uri, false if not valid
		 */
		public static function parseRequestUri()
		{
			$install_root  = dirname($_SERVER['PHP_SELF']);
			$uri           = StringUtils::removePrefix($_SERVER['REQUEST_URI'], $install_root);
			$uri           = StringUtils::removeSuffix($uri, '?' . $_SERVER['QUERY_STRING']);
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

				// prevent request to any route like /oz:error, /oz:...
				if (preg_match(self::INTERNAL_WEB_ROUTE_PREFIX, $uri)) {
					return false;
				}

				if (!WebRoute::routeExists($uri)) {
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
		 * @param string $extra_reg  the service extra regexp to be used
		 * @param array  $extra_map  the key map of regexp catch group
		 * @param array  &$extra_out the array in which the result will be stored
		 *
		 * @return bool true if valid extra, false if not valid
		 */
		public static function parseUriExtra($extra_reg, array $extra_map, array &$extra_out)
		{
			$extra = self::getUriExtra();
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
	}