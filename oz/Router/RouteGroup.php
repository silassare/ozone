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

use OZONE\OZ\Router\Traits\RouteOptionsShareableTrait;

/**
 * Class RouteGroup.
 */
final class RouteGroup
{
	use RouteOptionsShareableTrait;

	public function __construct(protected Router $router)
	{
	}

	public function group(callable $group): Router
	{
		$this->router->pushGroup($this);
		$group($this->router);
		$this->router->popGroup($this);

		return $this->router;
	}
}
