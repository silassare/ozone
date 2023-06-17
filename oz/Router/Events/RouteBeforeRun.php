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
	 * @param \OZONE\Core\Router\RouteInfo $ri
	 */
	public function __construct(protected RouteInfo $ri)
	{
		parent::__construct($ri->getContext());
	}

	/**
	 * RouteBeforeRun destructor.
	 */
	public function __destruct()
	{
		parent::__destruct();
		unset($this->ri);
	}

	/**
	 * Gets route info.
	 *
	 * @return \OZONE\Core\Router\RouteInfo
	 */
	public function getRouteInfo(): RouteInfo
	{
		return $this->ri;
	}
}
