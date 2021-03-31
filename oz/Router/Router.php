<?php

/**
 * Copyright (c) 2017-present, Emile Silas Sare
 *
 * This file is part of OZone package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace OZONE\OZ\Router;

use OZONE\OZ\Core\Context;
use OZONE\OZ\Exceptions\InternalErrorException;

class Router
{
	const NOT_FOUND = 0;

	const FOUND = 1;

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
	 * @var array
	 */
	private $defaultPlaceholders = [];

	/**
	 * Router constructor.
	 */
	public function __construct()
	{
	}

	/**
	 * Gets default options.
	 *
	 * @return array
	 */
	public function getDefaultOptions()
	{
		$options = [];

		foreach ($this->defaultPlaceholders as $placeholder => $dt) {
			$options[$placeholder] = $dt['regex'];
		}

		return $options;
	}

	/**
	 * Declare a route placeholder provider.
	 *
	 * @param string   $placeholder
	 * @param string   $regex
	 * @param callable $provider
	 *
	 * @return $this
	 */
	public function declarePlaceholder($placeholder, $regex, callable $provider)
	{
		$this->defaultPlaceholders[$placeholder] = [
			'regex'    => $regex,
			'provider' => $provider,
		];

		return $this;
	}

	/**
	 * Gets a given declared placeholder value.
	 *
	 * @param \OZONE\OZ\Core\Context $context
	 * @param string                 $placeholder
	 *
	 * @return null|string
	 */
	public function getDeclaredPlaceholderValue(Context $context, $placeholder)
	{
		if (isset($this->defaultPlaceholders[$placeholder])) {
			$value = \call_user_func($this->defaultPlaceholders[$placeholder]['provider'], $context);

			if (null === $value) {
				return null;
			}

			if (\is_string($value) || \is_numeric($value)) {
				return $value;
			}

			throw new \RuntimeException(\sprintf(
				'Declared provider for route placeholder "%s" should return string or null value not %s.',
				$placeholder,
				\gettype($value)
			));
		}

		return null;
	}

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
	 * @param \OZONE\OZ\Core\Context $context
	 * @param string                 $route_name
	 * @param array                  $args
	 *
	 * @throws \OZONE\OZ\Exceptions\InternalErrorException
	 *
	 * @return string
	 */
	public function buildRoutePath(Context $context, $route_name, array $args = [])
	{
		$route = $this->findWithName($route_name);

		if (!$route) {
			throw new InternalErrorException(\sprintf('There is no route named "%s".', $route_name));
		}

		return $route->toPath($context, $args);
	}

	/**
	 * Finds route with a given name
	 *
	 * @param string $name
	 *
	 * @return null|\OZONE\OZ\Router\Route
	 */
	public function findWithName($name)
	{
		foreach ($this->staticRoutes as $route) {
			if ($route->getOption(Route::OPTION_NAME, null) === $name) {
				return $route;
			}
		}

		foreach ($this->dynamicRoutes as $route) {
			if ($route->getOption(Route::OPTION_NAME, null) === $name) {
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
		$method          = \strtoupper($method);
		$first           = null;
		$static          = [];
		$dynamic         = [];
		$static_matches  = [];
		$dynamic_matches = [];

		if (isset($this->allowedMethods[$method])) {
			foreach ($this->staticRoutes as $route) {
				$args = [];

				if ($route->is($path, $args)) {
					$item             = [$route, $args];
					$static_matches[] = $item;

					if ($route->accept($method)) {
						$static[] = $item;

						if (!$all) {
							break;
						}
					}
				}
			}

			if ($all || empty($static)) {
				foreach ($this->dynamicRoutes as $route) {
					$args = [];

					if ($route->is($path, $args)) {
						$item              = [$route, $args];
						$dynamic_matches[] = $item;

						if ($route->accept($method)) {
							$dynamic[] = $item;

							if (!$all) {
								break;
							}
						}
					}
				}
			}
		}

		if (isset($static[0])) {
			$status = self::FOUND;
			$first  = $static[0];
		} elseif (isset($dynamic[0])) {
			$status = self::FOUND;
			$first  = $dynamic[0];
		} elseif (!empty($static_matches) || !empty($dynamic_matches)) {
			$status = self::METHOD_NOT_ALLOWED;
		} else {
			$status = self::NOT_FOUND;
		}

		return [
			'status'          => $status,
			'first'           => $first, // the first that matches the route and the method
			'static'          => $all ? $static : [], // matches the route and method
			'dynamic'         => $all ? $dynamic : [], // matches the route and the method
			'static_matches'  => $all ? $static_matches : [], // matches the route and/or the method
			'dynamic_matches' => $all ? $dynamic_matches : [], // matches the route and/or the method
		];
	}

	/**
	 * Registers route
	 *
	 * @param string|string[] $methods
	 * @param string          $route_path
	 * @param callable        $callable
	 * @param array           $options
	 *
	 * @return $this
	 */
	public function map($methods, $route_path, callable $callable, array $options = [])
	{
		if (!\is_array($methods)) {
			$methods = $methods === '*' ? \array_keys($this->allowedMethods) : [$methods];
		}

		$methods_filtered = [];

		foreach ($methods as $method) {
			$method = \strtoupper($method);

			if (!\is_string($method) || !isset($this->allowedMethods[$method])) {
				$allowed = \implode('|', \array_keys($this->allowedMethods));

				throw new \InvalidArgumentException(\sprintf(
					'Invalid method name "%s" for route: %s . Allowed methods -> %s',
					$method,
					$route_path,
					$allowed
				));
			}

			$methods_filtered[] = $method;
		}

		if (!\is_string($route_path) || !\strlen($route_path)) {
			throw new \InvalidArgumentException(\sprintf('Empty or invalid route path: %s', $route_path));
		}

		if (!\is_callable($callable)) {
			throw new \InvalidArgumentException(\sprintf(
				'Got "%s" while expecting callable for: %s',
				\gettype($callable),
				$route_path
			));
		}

		$route = new Route($this, $methods_filtered, $route_path, $callable, $options);

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
