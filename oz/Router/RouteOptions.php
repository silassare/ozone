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
	private static int $route_count    = 0;

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
	 * RouteOptions constructor.
	 *
	 * @param string                  $path
	 * @param null|RouteSharedOptions $parent
	 */
	public function __construct(
		string $path,
		?RouteSharedOptions $parent = null
	) {
		parent::__construct($path, $parent);

		$this->name('route_' . (++self::$route_count));
		// auto-generated names are not considered explicit so we revert to false here:
		$this->t_name_is_explicit = false;
	}

	/**
	 * {@inheritDoc}
	 */
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

		return $this;
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function getPath(bool $full = true): string
	{
		$path = parent::getPath($full);

		if ($full && !empty($this->full_path_prefix)) {
			$path = self::safePathConcat($this->full_path_prefix, $path);
		}

		return $path;
	}

	/**
	 * Returns true when the route name was set explicitly via {@see self::name()},
	 * false when the auto-generated fallback name is still in use.
	 *
	 * Some features may require an explicit name because auto-generated names
	 * are not stable across deployments or PHP workers.
	 *
	 * @return bool
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
}
