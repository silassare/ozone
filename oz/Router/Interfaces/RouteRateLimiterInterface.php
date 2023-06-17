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
 * Interface RouteRateLimiterInterface.
 */
interface RouteRateLimiterInterface
{
	/**
	 * Should return the rate limit instance for the given key.
	 *
	 * @param string $key
	 *
	 * @return \OZONE\Core\Router\Interfaces\RouteRateLimiterInterface
	 */
	public static function get(string $key): self;

	/**
	 * Sets the max allowed attempts in the given interval.
	 *
	 * @param int $max_attempts
	 * @param int $interval
	 *
	 * @return self
	 */
	public function setRate(int $max_attempts, int $interval): self;

	/**
	 * Sets the interval in seconds.
	 *
	 * @param int $interval
	 *
	 * @return $this
	 */
	public function setInterval(int $interval): self;

	/**
	 * Should allow or not consecutive attempts.
	 *
	 * @param bool $allow
	 *
	 * @return bool
	 */
	public function allowConsecutiveAttempts(bool $allow): bool;

	/**
	 * Should check if the rate limit is not reached.
	 *
	 * @param int $weight The weight of the request, some requests may be heavier than others
	 */
	public function hit(int $weight = 1): void;

	/**
	 * Returns the rate limit reports.
	 *
	 * @return array
	 */
	public function reports(): array;
}
