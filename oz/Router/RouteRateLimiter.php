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

use OZONE\Core\Cache\CacheManager;
use OZONE\Core\Router\Interfaces\RouteRateLimiterInterface;
use OZONE\Core\Router\Interfaces\RouteRateLimitInterface;

/**
 * Class RouteRateLimiter.
 */
class RouteRateLimiter implements RouteRateLimiterInterface
{
	private CacheManager $cache;

	/**
	 * RouteRateLimiter constructor.
	 *
	 * @param RouteInfo               $ri
	 * @param RouteRateLimitInterface $limit
	 */
	public function __construct(protected RouteInfo $ri, protected RouteRateLimitInterface $limit)
	{
		$this->cache = CacheManager::persistent(self::class);
	}

	/**
	 * {@inheritDoc}
	 */
	public static function get(RouteInfo $ri, RouteRateLimitInterface $limit): RouteRateLimiterInterface
	{
		return new static($ri, $limit);
	}

	/**
	 * {@inheritDoc}
	 */
	public function hit(): bool
	{
		$key      = $this->limit->key();
		$interval = $this->limit->interval();
		$weight   = $this->limit->weight();
		$rate     = $this->limit->rate();
		$now      = \microtime(true);
		$hits     = $this->cache->get($key . ':hits', 0);
		$last_hit = $this->cache->get($key . ':last_hit', $now);

		if ($now - $last_hit > $interval) {
			$hits = 0;
		}

		if ($hits + $weight > $rate) {
			return false;
		}

		$this->cache->set($key . ':last_hit', $now, $interval);

		if (0 === $hits) {
			$this->cache->set($key . ':first_hits', $now, $interval);
			$this->cache->set($key . ':hits', $weight, $interval);
		} else {
			$this->cache->increment($key . ':hits', $weight);
		}

		return true;
	}

	/**
	 * {@inheritDoc}
	 */
	public function reset(): self
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
			'remaining' => $rate - $hits,
			'reset'     => $first_hits + $interval,
		];
	}
}
