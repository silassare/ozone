<?php

/**
 * Copyright (c) 2017-present, Emile Silas Sare
 *
 * This file is part of OZone package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace OZONE\OZ\Router;

interface RouteProviderInterface
{
	/**
	 * Called to register the service routes.
	 *
	 * @param \OZONE\OZ\Router\Router $router
	 */
	public static function registerRoutes(Router $router);
}
