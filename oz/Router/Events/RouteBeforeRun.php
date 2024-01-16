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
 * Class RouteBeforeRun.
 *
 * This event is triggered just before a route is executed.
 */
final class RouteBeforeRun extends Hook
{
	/**
	 * RouteBeforeRun constructor.
	 *
	 * @param RouteInfo $target The route info
	 */
	public function __construct(public readonly RouteInfo $target)
	{
		parent::__construct($target->getContext());
	}
}
