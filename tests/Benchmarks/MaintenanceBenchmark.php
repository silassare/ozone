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

use Override;
use OZONE\Core\Maintenance\Maintenance;

/**
 * Benchmarks for OZONE\Core\Maintenance\Maintenance.
 *
 * Every request asks whether a maintenance holds it, before it is routed: while none is on, the
 * answer must stay one stat of one file.
 */
class MaintenanceBenchmark implements BenchmarkSuiteInterface
{
	#[Override]
	public static function callables(): array
	{
		Maintenance::disable();

		return [
			'maintenance_current_off' => static fn () => Maintenance::current(),
		];
	}
}
