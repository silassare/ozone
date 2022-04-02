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

namespace OZONE\OZ\Router\Interfaces;

use OZONE\OZ\Router\RouteGuard;
use OZONE\OZ\Router\RouteInfo;

/**
 * Interface RouteGuardProviderInterface.
 */
interface RouteGuardProviderInterface
{
	/**
	 * Returns route guard.
	 *
	 * @param \OZONE\OZ\Router\RouteInfo $ri
	 *
	 * @return null|\OZONE\OZ\Router\RouteGuard
	 */
	public static function getGuard(RouteInfo $ri): ?RouteGuard;
}
