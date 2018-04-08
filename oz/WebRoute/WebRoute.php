<?php
	/**
	 * Copyright (c) Emile Silas Sare <emile.silas@gmail.com>
	 *
	 * This file is part of the OZone package.
	 *
	 * For the full copyright and license information, please view the LICENSE
	 * file that was distributed with this source code.
	 */

	namespace OZONE\OZ\WebRoute;

	use OZONE\OZ\Core\URIHelper;
	use OZONE\OZ\Exceptions\BaseException;
	use OZONE\OZ\Exceptions\InternalErrorException;
	use OZONE\OZ\Core\SettingsManager;
	use OZONE\OZ\Core\Assert;
	use OZONE\OZ\Exceptions\NotFoundException;
	use OZONE\OZ\Exceptions\RuntimeException;
	use OZONE\OZ\OZone;

	defined('OZ_SELF_SECURITY_CHECK') or die;

	final class WebRoute
	{
		private static $redirect_history = [];
		private static $default_route    = [
			'path'    => null,
			'handler' => null
		];

		/**
		 * Find the route that match a given path.
		 *
		 * @param string $path a path
		 *
		 * @return string|null  the route id or null if none found
		 */
		public static function findRoute($path)
		{
			$routes = SettingsManager::get('oz.routes');

			// search for exact match
			foreach ($routes as $route_id => $route) {
				if (isset($route['path'])) {
					if (is_array($route['path']) AND in_array($path, $route['path'])) {
						return $route_id;
					} elseif ($path === $route['path']) {
						return $route_id;
					}
				}
			}

			// search against regexp
			foreach ($routes as $route_id => $route) {
				if (isset($route['~path'])) {
					$regexp = $route['~path'];
					if (preg_match($regexp, $path)) {
						return $route_id;
					}
				}
			}

			return null;
		}

		/**
		 * Gets a route with a given route id.
		 *
		 * @param string $route_id the route id
		 *
		 * @return array|null
		 */
		public static function getRouteById($route_id)
		{
			$route = SettingsManager::get('oz.routes', $route_id);

			if (is_array($route)) {
				$route = array_merge(self::$default_route, $route);
			}

			return $route;
		}

		/**
		 * Checks if a route exists for a given path.
		 *
		 * @param string $path a path
		 *
		 * @return bool
		 */
		public static function routePathExists($path)
		{
			$r = self::findRoute($path);

			return !empty($r);
		}

		/**
		 * Checks if a route id is for internal use.
		 *
		 * @param string $route_id the route id
		 *
		 * @return bool
		 */
		public static function isInternalRoute($route_id)
		{
			$route_id = is_string($route_id) ? strtolower($route_id) : false;

			return $route_id AND substr($route_id, 0, 3) === "oz:";
		}

		/**
		 * Silent route redirection without the user being informed
		 *
		 * @param string $route_id the route id
		 * @param array  $request  the request array to use
		 *
		 * @throws \OZONE\OZ\Exceptions\RuntimeException
		 */
		public static function silentRedirectRoute($route_id, $request = [])
		{
			$debug_data = ['oz_redirect_route' => $route_id, 'oz_redirect_history' => self::$redirect_history];

			$route = self::getRouteById($route_id);

			Assert::assertAuthorizeAction(!empty($route), new InternalErrorException('OZ_REDIRECT_ROUTE_IS_NOT_DEFINED', $debug_data));

			if (/* $route_id !== 'oz:error' AND */
			isset(self::$redirect_history[$route_id])) {
				throw new RuntimeException("OZ_RECURSIVE_REDIRECTION", $debug_data);
			}

			self::$redirect_history[$route_id] = $request;

			self::runRouteById($route_id, $request);

			exit;
		}

		/**
		 * Redirect user to a given route.
		 *
		 * @param string $route_id the route id
		 * @param array  $query    the query parameters
		 *
		 * @throws \OZONE\OZ\Exceptions\RuntimeException
		 */
		public static function redirectRoute($route_id, $query = [])
		{
			$debug_data = ['oz_redirect_id' => $route_id, 'oz_redirect_history' => self::$redirect_history];

			if (self::isInternalRoute($route_id)) {
				throw new RuntimeException("OZ_INTERNAL_ROUTE_ACCESS_NOT_ALLOWED", $debug_data);
			}

			$route = self::getRouteById($route_id);

			Assert::assertAuthorizeAction(!empty($route), new InternalErrorException('OZ_REDIRECT_ROUTE_ID_IS_NOT_DEFINED', $debug_data));

			if (empty($route["path"])) {
				throw new RuntimeException("OZ_REDIRECTION_STATIC_PATH_REQUIRED", $debug_data);
			}

			$path = is_array($route["path"]) ? $route["path"][0] : $route["path"];

			$url = URIHelper::buildURL(null, null, $path, $query);

			self::redirect($url);

			exit;
		}

		/**
		 * Redirect user to a given url.
		 *
		 * @param string $url the url to redirect user to
		 *
		 * @throws \OZONE\OZ\Exceptions\RuntimeException
		 */
		public static function redirect($url)
		{
			// TODO: a simple view as redirect page
			// to ask user to click on link if browser is not redirected
			if (filter_var($url, FILTER_VALIDATE_URL)) {
				header("Location: $url");
				exit;
			} else {
				throw new RuntimeException("OZ_REDIRECTION_URL_IS_INVALID");
			}
		}

		/**
		 * Show exception in a custom error page.
		 *
		 * @param $e \OZONE\OZ\Exceptions\BaseException
		 */
		public static function showCustomErrorPage(BaseException $e)
		{
			// TODO: find last url or go home
			$back_url             = SettingsManager::get('oz.config', 'OZ_APP_MAIN_URL');
			$http_response_header = $e->getHeaderString();
			$err_title            = str_replace('HTTP/1.1 ', '', $http_response_header);
			$err_code             = $e->getCode();
			$err_desc             = $e->getMessage();
			$err_data             = $e->getData();

			$desc = [
				'oz_error_code'     => $err_code,
				'oz_error_title'    => $err_title,
				'oz_error_desc'     => $err_desc,
				'oz_error_data'     => $err_data,
				'oz_error_url'      => $_SERVER['REQUEST_URI'],
				'oz_error_back_url' => $back_url
			];

			header($http_response_header);

			self::silentRedirectRoute('oz:error', $desc);
		}

		/**
		 * Run the route that match a given route path.
		 *
		 * @param string $route_id
		 * @param array  $request
		 */
		private static function runRouteById($route_id, array $request = [])
		{
			$route = self::getRouteById($route_id);

			// it may be a direct call so we check again
			Assert::assertAuthorizeAction(!empty($route), new RuntimeException("OZ_ROUTE_ID_IS_NOT_DEFINED", ['oz_route_id' => $route_id]));

			$route_class_name = $route['handler'];

			/** @var \OZONE\OZ\Core\BaseView $r */
			$r = OZone::obj($route_class_name, $request);

			$r->serve();
			exit;
		}

		/**
		 * Run the route that match a given route path.
		 *
		 * @param string $route_path
		 * @param array  $request
		 */
		public static function runRoutePath($route_path, array $request = [])
		{
			$route_id = self::findRoute($route_path);

			// it may be a direct call so we check again
			Assert::assertAuthorizeAction(!empty($route_id), new NotFoundException());

			self::runRouteById($route_id, $request);
			exit;
		}
	}