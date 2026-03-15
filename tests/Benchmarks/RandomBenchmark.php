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

use OZONE\Core\Utils\Random;

/**
 * Benchmarks for OZONE\Core\Utils\Random.
 *
 * Random generators are used for auth codes, session tokens, API keys, file
 * names, and PIN generation. Throughput matters when these are called on
 * every authenticated request.
 *
 * Add new entries here whenever a new Random method or character set is added.
 */
class RandomBenchmark implements BenchmarkSuiteInterface
{
    public static function callables(): array
    {
        return [
            // Full character set — worst-case entropy draw.
            'random_string_32'   => static fn() => Random::string(32),

            // Alphanumeric — common for tokens and auth codes.
            'random_alphanum_16' => static fn() => Random::alphaNum(16),

            // Numeric — used for PIN / OTP codes.
            'random_num_6'       => static fn() => Random::num(6),

            // Primitives.
            'random_int'         => static fn() => Random::int(0, 1_000_000),
            'random_bool'        => static fn() => Random::bool(),
        ];
    }
}
