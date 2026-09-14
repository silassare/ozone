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
use OZONE\Core\Router\Events\RouteFound;
use OZONE\Core\Router\Events\RouteMethodNotAllowed;
use OZONE\Core\Router\Events\RouteNotFound;
use OZONE\Core\Router\Interfaces\RouteProviderInterface;
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
	 * @var null|RouteGroup
	 */
	private ?RouteGroup $current_group = null;

	/**
	 * @var Route[]
	 */
	private array $static_routes = [];

	/**
	 * @var Route[]
	 */
	private array $dynamic_routes = [];

	/**
	 * Whether both route arrays are already sorted by priority.
	 */
	private bool $ordered = false;

	/**
	 * @var array<string, string>
	 */
	private array $global_params = [];

	/**
	 * @var array<string, callable(Context):(null|numeric|string)>
	 */
	private array $global_params_providers = [];

	/**
	 * The enabled route providers given to {@see registerProviders()}, in order.
	 *
	 * @var array<class-string<RouteProviderInterface>, true>
	 */
	private array $providers = [];

	/**
	 * The route providers registered so far.
	 *
	 * @var array<string, true>
	 */
	private array $registered = [];

	/**
	 * The route providers that do more than map routes: see {@see RouteTable::getEagerProviders()}.
	 *
	 * @var array<string, true>
	 */
	private array $eager = [];

	/**
	 * The group the route providers map their routes in.
	 */
	private ?RouteGroup $providers_group = null;

	/**
	 * The route provider whose routes are being mapped: '' outside of one.
	 */
	private string $source = '';

	/**
	 * The routes of each source, in the order it mapped them: a route is identified across routers
	 * by its source and its position there.
	 *
	 * @var array<string, list<Route>>
	 */
	private array $sources = [];

	/**
	 * How many routes were given each auto name, to tell identical definitions apart.
	 *
	 * @var array<string, int>
	 */
	private array $auto_names = [];

	/**
	 * The table routing requests while not every provider is registered.
	 */
	private ?RouteTable $table = null;

	/**
	 * Whether every provider given to {@see registerProviders()} is registered.
	 */
	private bool $all_registered = true;

	/**
	 * Whether {@see applyRefiners()} ran: a provider registered after it has its refiners run then.
	 */
	private bool $refiners_applied = false;

	/**
	 * Router constructor.
	 */
	public function __construct() {}

	/**
	 * Create a new route group.
	 *
	 * @param string                            $path
	 * @param callable(Router, RouteGroup):void $factory
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
	 * @param string                                  $param
	 * @param string                                  $pattern
	 * @param callable(Context):(null|numeric|string) $provider
	 *
	 * @return Router
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
	 * Registers every route provider first.
	 *
	 * @return Route[]
	 */
	public function getDynamicRoutes(): array
	{
		$this->registerAll();

		return $this->dynamic_routes;
	}

	/**
	 * Gets static routes.
	 *
	 * Registers every route provider first.
	 *
	 * @return Route[]
	 */
	public function getStaticRoutes(): array
	{
		$this->registerAll();

		return $this->static_routes;
	}

	/**
	 * Gets all routes.
	 *
	 * Registers every route provider first.
	 *
	 * @return Route[]
	 */
	public function getRoutes(): array
	{
		$this->registerAll();

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
		return $this->requireRoute($route_name)->buildPath($context, $params);
	}

	/**
	 * Gets route with a given name.
	 *
	 * With a route table, the route's provider is registered when it is not yet.
	 *
	 * @param string $name
	 *
	 * @return null|Route
	 */
	public function getRoute(string $name): ?Route
	{
		if (null !== $this->table && null !== ($at = $this->table->lookup($name))) {
			$route = $this->routeAt($at[0], $at[1]);

			if (null !== $route && $route->getName() === $name) {
				return $route;
			}

			$this->dropTable();
		}

		foreach ($this->static_routes as $route) {
			if ($route->getName() !== $name) {
				continue;
			}

			return $route;
		}

		foreach ($this->dynamic_routes as $route) {
			if ($route->getName() !== $name) {
				continue;
			}

			return $route;
		}

		return null;
	}

	/**
	 * Require a route by name.
	 *
	 * @param string $route_name The route name
	 */
	public function requireRoute(string $route_name): Route
	{
		$route = $this->getRoute($route_name);

		if (!$route) {
			throw new RuntimeException(\sprintf('There is no route named "%s".', $route_name));
		}

		return $route;
	}

	/**
	 * Finds the routes that match the given path.
	 *
	 * With a route table, the matching route is found in the table and only its provider registered:
	 * the result then holds that route alone. `$all` registers every provider instead.
	 *
	 * @param string $method The request method
	 * @param string $path   The request path
	 * @param bool   $all    True to stop searching when a route match
	 *
	 * @return RouteSearchResult
	 */
	public function find(string $method, string $path, bool $all = false): RouteSearchResult
	{
		if (null !== $this->table) {
			if (!$all) {
				$result = $this->findInTable($this->table, $method, $path);

				if (null !== $result) {
					return $result;
				}
			} else {
				$this->registerAll();
			}
		}

		$method          = \strtoupper($method);
		$found           = null;
		$static          = [];
		$dynamic         = [];
		$static_matches  = [];
		$dynamic_matches = [];

		if (isset($this->allowed_methods[$method])) {
			$this->ensureOrdered();

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
				$route  = $result->foundRoute();
				$params = $result->foundRouteParams();

				(new RouteFound($context, $route, $params))->dispatch();

				$ri = new RouteInfo($context, $route, $params, $authenticator);

				if (!$ri->isIntercepted()) {
					$ri->checkRouteForm();
				}

				(new RouteBeforeRun($ri))->dispatch();

				$this->runRoute($ri);

				break;
		}
	}

	/**
	 * Registers, in the current group, the routes of the enabled providers of a route providers map
	 * (`oz.routes`, `oz.routes.api` or `oz.routes.web`).
	 *
	 * With a {@see RouteTable} compiled from the same providers, only the providers that do more than
	 * map routes (such as adding a global parameter) are registered now: each other one when a route
	 * of it is needed -- matched by {@see find()}, or looked up by name -- and all of them when the
	 * routes are listed ({@see getRoutes()}).
	 *
	 * @param array<string, bool> $providers provider class -> enabled
	 * @param null|RouteTable     $table     a table compiled from a router given the same providers
	 */
	public function registerProviders(array $providers, ?RouteTable $table = null): void
	{
		$this->providers_group = $this->current_group;

		foreach ($providers as $provider => $enabled) {
			if (!$enabled) {
				continue;
			}

			if (!\is_subclass_of($provider, RouteProviderInterface::class)) {
				throw new RuntimeException(
					\sprintf(
						'Route provider "%s" should implements "%s".',
						$provider,
						RouteProviderInterface::class
					)
				);
			}

			$this->providers[$provider] = true;
		}

		$this->all_registered = false;

		if (null === $table) {
			$this->registerAll();

			return;
		}

		$this->table = $table;

		foreach ($table->getEagerProviders() as $provider) {
			if (isset($this->providers[$provider])) {
				$this->registerProvider($provider);
			}
		}
	}

	/**
	 * Compiles this router's routes into a {@see RouteTable}, every provider registered.
	 *
	 * Meant for a router whose creation is complete: {@see RouterCreated} dispatched, its refiners
	 * and listeners run.
	 *
	 * @internal
	 */
	public function compileTable(): RouteTable
	{
		$this->registerAll();
		$this->ensureOrdered();

		$static  = [];
		$dynamic = [];
		$names   = [];

		foreach ($this->static_routes as $route) {
			$source  = $route->getSource();
			$ordinal = $route->getOrdinal();

			$static[$route->getPath()][] = [\array_fill_keys($route->getMethods(), 1), $source, $ordinal];
			$names[$route->getName()] ??= [$source, $ordinal];
		}

		foreach ($this->dynamic_routes as $route) {
			$source  = $route->getSource();
			$ordinal = $route->getOrdinal();

			$dynamic[] = [
				Route::REG_DELIMITER . '^' . $route->getParserResult() . '$' . Route::REG_DELIMITER,
				\array_fill_keys($route->getMethods(), 1),
				$source,
				$ordinal,
			];
			$names[$route->getName()] ??= [$source, $ordinal];
		}

		return new RouteTable([
			'format'  => RouteTable::FORMAT,
			'eager'   => \array_keys($this->eager),
			'static'  => $static,
			'dynamic' => $dynamic,
			'names'   => $names,
		]);
	}

	/**
	 * Registers route.
	 *
	 * @param string|string[]         $methods
	 * @param callable|string         $path
	 * @param null|callable           $factory
	 * @param null|RouteSharedOptions $parent  explicit parent; defaults to the current open group when null
	 *
	 * @return RouteOptions
	 */
	public function map(
		array|string $methods,
		callable|string $path,
		?callable $factory = null,
		?RouteSharedOptions $parent = null
	): RouteOptions {
		$parent = $parent ?? $this->current_group;

		if (\is_callable($path)) {
			$factory = $path;
			$path    = '';
		}

		if (!\is_array($methods)) {
			$methods = '*' === $methods ? \array_keys($this->allowed_methods) : [$methods];
		}

		if (empty($path) && !$parent) {
			throw new InvalidArgumentException('Route path cannot be empty when there is no parent.');
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

		$options = new RouteOptions($path, $parent);
		$route   = new Route(
			$this,
			$methods_filtered,
			$factory,
			$options,
			$this->source,
			\count($this->sources[$this->source] ?? [])
		);

		$this->sources[$this->source][] = $route;

		$options->autoName($this->autoName($methods_filtered, $options));

		if ($route->isDynamic()) {
			$this->dynamic_routes[] = $route;
		} else {
			$this->static_routes[] = $route;
		}

		$this->ordered = false;

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
	public function connect(callable|string $path, ?callable $factory = null): RouteOptions
	{
		return $this->map(['connect'], $path, $factory);
	}

	/**
	 * Register route for DELETE request method.
	 *
	 * @param callable|string $path
	 * @param null|callable   $factory
	 *
	 * @return RouteOptions
	 */
	public function delete(callable|string $path, ?callable $factory = null): RouteOptions
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
	public function get(callable|string $path, ?callable $factory = null): RouteOptions
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
	public function head(callable|string $path, ?callable $factory = null): RouteOptions
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
	public function options(callable|string $path, ?callable $factory = null): RouteOptions
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
	public function patch(callable|string $path, ?callable $factory = null): RouteOptions
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
	public function post(callable|string $path, ?callable $factory = null): RouteOptions
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
	public function put(callable|string $path, ?callable $factory = null): RouteOptions
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
	public function trace(callable|string $path, ?callable $factory = null): RouteOptions
	{
		return $this->map(['trace'], $path, $factory);
	}

	/**
	 * Applies all refiners registered on each route's {@see RouteOptions}.
	 *
	 * Should be called once per router after all routes are registered —
	 * typically from the {@see RouterCreated} constructor. Routes added by
	 * refiners (e.g. POST siblings) are NOT themselves passed back to refiners.
	 * After this call every processed {@see RouteOptions} will reject new refiners.
	 *
	 * A route provider registered afterwards, from a route table, has its refiners run when it is.
	 */
	public function applyRefiners(): void
	{
		$this->refiners_applied = true;

		// Snapshot before entering the loop so routes added by refiners
		// (e.g. sibling routes) are not themselves refined.
		$this->refine(\array_merge($this->static_routes, $this->dynamic_routes));
	}

	/**
	 * Registers every route provider not registered yet, in order.
	 */
	private function registerAll(): void
	{
		if ($this->all_registered) {
			return;
		}

		$this->all_registered = true;

		foreach (\array_keys($this->providers) as $provider) {
			$this->registerProvider($provider);
		}
	}

	/**
	 * Registers a route provider's routes, in the group the providers map theirs in.
	 */
	private function registerProvider(string $provider): void
	{
		if (isset($this->registered[$provider])) {
			return;
		}

		$this->registered[$provider] = true;

		$group         = $this->current_group;
		$source        = $this->source;
		$global_params = \count($this->global_params);

		$this->current_group = $this->providers_group;
		$this->source        = $provider;

		try {
			/** @var class-string<RouteProviderInterface> $provider */
			$provider::registerRoutes($this);
		} finally {
			$this->current_group = $group;
			$this->source        = $source;
		}

		$routes = $this->sources[$provider] ?? [];

		if (empty($routes) || $global_params !== \count($this->global_params)) {
			$this->eager[$provider] = true;
		}

		if ($this->refiners_applied) {
			// in the order applyRefiners() goes: static routes first
			$this->refine(\array_merge(
				\array_filter($routes, static fn (Route $route): bool => !$route->isDynamic()),
				\array_filter($routes, static fn (Route $route): bool => $route->isDynamic())
			));
		}
	}

	/**
	 * Runs the refiners of the given routes: what a refiner maps belongs to the refined route's source.
	 *
	 * @param Route[] $routes
	 */
	private function refine(array $routes): void
	{
		$source = $this->source;

		try {
			foreach ($routes as $route) {
				$this->source = $route->getSource();

				$route->getOptions()->runRefiners($this, $route);
			}
		} finally {
			$this->source = $source;
		}
	}

	/**
	 * The route a source mapped at a position, its provider registered first: null when there is no
	 * such route.
	 */
	private function routeAt(string $source, int $ordinal): ?Route
	{
		if ('' !== $source) {
			if (!isset($this->providers[$source])) {
				return null;
			}

			$this->registerProvider($source);
		}

		return $this->sources[$source][$ordinal] ?? null;
	}

	/**
	 * Finds, in the route table, the route matching a request: null when the table turns out not to
	 * describe the routes the code maps, and is dropped.
	 */
	private function findInTable(RouteTable $table, string $method, string $path): ?RouteSearchResult
	{
		$method = \strtoupper($method);

		if (!isset($this->allowed_methods[$method])) {
			return new RouteSearchResult(RouteSearchStatus::NOT_FOUND, null, [], [], [], []);
		}

		[$status, $at] = $table->match($method, $path);

		if (null === $at) {
			return new RouteSearchResult($status, null, [], [], [], []);
		}

		[$source, $ordinal, $dynamic] = $at;

		$route  = $this->routeAt($source, $ordinal);
		$params = [];

		if (null === $route || !$route->is($path, $params) || !$route->accept($method)) {
			$this->dropTable();

			return null;
		}

		$item = ['route' => $route, 'params' => $params];

		return $dynamic
			? new RouteSearchResult($status, $item, [], [$item], [], [$item])
			: new RouteSearchResult($status, $item, [$item], [], [$item], []);
	}

	/**
	 * Stops routing with a table that does not describe the routes the code maps: deletes its file
	 * and registers every provider.
	 */
	private function dropTable(): void
	{
		$table       = $this->table;
		$this->table = null;

		$table?->discard();
		$this->registerAll();
	}

	/**
	 * The name of a route not named explicitly.
	 *
	 * Derived from its methods and path, not from the order routes are mapped in: a router with a
	 * route table maps them in the order requests need them, and the name is part of the route's
	 * identity ({@see Route::key()}), which form sessions and rate limits are keyed by.
	 *
	 * @param string[] $methods
	 */
	private function autoName(array $methods, RouteOptions $options): string
	{
		\sort($methods);

		$name  = 'route_' . \hash('xxh3', \implode(',', $methods) . ' ' . $options->getPath());
		$count = $this->auto_names[$name] = ($this->auto_names[$name] ?? 0) + 1;

		return 1 === $count ? $name : $name . '_' . $count;
	}

	/**
	 * Ensure routes are ordered by priority.
	 */
	private function ensureOrdered(): void
	{
		if ($this->ordered) {
			return;
		}

		\usort($this->static_routes, $this->routePriorityComparator(...));
		\usort($this->dynamic_routes, $this->routePriorityComparator(...));

		$this->ordered = true;
	}

	/**
	 * Comparator for sorting routes by priority.
	 *
	 * The routes with higher priority values will be sorted before those with lower values.
	 *
	 * @param Route $a
	 * @param Route $b
	 *
	 * @return int
	 */
	private function routePriorityComparator(Route $a, Route $b): int
	{
		$ap = $a->getOptions()->getPriority(true);
		$bp = $b->getOptions()->getPriority(true);

		if ($ap === $bp) {
			return 0;
		}

		return ($ap > $bp) ? -1 : 1;
	}

	/**
	 * Run the route that match the current request path.
	 *
	 * @param RouteInfo $ri The route info to run
	 *
	 * @throws Throwable
	 */
	private function runRoute(RouteInfo $ri): void
	{
		$route   = $ri->route();
		$handler = $ri->getEffectiveHandler();

		// Per request, on the root context: a `static` here never reset, so a persistent worker
		// (RoadRunner, Swoole, FrankenPHP worker mode) threw below on its eleventh route run
		// whatever the request, and a single request making many sub-requests hit the same wall.
		$history = $ri->getContext()->navigator()->recordRouteRun($route->getName(), $route->getPath());

		// In a simple and good app
		// this should not be called to much keep it simple
		// 10 is a limit, it may be 5 or 100.
		if (\count($history) >= 10) {
			throw new RuntimeException('Possible recursive redirection.', $history);
		}

		$debug_data = static fn (Route $route, array $data = []): array => [
			'route' => $route->getPath(),
		] + $data;

		$ob_level = \ob_get_level();

		try {
			\ob_start();
			$return        = \call_user_func($handler, $ri);
			$output_buffer = \ob_get_clean();
		} catch (Throwable $t) {
			// Close what was opened here, rather than only emptying it: `ob_clean()` leaves the
			// buffer open, and the only thing that used to close it was the error path
			// (`BaseException::informClient()` unwinds to level 1). A handler that responds and then
			// throws -- what `respond(): never` promises and application code is written around --
			// never reaches that path, so in a worker every such request leaked one level, for the
			// life of the process, with the response nesting deeper inside orphaned buffers.
			while (\ob_get_level() > $ob_level && \ob_end_clean()) {
				// Stops on a buffer that refuses to be removed rather than spinning on it.
			}

			// throw again exactly the same
			throw $t;
		}

		if (!empty($output_buffer)) {
			throw (new RuntimeException(
				'Writing to output buffer is not allowed.',
				$debug_data($route, ['output_buffer' => $output_buffer])
			))->suspectCallable($handler);
		}

		if (!$return instanceof Response) {
			throw (new RuntimeException(\sprintf(
				'Invalid return type, got "%s" will expecting "%s".',
				\get_debug_type($return),
				Response::class
			), $debug_data($route)))->suspectCallable($handler);
		}

		$ri->getContext()
			->setResponse($return);

		$ri->finalize($return);
	}
}
