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

namespace OZONE\Tests\Benchmarks;

use OZONE\Core\Cache\CacheRegistry;

/**
 * Benchmarks for {@see CacheRegistry} / {@see CacheStore} (runtime driver).
 *
 * Cache set/get/has are among the hottest paths in request-scoped caching.
 * The remember() pattern (lazy-computed value) is also used heavily for
 * computed settings and memoized lookups.
 *
 * Add new entries here when new drivers or cache access patterns are added.
 */
class CacheBenchmark implements BenchmarkSuiteInterface
{
	public static function callables(): array
	{
		$cache = CacheRegistry::runtime('benchmark_suite');
		// Pre-populate so get/has always exercise a hit path.
		$cache->set('bench_key', 'bench_value');

		return [
			'cache_set'     => static fn () => $cache->set('bench_key', 'bench_value'),
			'cache_get_hit' => static fn () => $cache->get('bench_key'),
			'cache_has'     => static fn () => $cache->has('bench_key'),

			// remember() returns cached value on subsequent calls - tests memoize path.
			'cache_remember' => static fn () => $cache->remember(
				'bench_remember_key',
				static fn () => 'computed_value'
			),
		];
	}
}
