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
use Override;
use OZONE\Core\Exceptions\RuntimeException;

/**
 * Class RouteOptions.
 */
final class RouteOptions extends RouteSharedOptions
{
	protected string $full_path_prefix = '';

	/**
	 * @var list<callable(Router, Route):void>
	 */
	private array $refiners = [];

	/**
	 * True after {@see runRefiners()} has been called.
	 * Prevents adding refiners to an already-refined route.
	 */
	private bool $refined = false;

	private bool $t_name_is_explicit = false;

	/**
	 * The route's methods, sorted and joined, which its auto name is derived from: null until the
	 * router gives them ({@see autoName()}).
	 */
	private ?string $auto_name_methods = null;

	/**
	 * RouteOptions constructor.
	 *
	 * The router names the route ({@see autoName()}) until {@see name()} does.
	 */
	public function __construct(
		string $path,
		?RouteSharedOptions $parent = null
	) {
		parent::__construct($path, $parent);
	}

	/**
	 * Names the route from its methods, host and path, unless {@see name()} does: the name is not
	 * explicit.
	 *
	 * The name is derived when it is read, never here: `host()` and the group options are chained
	 * after the route is mapped. It does not depend on the order routes are mapped in, since a
	 * router with a route table maps them in the order requests need them, and the name is part of
	 * the route's identity ({@see Route::key()}), which form sessions and rate limits are keyed by.
	 * Two routes of the same methods, host and path get one name, and the router refuses them.
	 *
	 * @param string[] $methods
	 *
	 * @return $this
	 *
	 * @internal called by {@see Router::map()} only
	 */
	public function autoName(array $methods): static
	{
		\sort($methods);

		$this->auto_name_methods = \implode(',', $methods);

		return $this;
	}

	#[Override]
	public function name(string $name): static
	{
		// only group name can be empty
		if ('' === \trim($name)) {
			throw new InvalidArgumentException('Route name must be non-empty and not whitespace-only string.');
		}

		$this->t_name_is_explicit = true;

		return parent::name($name);
	}

	/**
	 * Sets a full path prefix for the route.
	 *
	 * @param string $path the path to be used as prefix for the full route path
	 *
	 * @return $this
	 */
	public function fullPathPrefix(string $path): static
	{
		$this->full_path_prefix = $path;

		// The full path is cached until an option changes.
		self::changed();

		return $this;
	}

	/**
	 * Returns true when the route name was set explicitly via {@see self::name()},
	 * false when the auto-generated fallback name is still in use.
	 *
	 * Some features may require an explicit name because auto-generated names
	 * are not stable across deployments or PHP workers.
	 */
	public function isNameExplicit(): bool
	{
		return $this->t_name_is_explicit;
	}

	/**
	 * Registers a refiner on this specific route.
	 *
	 * A refiner is called by {@see Router::applyRefiners()} with the router and
	 * the route this options instance belongs to. This can be used to add sibling
	 * routes, mutate options, etc., once all routes are fully registered.
	 *
	 * @param callable(Router, Route):void $refiner
	 *
	 * @return $this
	 *
	 * @throws RuntimeException when called after refiners have already been applied
	 */
	public function pushRefiner(callable $refiner): static
	{
		if ($this->refined) {
			throw new RuntimeException('Cannot add a refiner to a route that has already been refined.');
		}

		$this->refiners[] = $refiner;

		return $this;
	}

	/**
	 * Executes all refiners registered on this route.
	 *
	 * Called by {@see Router::applyRefiners()} for each route.
	 * After this call, {@see pushRefiner()} will throw.
	 *
	 * @param Router $router the router to pass to each refiner
	 * @param Route  $route  the route this options instance belongs to
	 *
	 * @internal this should only be called by the router, and only once per route dispatch
	 */
	public function runRefiners(Router $router, Route $route): void
	{
		$this->refined = true;

		foreach ($this->refiners as $refiner) {
			$refiner($router, $route);
		}
	}

	/**
	 * The explicit name, else the auto name: `route_` and a hash of the sorted methods, the full host
	 * template when there is one, and the full path. A route declaring no host hashes what it always
	 * did (methods and path), so its name is unchanged.
	 */
	#[Override]
	protected function localName(): string
	{
		if ($this->t_name_is_explicit || null === $this->auto_name_methods) {
			return parent::localName();
		}

		$host = $this->getHost();
		$path = $this->getPath();
		$what = null === $host ? $path : '//' . $host . $path;

		return 'route_' . \hash('xxh3', $this->auto_name_methods . ' ' . $what);
	}

	/**
	 * The route's full path, behind its full path prefix.
	 */
	#[Override]
	protected function computeFullPath(): string
	{
		$path = parent::computeFullPath();

		if (!empty($this->full_path_prefix)) {
			$path = self::safePathConcat($this->full_path_prefix, $path);
		}

		return $path;
	}
}
