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

		return parent::name($name);
	}
}
