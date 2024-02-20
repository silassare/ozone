<?php

/**
 * Copyright (c) 2017-present, Emile Silas Sare
 *
 * This file is part of OZone package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace OZONE\Core\Router;

use InvalidArgumentException;
use OZONE\Core\App\Context;
use OZONE\Core\Exceptions\ForbiddenException;
use OZONE\Core\Exceptions\InvalidFormException;
use OZONE\Core\Exceptions\RuntimeException;
use OZONE\Core\Http\Response;
use OZONE\Core\Router\Events\RouteBeforeRun;
use OZONE\Core\Router\Events\RouteMethodNotAllowed;
use OZONE\Core\Router\Events\RouteNotFound;
use Throwable;

/**
 * Class Router.
 */
final class Router
{
	private array $allowed_methods = [
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
	 * @var null|\OZONE\Core\Router\RouteGroup
	 */
	private ?RouteGroup $current_group = null;

	/**
	 * @var \OZONE\Core\Router\Route[]
	 */
	private array $static_routes = [];

	/**
	 * @var \OZONE\Core\Router\Route[]
	 */
	private array $dynamic_routes = [];

	/**
	 * @var array<string, string>
	 */
	private array $global_params = [];

	/**
	 * @var array<string, callable>
	 */
	private array $global_params_providers = [];

	/**
	 * Router constructor.
	 */
	public function __construct() {}

	/**
	 * Create a new route group.
	 *
	 * @param string                                               $path
	 * @param callable(Router, \OZONE\Core\Router\RouteGroup):void $factory
	 *
	 * @return RouteGroup
	 */
	public function group(string $path, callable $factory): RouteGroup
	{
		$group = new RouteGroup($path, $this->current_group);

		$this->current_group = $group;

		$factory($this, $group);

		$this->current_group = $group->getParent();

		return $group;
	}

	/**
	 * Gets global parameters.
	 *
	 * @return array<string, string>
	 */
	public function getGlobalParams(): array
	{
		return $this->global_params;
	}

	/**
	 * Add a global parameter provider.
	 *
	 * @param string   $param
	 * @param string   $pattern
	 * @param callable $provider
	 *
	 * @return $this
	 */
	public function addGlobalParam(string $param, string $pattern, callable $provider): self
	{
		$this->global_params[$param]           = $pattern;
		$this->global_params_providers[$param] = $provider;

		return $this;
	}

	/**
	 * Gets a given global parameter value.
	 *
	 * @param Context $context
	 * @param string  $param
	 *
	 * @return null|string
	 */
	public function getGlobalParamValue(Context $context, string $param): ?string
	{
		if (isset($this->global_params_providers[$param])) {
			$factory = $this->global_params_providers[$param];
			$value   = $factory($context);

			if (null === $value) {
				return null;
			}

			if (\is_string($value) || \is_numeric($value)) {
				return (string) $value;
			}

			throw (new RuntimeException(\sprintf(
				'Declared provider for global route parameter "%s" should return "string" or "null" value type not: %s',
				$param,
				\get_debug_type($value)
			)))->suspectCallable($factory);
		}

		return null;
	}

	/**
	 * Gets dynamic routes.
	 *
	 * @return \OZONE\Core\Router\Route[]
	 */
	public function getDynamicRoutes(): array
	{
		return $this->dynamic_routes;
	}

	/**
	 * Gets static routes.
	 *
	 * @return \OZONE\Core\Router\Route[]
	 */
	public function getStaticRoutes(): array
	{
		return $this->static_routes;
	}

	/**
	 * Gets all routes.
	 *
	 * @return \OZONE\Core\Router\Route[]
	 */
	public function getRoutes(): array
	{
		$routes = $this->static_routes;

		foreach ($this->dynamic_routes as $route) {
			$routes[] = $route;
		}

		return $routes;
	}

	/**
	 * Builds route path.
	 *
	 * @param Context $context
	 * @param string  $route_name
	 * @param array   $params
	 *
	 * @return string
	 */
	public function buildRoutePath(Context $context, string $route_name, array $params = []): string
	{
		$route = $this->getRoute($route_name);

		if (!$route) {
			throw new RuntimeException(\sprintf('There is no route named "%s".', $route_name));
		}

		return $route->buildPath($context, $params);
	}

	/**
	 * Gets route with a given name.
	 *
	 * @param string $name
	 *
	 * @return null|\OZONE\Core\Router\Route
	 */
	public function getRoute(string $name): ?Route
	{
		foreach ($this->static_routes as $route) {
			if ($route->getName() === $name) {
				return $route;
			}
		}

		foreach ($this->dynamic_routes as $route) {
			if ($route->getName() === $name) {
				return $route;
			}
		}

		return null;
	}

	/**
	 * Finds the routes that match the given path.
	 *
	 * @param string $method The request method
	 * @param string $path   The request path
	 * @param bool   $all    True to stop searching when a route match
	 *
	 * @return RouteSearchResult
	 */
	public function find(string $method, string $path, bool $all = false): RouteSearchResult
	{
		$method          = \strtoupper($method);
		$found           = null;
		$static          = [];
		$dynamic         = [];
		$static_matches  = [];
		$dynamic_matches = [];

		if (isset($this->allowed_methods[$method])) {
			foreach ($this->static_routes as $route) {
				$params = [];

				if ($route->is($path, $params)) {
					$item             = ['route' => $route, 'params' => $params];
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
				foreach ($this->dynamic_routes as $route) {
					$params = [];

					if ($route->is($path, $params)) {
						$item              = ['route' => $route, 'params' => $params];
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
			$status = RouteSearchStatus::FOUND;
			$found  = $static[0];
		} elseif (isset($dynamic[0])) {
			$status = RouteSearchStatus::FOUND;
			$found  = $dynamic[0];
		} elseif (!empty($static_matches) || !empty($dynamic_matches)) {
			$status = RouteSearchStatus::METHOD_NOT_ALLOWED;
		} else {
			$status = RouteSearchStatus::NOT_FOUND;
		}

		return new RouteSearchResult(
			$status,
			$found,
			$static,
			$dynamic,
			$static_matches,
			$dynamic_matches
		);
	}

	/**
	 * Handle the request in a given context.
	 *
	 * @param Context                       $context
	 * @param null|callable(RouteInfo):void $authenticator
	 *
	 * @throws ForbiddenException
	 * @throws InvalidFormException
	 * @throws Throwable
	 */
	public function handle(Context $context, ?callable $authenticator = null): void
	{
		$request = $context->getRequest();
		$uri     = $request->getUri();
		$result  = $this->find($request->getMethod(), $uri->getPath());

		switch ($result->status()) {
			case RouteSearchStatus::NOT_FOUND:
				(new RouteNotFound($context))->dispatch();

				break;

			case RouteSearchStatus::METHOD_NOT_ALLOWED:
				(new RouteMethodNotAllowed($context))->dispatch();

				break;

			case RouteSearchStatus::FOUND:
				['route' => $route, 'params' => $params] = $result->found();
				$ri                                      = new RouteInfo($context, $route, $params, $authenticator);

				(new RouteBeforeRun($ri))->dispatch();

				$this->runRoute($ri);

				break;
		}
	}

	/**
	 * Registers route.
	 *
	 * @param string|string[] $methods
	 * @param callable|string $path
	 * @param null|callable   $factory
	 *
	 * @return RouteOptions
	 */
	public function map(array|string $methods, callable|string $path, callable $factory = null): RouteOptions
	{
		if (\is_callable($path)) {
			$factory = $path;
			$path    = '';
		}

		if (!\is_array($methods)) {
			$methods = '*' === $methods ? \array_keys($this->allowed_methods) : [$methods];
		}

		if (empty($path) && !$this->current_group) {
			throw new InvalidArgumentException('Route path cannot be empty outside of a group.');
		}

		$methods_filtered = [];

		foreach ($methods as $method) {
			if (!\is_string($method) || !isset($this->allowed_methods[\strtoupper($method)])) {
				$allowed = \implode('|', \array_keys($this->allowed_methods));

				throw new InvalidArgumentException(\sprintf(
					'Invalid method name "%s" for route "%s", allowed methods: %s',
					$method,
					$path,
					$allowed
				));
			}

			$methods_filtered[] = \strtoupper($method);
		}

		if (!\is_callable($factory)) {
			throw new InvalidArgumentException(\sprintf(
				'Got "%s" while expecting callable for: %s',
				\get_debug_type($factory),
				$path
			));
		}

		$route = new Route($this, $methods_filtered, $factory, $options = new RouteOptions($path, $this->current_group));

		if ($route->isDynamic()) {
			$this->dynamic_routes[] = $route;
		} else {
			$this->static_routes[] = $route;
		}

		return $options;
	}

	/**
	 * Register route for CONNECT request method.
	 *
	 * @param callable|string $path
	 * @param null|callable   $factory
	 *
	 * @return RouteOptions
	 */
	public function connect(callable|string $path, callable $factory = null): RouteOptions
	{
		return $this->map('connect', $path, $factory);
	}

	/**
	 * Register route for DELETE request method.
	 *
	 * @param callable|string $path
	 * @param null|callable   $factory
	 *
	 * @return RouteOptions
	 */
	public function delete(callable|string $path, callable $factory = null): RouteOptions
	{
		return $this->map(['delete'], $path, $factory);
	}

	/**
	 * Register route for GET request method.
	 *
	 * @param callable|string $path
	 * @param null|callable   $factory
	 *
	 * @return RouteOptions
	 */
	public function get(callable|string $path, callable $factory = null): RouteOptions
	{
		return $this->map(['get'], $path, $factory);
	}

	/**
	 * Register route for HEAD request method.
	 *
	 * @param callable|string $path
	 * @param null|callable   $factory
	 *
	 * @return RouteOptions
	 */
	public function head(callable|string $path, callable $factory = null): RouteOptions
	{
		return $this->map(['head'], $path, $factory);
	}

	/**
	 * Register route for OPTIONS request method.
	 *
	 * @param callable|string $path
	 * @param null|callable   $factory
	 *
	 * @return RouteOptions
	 */
	public function options(callable|string $path, callable $factory = null): RouteOptions
	{
		return $this->map(['options'], $path, $factory);
	}

	/**
	 * Register route for PATCH request method.
	 *
	 * @param callable|string $path
	 * @param null|callable   $factory
	 *
	 * @return RouteOptions
	 */
	public function patch(callable|string $path, callable $factory = null): RouteOptions
	{
		return $this->map(['patch'], $path, $factory);
	}

	/**
	 * Register route for POST request method.
	 *
	 * @param callable|string $path
	 * @param null|callable   $factory
	 *
	 * @return RouteOptions
	 */
	public function post(callable|string $path, callable $factory = null): RouteOptions
	{
		return $this->map(['post'], $path, $factory);
	}

	/**
	 * Register route for PUT request method.
	 *
	 * @param callable|string $path
	 * @param null|callable   $factory
	 *
	 * @return RouteOptions
	 */
	public function put(callable|string $path, callable $factory = null): RouteOptions
	{
		return $this->map(['put'], $path, $factory);
	}

	/**
	 * Register route for TRACE request method.
	 *
	 * @param callable|string $path
	 * @param null|callable   $factory
	 *
	 * @return RouteOptions
	 */
	public function trace(callable|string $path, callable $factory = null): RouteOptions
	{
		return $this->map(['trace'], $path, $factory);
	}

	/**
	 * Run the route that match the current request path.
	 *
	 * @param RouteInfo $ri
	 *
	 * @throws Throwable
	 */
	private function runRoute(RouteInfo $ri): void
	{
		static $history = [];

		$route = $ri->route();

		$history[] = ['name' => $route->getName(), 'path' => $route->getPath()];

		// In a simple and good app
		// this should not be called to much keep it simple
		// 10 is a limit, it may be 5 or 100.
		if (\count($history) >= 10) {
			throw new RuntimeException('Possible recursive redirection.', $history);
		}

		$debug_data = static function (Route $route, array $data = []) {
			return [
				'route' => $route->getPath(),
			] + $data;
		};

		try {
			\ob_start();
			$return        = \call_user_func($route->getHandler(), $ri);
			$output_buffer = \ob_get_clean();
		} catch (Throwable $t) {
			\ob_clean();

			// throw again exactly the same
			throw $t;
		}

		if (!empty($output_buffer)) {
			throw (new RuntimeException(
				'Writing to output buffer is not allowed.',
				$debug_data($route, ['output_buffer' => $output_buffer])
			))->suspectCallable($route->getHandler());
		}

		if (!$return instanceof Response) {
			throw (new RuntimeException(\sprintf(
				'Invalid return type, got "%s" will expecting "%s".',
				\get_debug_type($return),
				Response::class
			), $debug_data($route)))->suspectCallable($route->getHandler());
		}

		$ri->getContext()
			->setResponse($return);
	}
}
