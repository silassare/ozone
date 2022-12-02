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

namespace OZONE\OZ\Router;

use InvalidArgumentException;
use OZONE\OZ\Core\Context;
use OZONE\OZ\Exceptions\RuntimeException;
use OZONE\OZ\Forms\FormData;
use OZONE\OZ\Http\Response;
use OZONE\OZ\Router\Events\RouteBeforeRun;
use OZONE\OZ\Router\Events\RouteMethodNotAllowed;
use OZONE\OZ\Router\Events\RouteNotFound;
use PHPUtils\Events\Event;
use Throwable;

/**
 * Class Router.
 */
final class Router
{
	public const NOT_FOUND = 0;

	public const FOUND = 1;

	public const METHOD_NOT_ALLOWED = 2;

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
	 * @var \OZONE\OZ\Router\RouteGroup[]
	 */
	private array $groups = [];

	/**
	 * @var \OZONE\OZ\Router\Route[]
	 */
	private array $static_routes = [];

	/**
	 * @var \OZONE\OZ\Router\Route[]
	 */
	private array $dynamic_routes = [];

	private array $global_params = [];

	/**
	 * @var callable[]
	 */
	private array $global_params_providers = [];

	/**
	 * Router constructor.
	 */
	public function __construct()
	{
	}

	/**
	 * Create a new route group.
	 *
	 * @param string   $path
	 * @param callable<Router> $factory
	 *
	 * @return \OZONE\OZ\Router\RouteGroup
	 */
	public function group(string $path, callable $factory): RouteGroup
	{
		$group =  new RouteGroup($path, $this);

		$this->groups[] = $group;

		$factory($this);

		array_pop($this->groups);

		return $group;
	}

	/**
	 * Gets global parameters.
	 *
	 * @return array
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
	 * @param \OZONE\OZ\Core\Context $context
	 * @param string                 $param
	 *
	 * @return null|string
	 */
	public function getGlobalParamValue(Context $context, string $param): ?string
	{
		if (isset($this->global_params_providers[$param])) {
			$value = \call_user_func($this->global_params_providers[$param], $context);

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
			)))->suspectCallable($this->global_params[$param]);
		}

		return null;
	}

	/**
	 * Gets dynamic routes.
	 *
	 * @return \OZONE\OZ\Router\Route[]
	 */
	public function getDynamicRoutes(): array
	{
		return $this->dynamic_routes;
	}

	/**
	 * Gets static routes.
	 *
	 * @return \OZONE\OZ\Router\Route[]
	 */
	public function getStaticRoutes(): array
	{
		return $this->static_routes;
	}

	/**
	 * Gets all routes.
	 *
	 * @return \OZONE\OZ\Router\Route[]
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
	 * @param \OZONE\OZ\Core\Context $context
	 * @param string                 $route_name
	 * @param array                  $params
	 *
	 * @return string
	 */
	public function buildRoutePath(Context $context, string $route_name, array $params = []): string
	{
		$route = $this->getRoute($route_name);

		if (!$route) {
			throw new RuntimeException(\sprintf('There is no route named "%s".', $route_name));
		}

		return $route->toPath($context, $params);
	}

	/**
	 * Gets route with a given name.
	 *
	 * @param string $name
	 *
	 * @return null|\OZONE\OZ\Router\Route
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
	 * @return array
	 */
	public function find(string $method, string $path, bool $all = false): array
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
					$item             = [$route, $params];
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
						$item              = [$route, $params];
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
			$found  = $static[0];
		} elseif (isset($dynamic[0])) {
			$status = self::FOUND;
			$found  = $dynamic[0];
		} elseif (!empty($static_matches) || !empty($dynamic_matches)) {
			$status = self::METHOD_NOT_ALLOWED;
		} else {
			$status = self::NOT_FOUND;
		}

		return [
			'status'          => $status,
			'found'           => $found, // the first route that matches the route and the method
			'static'          => $all ? $static : [], // matches the route and method
			'dynamic'         => $all ? $dynamic : [], // matches the route and the method
			'static_matches'  => $all ? $static_matches : [], // matches the route and/or the method
			'dynamic_matches' => $all ? $dynamic_matches : [], // matches the route and/or the method
		];
	}

	/**
	 * Handle the request in a given context.
	 *
	 * @throws Throwable
	 */
	public function handle(Context $context): void
	{
		$request = $context->getRequest();
		$uri     = $request->getUri();
		$results = $this->find($request->getMethod(), $uri->getPath(), true);

		switch ($results['status']) {
			case self::NOT_FOUND:
				Event::trigger(new RouteNotFound($context));

				break;

			case self::METHOD_NOT_ALLOWED:
				Event::trigger(new RouteMethodNotAllowed($context));

				break;

			case self::FOUND:
				/** @var \OZONE\OZ\Router\Route $route */
				/** @var array $params */
				[$route, $params] = $results['found'];

				$ri = new RouteInfo($context, $route, $params);

				$options = $route->getOptions();

				$options->runGuards($ri);

				$clean_fd = $options->getForm($ri)?->validate($context->getRequest()
					->getFormData());

				if($clean_fd){
					$ri->getCleanFormData()->merge($clean_fd);
				}

				Event::trigger(new RouteBeforeRun($ri));

				$this->runRoute($ri);

				break;
		}
	}

	/**
	 * Registers route.
	 *
	 * @param string|string[] $methods
	 * @param string          $route_path
	 * @param callable        $callable
	 *
	 * @return \OZONE\OZ\Router\RouteOptions
	 */
	public function map(string|array $methods, string $route_path, callable $callable): RouteOptions
	{
		if (!\is_array($methods)) {
			$methods = '*' === $methods ? \array_keys($this->allowed_methods) : [$methods];
		}

		if (empty($route_path)) {
			throw new InvalidArgumentException(\sprintf('Invalid route path: %s', $route_path));
		}

		$methods_filtered = [];

		foreach ($methods as $method) {

			if (!\is_string($method) || !isset($this->allowed_methods[\strtoupper($method)])) {
				$allowed = \implode('|', \array_keys($this->allowed_methods));

				throw new InvalidArgumentException(\sprintf(
					'Invalid method name "%s" for route "%s", allowed methods: %s',
					$method,
					$route_path,
					$allowed
				));
			}

			$methods_filtered[] = \strtoupper($method);
		}


		if (!\is_callable($callable)) {
			throw new InvalidArgumentException(\sprintf(
				'Got "%s" while expecting callable for: %s',
				\get_debug_type($callable),
				$route_path
			));
		}

		$route = new Route($this, $methods_filtered, $route_path, $callable, $options = new RouteOptions());

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
	 * @param string   $route_path
	 * @param callable $callable
	 *
	 * @return \OZONE\OZ\Router\RouteOptions
	 */
	public function connect(string $route_path, callable $callable): RouteOptions
	{
		return $this->map('connect', $route_path, $callable);
	}

	/**
	 * Register route for DELETE request method.
	 *
	 * @param string   $route_path
	 * @param callable $callable
	 *
	 * @return \OZONE\OZ\Router\RouteOptions
	 */
	public function delete(string $route_path, callable $callable): RouteOptions
	{
		return $this->map(['delete'], $route_path, $callable);
	}

	/**
	 * Register route for GET request method.
	 *
	 * @param string   $route_path
	 * @param callable $callable
	 *
	 * @return \OZONE\OZ\Router\RouteOptions
	 */
	public function get(string $route_path, callable $callable): RouteOptions
	{
		return $this->map(['get'], $route_path, $callable);
	}

	/**
	 * Register route for HEAD request method.
	 *
	 * @param string   $route_path
	 * @param callable $callable
	 *
	 * @return \OZONE\OZ\Router\RouteOptions
	 */
	public function head(string $route_path, callable $callable): RouteOptions
	{
		return $this->map(['head'], $route_path, $callable);
	}

	/**
	 * Register route for OPTIONS request method.
	 *
	 * @param string   $route_path
	 * @param callable $callable
	 *
	 * @return \OZONE\OZ\Router\RouteOptions
	 */
	public function options(string $route_path, callable $callable): RouteOptions
	{
		return $this->map(['options'], $route_path, $callable);
	}

	/**
	 * Register route for PATCH request method.
	 *
	 * @param string   $route_path
	 * @param callable $callable
	 *
	 * @return \OZONE\OZ\Router\RouteOptions
	 */
	public function patch(string $route_path, callable $callable): RouteOptions
	{
		return $this->map(['patch'], $route_path, $callable);
	}

	/**
	 * Register route for POST request method.
	 *
	 * @param string   $route_path
	 * @param callable $callable
	 *
	 * @return \OZONE\OZ\Router\RouteOptions
	 */
	public function post(string $route_path, callable $callable): RouteOptions
	{
		return $this->map(['post'], $route_path, $callable);
	}

	/**
	 * Register route for PUT request method.
	 *
	 * @param string   $route_path
	 * @param callable $callable
	 *
	 * @return \OZONE\OZ\Router\RouteOptions
	 */
	public function put(string $route_path, callable $callable): RouteOptions
	{
		return $this->map(['put'], $route_path, $callable);
	}

	/**
	 * Register route for TRACE request method.
	 *
	 * @param string   $route_path
	 * @param callable $callable
	 *
	 * @return \OZONE\OZ\Router\RouteOptions
	 */
	public function trace(string $route_path, callable $callable): RouteOptions
	{
		return $this->map(['trace'], $route_path, $callable);
	}

	/**
	 * Run the route that match the current request path.
	 *
	 * @param \OZONE\OZ\Router\RouteInfo $ri
	 *
	 * @throws \Throwable
	 */
	private function runRoute(RouteInfo $ri): void
	{
		static $history = [];

		$route = $ri->getRoute();

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
			$return        = \call_user_func($route->getCallable(), $ri);
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
			))->suspectCallable($route->getCallable());
		}

		if (!$return instanceof Response) {
			throw (new RuntimeException(\sprintf(
				'Invalid return type, got "%s" will expecting "%s".',
				\get_debug_type($return),
				Response::class
			), $debug_data($route)))->suspectCallable($route->getCallable());
		}

		$ri->getContext()
			->setResponse($return);
	}
}
