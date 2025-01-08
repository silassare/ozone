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

use OZONE\Core\Router\RouteInfo;

/**
 * Interface RouteGuardProviderInterface.
 */
interface RouteGuardProviderInterface
{
	/**
	 * Returns route guard.
	 *
	 * @param RouteInfo $ri
	 *
	 * @return null|RouteGuardInterface
	 */
	public static function getGuard(RouteInfo $ri): ?RouteGuardInterface;
}
