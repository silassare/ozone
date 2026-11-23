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
use OZONE\Core\App\Settings;

/**
 * Benchmarks for OZONE\Core\App\Settings.
 *
 * A request reads settings a few hundred times: the read of a top-level key of a loaded group is the
 * path every one of them takes, and nothing that changes how the settings are held may slow it.
 */
class SettingsBenchmark implements BenchmarkSuiteInterface
{
	#[Override]
	public static function callables(): array
	{
		// Loaded once, as the first read of a request loads it.
		Settings::load('oz.request');

		return [
			'settings_get' => static fn () => Settings::get('oz.request', 'OZ_DEFAULT_ORIGIN'),
		];
	}
}
