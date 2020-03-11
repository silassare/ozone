<?php
	/**
	 * Copyright (c) 2017-present, Emile Silas Sare
	 *
	 * This file is part of OZone (O'Zone) package.
	 *
	 * For the full copyright and license information, please view the LICENSE
	 * file that was distributed with this source code.
	 */

	namespace OZONE\OZ\Router;

	use InvalidArgumentException;
	use OZONE\OZ\Exceptions\InternalErrorException;

	defined('OZ_SELF_SECURITY_CHECK') or die;

	class Router
	{
		const NOT_FOUND          = 0;
		const FOUND              = 1;
		const METHOD_NOT_ALLOWED = 2;

		private $allowedMethods = [
			'CONNECT' => 1,
			'DELETE'  => 1,
			'GET'     => 1,
			'HEAD'    => 1,
			'OPTIONS' => 1,
			'PATCH'   => 1,
			'POST'    => 1,
			'PUT'     => 1,
			'TRACE'   => 1,
		];

		/**
		 * @var \OZONE\OZ\Router\Route[]
		 */
		private $staticRoutes = [];
		/**
		 * @var \OZONE\OZ\Router\Route[]
		 */
		private $dynamicRoutes = [];

		/**
		 * Router constructor.
		 */
		public function __construct() { }

		/**
		 * Gets dynamic routes.
		 *
		 * @return \OZONE\OZ\Router\Route[]
		 */
		public function getDynamicRoutes()
		{
			return $this->dynamicRoutes;
		}

		/**
		 * Gets static routes.
		 *
		 * @return \OZONE\OZ\Router\Route[]
		 */
		public function getStaticRoutes()
		{
			return $this->staticRoutes;
		}

		/**
		 * Gets all routes.
		 *
		 * @return \OZONE\OZ\Router\Route[]
		 */
		public function getRoutes()
		{
			return $this->staticRoutes + $this->dynamicRoutes;
		}

		/**
		 * Builds route path.
		 *
		 * @param string $route_name
		 * @param array  $args
		 *
		 * @return string
		 * @throws \OZONE\OZ\Exceptions\InternalErrorException
		 */
		public function buildRoutePath($route_name, array $args = [])
		{
			$route = $this->findWithName($route_name);

			if (!$route) {
				throw new InternalErrorException(sprintf('There is no route named "%s".', $route_name));
			}

			return $route->toPath($args);
		}

		/**
		 * Finds route with a given name
		 *
		 * @param string $name
		 *
		 * @return \OZONE\OZ\Router\Route|null
		 */
		public function findWithName($name)
		{
			foreach ($this->staticRoutes as $route) {
				if ($route->getOption('route:name', null) === $name) {
					return $route;
				}
			}

			foreach ($this->dynamicRoutes as $route) {
				if ($route->getOption('route:name', null) === $name) {
					return $route;
				}
			}

			return null;
		}

		/**
		 * Finds the routes that match the given path
		 *
		 * @param string $method The request method
		 * @param string $path   The request path
		 * @param bool   $all    True to stop searching when a route match
		 *
		 * @return array
		 */
		public function find($method, $path, $all = false)
		{
			$method  = strtoupper($method);
			$found   = null;
			$static  = [];
			$dynamic = [];

			if (isset($this->allowedMethods[$method])) {
				foreach ($this->staticRoutes as $route) {
					$args = [];
					if ($route->is($path, $args)) {
						$static[] = $route;

						if ($route->accept($method)) {
							$found = [$route, $args];
							if (!$all) {
								break;
							}
						}
					}
				}

				if ($all OR !isset($found)) {
					foreach ($this->dynamicRoutes as $route) {
						$args = [];
						if ($route->is($path, $args)) {
							$dynamic[] = $route;

							if ($route->accept($method)) {
								$found = [$route, $args];
								if (!$all) {
									break;
								}
							}
						}
					}
				}
			}

			if (isset($found)) {
				$status = self::FOUND;
			} elseif (!empty($static) OR !empty($dynamic)) {
				$status = self::METHOD_NOT_ALLOWED;
			} else {
				$status = self::NOT_FOUND;
			}

			return [
				'status'  => $status,
				'found'   => $found,
				'static'  => $static,
				'dynamic' => $dynamic
			];
		}

		/**
		 * Registers route
		 *
		 * @param string[]|string $methods
		 * @param string          $route_path
		 * @param callable        $callable
		 * @param array           $options
		 *
		 * @return $this
		 */
		public function map($methods, $route_path, callable $callable, array $options = [])
		{
			if (!is_array($methods)) {
				$methods = $methods === '*' ? array_keys($this->allowedMethods) : [$methods];
			}

			$methods_filtered = [];

			foreach ($methods as $method) {
				$method = strtoupper($method);
				if (!is_string($method) OR !isset($this->allowedMethods[$method])) {
					$allowed = implode('|', array_keys($this->allowedMethods));
					throw new InvalidArgumentException(sprintf('Invalid method name "%s" for route: %s . Allowed methods -> %s', $method, $route_path, $allowed));
				}

				$methods_filtered[] = $method;
			}

			if (!is_string($route_path) OR !strlen($route_path)) {
				throw new InvalidArgumentException(sprintf('Empty or invalid route path: %s', $route_path));
			}

			if (!is_callable($callable)) {
				throw new InvalidArgumentException(sprintf('Got "%s" while expecting callable for: %s', gettype($callable), $route_path));
			}

			$route = new Route($methods_filtered, $route_path, $callable, $options);

			if ($route->isDynamic()) {
				$this->dynamicRoutes[] = $route;
			} else {
				$this->staticRoutes[] = $route;
			}

			return $this;
		}

		/**
		 * Register route for CONNECT request method
		 *
		 * @param string   $route_path
		 * @param callable $callable
		 * @param array    $options
		 *
		 * @return $this
		 */
		public function connect($route_path, callable $callable, array $options = [])
		{
			return $this->map('connect', $route_path, $callable, $options);
		}

		/**
		 * Register route for DELETE request method
		 *
		 * @param string   $route_path
		 * @param callable $callable
		 * @param array    $options
		 *
		 * @return $this
		 */
		public function delete($route_path, callable $callable, array $options = [])
		{
			return $this->map('delete', $route_path, $callable, $options);
		}

		/**
		 * Register route for GET request method
		 *
		 * @param string   $route_path
		 * @param callable $callable
		 * @param array    $options
		 *
		 * @return $this
		 */
		public function get($route_path, callable $callable, array $options = [])
		{
			return $this->map('get', $route_path, $callable, $options);
		}

		/**
		 * Register route for HEAD request method
		 *
		 * @param string   $route_path
		 * @param callable $callable
		 * @param array    $options
		 *
		 * @return $this
		 */
		public function head($route_path, callable $callable, array $options = [])
		{
			return $this->map('head', $route_path, $callable, $options);
		}

		/**
		 * Register route for OPTIONS request method
		 *
		 * @param string   $route_path
		 * @param callable $callable
		 * @param array    $options
		 *
		 * @return $this
		 */
		public function options($route_path, callable $callable, array $options = [])
		{
			return $this->map('options', $route_path, $callable, $options);
		}

		/**
		 * Register route for PATCH request method
		 *
		 * @param string   $route_path
		 * @param callable $callable
		 * @param array    $options
		 *
		 * @return $this
		 */
		public function patch($route_path, callable $callable, array $options = [])
		{
			return $this->map('patch', $route_path, $callable, $options);
		}

		/**
		 * Register route for POST request method
		 *
		 * @param string   $route_path
		 * @param callable $callable
		 * @param array    $options
		 *
		 * @return $this
		 */
		public function post($route_path, callable $callable, array $options = [])
		{
			return $this->map('post', $route_path, $callable, $options);
		}

		/**
		 * Register route for PUT request method
		 *
		 * @param string   $route_path
		 * @param callable $callable
		 * @param array    $options
		 *
		 * @return $this
		 */
		public function put($route_path, callable $callable, array $options = [])
		{
			return $this->map('put', $route_path, $callable, $options);
		}

		/**
		 * Register route for TRACE request method
		 *
		 * @param string   $route_path
		 * @param callable $callable
		 * @param array    $options
		 *
		 * @return $this
		 */
		public function trace($route_path, callable $callable, array $options = [])
		{
			return $this->map('trace', $route_path, $callable, $options);
		}
	}
