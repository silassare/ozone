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

use OZONE\Core\Utils\Hasher;

/**
 * Benchmarks for OZONE\Core\Utils\Hasher.
 *
 * Hasher methods underpin session ID generation, file access keys, auth tokens,
 * and short display identifiers. Any degradation here affects every request.
 *
 * Add new entries here whenever a new Hasher method is introduced.
 */
class HasherBenchmark implements BenchmarkSuiteInterface
{
    public static function callables(): array
    {
        return [
            'hasher_hash32'  => static fn() => Hasher::hash32('benchmark-input-string'),
            'hasher_hash64'  => static fn() => Hasher::hash64('benchmark-input-string'),
            'hasher_shorten' => static fn() => Hasher::shorten('benchmark-input-string'),
        ];
    }
}
