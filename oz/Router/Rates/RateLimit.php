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

use OZONE\Core\Router\Interfaces\RouteRateLimitInterface;

/**
 * Class RateLimit.
 */
class RateLimit implements RouteRateLimitInterface
{
	/**
	 * RateLimit constructor.
	 *
	 * @param string $key      the unique key to be used for rate limit
	 * @param int    $rate     number of requests allowed in the interval
	 * @param int    $interval the time window in which the rate limit applies
	 * @param int    $weight   the request weight
	 */
	public function __construct(
		protected readonly string $key,
		protected readonly int $rate,
		protected readonly int $interval,
		protected readonly int $weight = 1
	) {}

	/**
	 * {@inheritDoc}
	 */
	public function key(): string
	{
		return $this->key;
	}

	/**
	 * {@inheritDoc}
	 */
	public function interval(): int
	{
		return $this->interval;
	}

	/**
	 * {@inheritDoc}
	 */
	public function rate(): int
	{
		return $this->rate;
	}

	/**
	 * {@inheritDoc}
	 */
	public function weight(): int
	{
		return $this->weight;
	}
}
