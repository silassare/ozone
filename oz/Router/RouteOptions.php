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

/**
 * Class RouteOptions.
 */
final class RouteOptions extends RouteSharedOptions
{
	private static int $route_count = 0;

	private bool $t_name_is_explicit = false;

	/**
	 * RouteOptions constructor.
	 *
	 * @param string          $path
	 * @param null|RouteGroup $group
	 */
	public function __construct(
		string $path,
		?RouteGroup $group = null
	) {
		parent::__construct($path, $group);

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
	 * Returns true when the route name was set explicitly via {@see self::name()},
	 * false when the auto-generated fallback name is still in use.
	 *
	 * Resumable form providers keyed by `route:{name}` require an explicit name
	 * because auto-generated names are not stable across deployments or PHP workers.
	 *
	 * @return bool
	 */
	public function isNameExplicit(): bool
	{
		return $this->t_name_is_explicit;
	}
}
