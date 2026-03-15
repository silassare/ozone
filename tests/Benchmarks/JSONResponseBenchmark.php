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

use OZONE\Core\App\JSONResponse;

/**
 * Benchmarks for OZONE\Core\App\JSONResponse.
 *
 * A JSONResponse is built inside every route handler and serialised before
 * the response is sent. Construction + toArray() cost is paid on each request.
 *
 * Add new entries here when new response-building helpers are added.
 */
class JSONResponseBenchmark implements BenchmarkSuiteInterface
{
    public static function callables(): array
    {
        return [
            // Success path: set message + data payload, then serialise.
            'json_response_done'  => static function () {
                return (new JSONResponse())
                    ->setDone('OK')
                    ->setData(['id' => 1, 'name' => 'test'])
                    ->toArray();
            },

            // Error path: set error message, then serialise.
            'json_response_error' => static function () {
                return (new JSONResponse())
                    ->setError('OZ_ERROR_NOT_FOUND')
                    ->toArray();
            },
        ];
    }
}
