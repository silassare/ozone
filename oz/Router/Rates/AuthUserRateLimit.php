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

use OZONE\Core\Auth\AuthUsers;
use OZONE\Core\Router\RouteInfo;

/**
 * Class AuthUserRateLimit.
 */
final class AuthUserRateLimit extends RateLimit
{
	/**
	 * AuthUserRateLimit constructor.
	 *
	 * @param RouteInfo $ri       the route info
	 * @param int       $rate     number of requests allowed in the interval
	 * @param int       $interval the time window in which the rate limit applies
	 * @param int       $weight   the request weight
	 * @param string    $kind     what is counted, when a route counts some requests apart from the
	 *                            others: each kind has its own quota
	 */
	public function __construct(RouteInfo $ri, int $rate, int $interval, int $weight = 1, string $kind = '')
	{
		// Per route, as every quota: the signed-in user has one on each route limited this way.
		parent::__construct(
			self::routeKey('user', $ri, AuthUsers::ref($ri->getContext()->auth()->user()), $kind),
			$rate,
			$interval,
			$weight
		);
	}
}
