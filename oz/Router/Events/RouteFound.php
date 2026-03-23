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

use OZONE\Core\App\Context;
use OZONE\Core\Hooks\Hook;
use OZONE\Core\Router\Route;

/**
 * Class RouteFound.
 *
 * This event is triggered when a route is found for the requested resource.
 *
 * The difference between this event and {@see RouteBeforeRun} is that this event
 * is triggered before any route guard is checked, while {@see RouteBeforeRun}
 * is triggered after all guards have been checked and passed.
 */
final class RouteFound extends Hook
{
	public function __construct(
		Context $context,
		public readonly Route $route,
		public readonly array $params
	) {
		parent::__construct($context);
	}
}
