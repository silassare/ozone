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

use OZONE\Core\Router\Router;

/**
 * Interface RouteProviderInterface.
 */
interface RouteProviderInterface
{
	/**
	 * Called to register the service routes.
	 *
	 * @param \OZONE\Core\Router\Router $router
	 */
	public static function registerRoutes(Router $router);
}
