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

namespace OZONE\Core\Router\Interfaces;

use OZONE\Core\Http\Response;
use OZONE\Core\Router\RouteInfo;

/**
 * Interface RouteMiddlewareInterface.
 */
interface RouteMiddlewareInterface
{
	/**
	 * Get middleware instance.
	 *
	 * @return self
	 */
	public static function get(): self;

	/**
	 * Run middleware.
	 *
	 * @param RouteInfo $ri
	 */
	public function run(RouteInfo $ri): ?Response;
}
