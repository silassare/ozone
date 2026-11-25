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

use Override;
use OZONE\Core\Router\Interfaces\RouteRateLimitInterface;
use OZONE\Core\Router\RouteInfo;

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

	#[Override]
	public function key(): string
	{
		return $this->key;
	}

	/**
	 * The key of a quota per route: `{by}:{route key}:{who}`, then `:{kind}` when a route counts some
	 * requests apart from the others (a form discovery). Keyed per route, because a key without the
	 * route makes every route limited the same way share one quota.
	 *
	 * @param string $by   what the quota is per: `ip`, `user`, `session`
	 * @param string $who  the address, the user or the session counted
	 * @param string $kind what is counted apart, or empty
	 */
	public static function routeKey(string $by, RouteInfo $ri, string $who, string $kind = ''): string
	{
		$key = \sprintf('%s:%s:%s', $by, $ri->route()->key(), $who);

		return '' === $kind ? $key : $key . ':' . $kind;
	}

	#[Override]
	public function interval(): int
	{
		return $this->interval;
	}

	#[Override]
	public function rate(): int
	{
		return $this->rate;
	}

	#[Override]
	public function weight(): int
	{
		return $this->weight;
	}
}
