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

/**
 * Interface BenchmarkSuiteInterface.
 *
 * All benchmark suite classes in tests/Benchmarks/ must implement this interface.
 * The runner (tests/run_benchmarks.php) auto-discovers every *Benchmark.php file
 * in the directory and calls ::callables() to collect the entries.
 *
 * Label convention: area_operation[_variant]
 *   e.g. router_find_static, hasher_hash64, docrypt_encrypt_aes256
 */
interface BenchmarkSuiteInterface
{
    /**
     * Returns a map of benchmark entries to measure.
     * Keys are stable labels (used to match against the baseline JSON).
     * Values are zero-argument callables whose execution time is measured.
     *
     * @return array<string, callable>
     */
    public static function callables(): array;
}
