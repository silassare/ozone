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

use OZONE\Core\Http\Uri;

/**
 * Benchmarks for OZONE\Core\Http\Uri.
 *
 * Uri is parsed once per request and mutated via immutable wither methods
 * throughout the routing and redirect pipeline. Allocation cost matters.
 *
 * Add new entries here when new URI mutation helpers are introduced.
 */
class UriBenchmark implements BenchmarkSuiteInterface
{
    public static function callables(): array
    {
        $rawUri = 'https://example.com/users/42?page=1&sort=asc#section';

        return [
            // Parsing a full URI string into an immutable value object.
            'uri_parse'        => static fn() => Uri::createFromString($rawUri),

            // Single immutable mutations — one new object allocated per call.
            'uri_mutate_path'  => static fn() => Uri::createFromString($rawUri)
                ->withPath('/api/v1/items'),
            'uri_mutate_query' => static fn() => Uri::createFromString($rawUri)
                ->withQuery('q=test&page=2'),

            // Chained mutations — multiple allocations in sequence.
            'uri_mutate_chain' => static fn() => Uri::createFromString($rawUri)
                ->withPath('/api/v2/products')
                ->withQuery('search=foo&limit=20')
                ->withFragment('results'),
        ];
    }
}
