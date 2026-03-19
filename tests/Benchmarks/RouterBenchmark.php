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

use OZONE\Tests\TestUtils;

/**
 * Benchmarks for OZONE\Core\Router\Router.
 *
 * Router::find() is called on every HTTP request.
 * These entries cover static paths, dynamic path segments, optional segments,
 * and 404-miss exhaustion - the most common runtime code paths.
 *
 * Add new entries here whenever the router gains new matching logic
 * (e.g. new guard integration, new param constraint type).
 */
class RouterBenchmark implements BenchmarkSuiteInterface
{
	public static function callables(): array
	{
		$router = TestUtils::router();

		return [
			// Static route table - O(1) hash lookup.
			'router_find_static'          => static fn () => $router->find('GET', '/foo'),
			'router_find_static_deep'     => static fn () => $router->find('GET', '/bar/baz'),

			// Dynamic segments - regex matching against compiled patterns.
			'router_find_dynamic_id'      => static fn () => $router->find('GET', '/users/42'),
			'router_find_dynamic_article' => static fn () => $router->find('GET', '/users/42/articles/published'),
			'router_find_articles_list'   => static fn () => $router->find('GET', '/articles'),
			'router_find_articles_id'     => static fn () => $router->find('GET', '/articles/99'),

			// 404 miss - all routes exhausted before returning NOT_FOUND.
			'router_find_dynamic_miss'    => static fn () => $router->find('GET', '/not-found-route'),
		];
	}
}
