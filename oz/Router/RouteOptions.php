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

use OZONE\OZ\Exceptions\RuntimeException;
use OZONE\OZ\Router\Traits\RouteOptionsShareableTrait;

/**
 * Class RouteOptions.
 */
final class RouteOptions
{
	use RouteOptionsShareableTrait;

	private static array $names       = [];
	private static int   $route_count = 0;

	private string $name;

	/**
	 * RouteOptions constructor.
	 */
	public function __construct()
	{
		$this->name('route_' . (++self::$route_count));
	}

	/**
	 * RouteOptions destructor.
	 */
	public function __destruct()
	{
		unset($this->route_guard, $this->route_form, $this->route_params);
	}

	/**
	 * Define the route name.
	 *
	 * Should be unique.
	 *
	 * @param string $name
	 *
	 * @return \OZONE\OZ\Router\RouteOptions
	 */
	public function name(string $name): self
	{
		if (isset(self::$names[$name])) {
			throw new RuntimeException(\sprintf('Route name "%s" is already in use.', $name));
		}

		self::$names[$name] = 1;
		$this->name         = $name;

		return $this;
	}

	/**
	 * Gets route name.
	 *
	 * @return string
	 */
	public function getName(): string
	{
		return $this->name;
	}
}
