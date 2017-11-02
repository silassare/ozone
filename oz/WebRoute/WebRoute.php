<?php

	namespace OZONE\OZ\WebRoute;

	use OZONE\OZ\Exceptions\BaseException;
	use OZONE\OZ\Exceptions\InternalErrorException;
	use OZONE\OZ\Core\SettingsManager;
	use OZONE\OZ\Core\Assert;
	use OZONE\OZ\WebRoute\Services\RouteRunner;

	final class WebRoute
	{

		static $redirect_history = [];

		/**
		 * find the route that match a given path
		 *
		 * @param string $path a path
		 *
		 * @return array|null  the route or null if none found
		 */
		public static function findRoute($path)
		{
			$routes = SettingsManager::get('oz.routes');
			$found  = null;

			// search for exact match
			foreach ($routes as $route) {
				if (isset($route['path'])) {
					if (is_array($route['path']) AND in_array($path, $route['path'])) {
						$found = $route;
						break;
					} elseif ($path === $route['path']) {
						$found = $route;
						break;
					}
				}
			}

			// search against regexp
			if ($found == null) {
				foreach ($routes as $route) {
					if (isset($route['~path'])) {
						$regexp = $route['~path'];
						if (preg_match($regexp, $path)) {
							$found = $route;
							break;
						}
					}
				}
			}

			return $found;
		}

		/**
		 * Checks if a route exists for a given path
		 *
		 * @param string $path a path
		 *
		 * @return Boolean
		 */
		public static function routeExists($path)
		{
			$route = self::findRoute($path);

			return !empty($route);
		}

		/**
		 * silent route redirection without the user being informed
		 *
		 * @param string $path    a specific path
		 * @param array  $request the request array to use
		 */
		public static function silentRedirectRoute($path, $request = [])
		{
			$debug_data = ['oz_redirect_path' => $path, 'oz_redirect_history' => self::$redirect_history];

			Assert::assertAuthorizeAction(self::routeExists($path), new InternalErrorException('OZ_REDIRECT_UNDEFINED_ROUTE', $debug_data));

			$request['oz_route_path'] = $path;

			self::$redirect_history[$path] = $request;

			$rr = new RouteRunner();

			$rr->execute($request);
			exit;
		}

		/**
		 * show exception in a custom error page.
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

			self::silentRedirectRoute('/oz:error', $desc);
		}
	}