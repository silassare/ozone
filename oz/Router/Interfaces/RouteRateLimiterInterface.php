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
 * Interface RouteRateLimiterInterface.
 */
interface RouteRateLimiterInterface
{
	/**
	 * Should return the rate limiter instance for the given key.
	 *
	 * @param RouteInfo               $ri
	 * @param RouteRateLimitInterface $limit
	 *
	 * @return RouteRateLimiterInterface
	 */
	public static function get(RouteInfo $ri, RouteRateLimitInterface $limit): self;

	/**
	 * Should check if the rate limit is not reached.
	 *
	 * @return bool true if the rate limit is not reached
	 */
	public function hit(): bool;

	/**
	 * Should reset the rate limit.
	 *
	 * @return $this
	 */
	public function reset(): self;

	/**
	 * Should return the rate limit status.
	 *
	 * @return array{limit:int, remaining:int, reset:int}
	 */
	public function status(): array;
}
