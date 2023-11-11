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

namespace OZONE\Core\Router\Rates;

use OZONE\Core\Router\RouteInfo;

/**
 * Class SessionRateLimit.
 */
final class SessionRateLimit extends RateLimit
{
	/**
	 * SessionRateLimit constructor.
	 *
	 * @param RouteInfo $ri       the route info
	 * @param int       $rate     number of requests allowed in the interval
	 * @param int       $interval the time window in which the rate limit applies
	 * @param int       $weight   the request weight
	 */
	public function __construct(RouteInfo $ri, int $rate, int $interval, int $weight = 1)
	{
		$sid = $ri->getContext()->session()->id();

		parent::__construct(
			$sid,
			$rate,
			$interval,
			$weight
		);
	}
}