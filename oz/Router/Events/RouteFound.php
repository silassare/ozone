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

namespace OZONE\Core\Router\Events;

use OZONE\Core\Hooks\Hook;
use OZONE\Core\Router\RouteInfo;

/**
 * Class RouteFound.
 *
 * This event is triggered when a route is found for the requested resource.
 * The route form is not checked at this point.
 */
final class RouteFound extends Hook
{
	public function __construct(
		public readonly RouteInfo $ri,
	) {
		parent::__construct($ri->getContext());
	}
}
