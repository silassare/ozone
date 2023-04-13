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

/**
 * Class RouteOptions.
 */
final class RouteOptions extends RouteSharedOptions
{
	private static int $route_count = 0;

	/**
	 * RouteOptions constructor.
	 *
	 * @param string                           $path
	 * @param null|\OZONE\OZ\Router\RouteGroup $group
	 */
	public function __construct(
		string $path,
		?RouteGroup $group = null
	) {
		parent::__construct($path, $group);

		$this->name('route_' . (++self::$route_count));
	}
}
