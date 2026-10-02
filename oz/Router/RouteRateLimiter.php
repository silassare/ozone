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

namespace OZONE\Core\Router;

use Override;
use OZONE\Core\Router\Interfaces\RouteRateLimiterInterface;
use OZONE\Core\Router\Interfaces\RouteRateLimitInterface;
use OZONE\Core\Stores\KeyValueStore;
use OZONE\Core\Stores\StateRegistry;

/**
 * Class RouteRateLimiter.
 */
class RouteRateLimiter implements RouteRateLimiterInterface
{
	public const CACHE_NAMESPACE = 'oz:rate_limit';
	private KeyValueStore $cache;

	/**
	 * RouteRateLimiter constructor.
	 *
	 * @param RouteInfo               $ri
	 * @param RouteRateLimitInterface $limit
	 */
	public function __construct(protected RouteInfo $ri, protected RouteRateLimitInterface $limit)
	{
		$this->cache = StateRegistry::store(self::CACHE_NAMESPACE);
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public static function get(RouteInfo $ri, RouteRateLimitInterface $limit): static
	{
		return new static($ri, $limit);
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function hit(): bool
	{
		$key      = $this->limit->key();
		$interval = $this->limit->interval();
		$weight   = $this->limit->weight();
		$now      = \microtime(true);

		// Counted and read in one atomic step: requests arriving together are all counted, so no
		// more of them pass than the limit allows. A refused hit counts too.
		$hits = $this->cache->count($key . ':hits', $weight, $interval);

		// The hit that opened the window says when it ends.
		if ($hits == $weight) {
			$this->cache->set($key . ':first_hits', $now, $interval);
		}

		$this->cache->set($key . ':last_hit', $now, $interval);

		return $hits <= $this->limit->rate();
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function reset(): static
	{
		$key = $this->limit->key();

		$this->cache->delete($key . ':first_hits');
		$this->cache->delete($key . ':hits');
		$this->cache->delete($key . ':last_hit');

		return $this;
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function status(): array
	{
		$key      = $this->limit->key();
		$interval = $this->limit->interval();
		$rate     = $this->limit->rate();
		$now      = \microtime(true);

		$first_hits = $this->cache->get($key . ':first_hits', $now);
		$hits       = $this->cache->get($key . ':hits', 0);

		return [
			'limit'     => $rate,
			'remaining' => \max(0, $rate - $hits),
			'reset'     => $first_hits + $interval,
		];
	}
}
