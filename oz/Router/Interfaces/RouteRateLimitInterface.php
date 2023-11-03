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

/**
 * Interface RouteRateLimitInterface.
 */
interface RouteRateLimitInterface
{
	/**
	 * Should return a unique key to be used for rate limit.
	 *
	 * @return string
	 */
	public function key(): string;

	/**
	 * Should return the rate limit interval in seconds.
	 *
	 * The interval is the time window in which the rate limit applies.
	 *
	 * @return int
	 */
	public function interval(): int;

	/**
	 * Should return the rate limit.
	 *
	 * The number of requests allowed in the interval.
	 *
	 * @return int
	 */
	public function rate(): int;

	/**
	 * Should return the request weight.
	 *
	 * @return int the request weight
	 */
	public function weight(): int;
}
