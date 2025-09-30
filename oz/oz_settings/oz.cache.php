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

use OZONE\Core\Cache\Drivers\DbCache;
use OZONE\Core\Cache\Drivers\RuntimeCache;

return [
	/**
	 * Runtime cache driver: data are lost after runtime.
	 */
	'OZ_RUNTIME_CACHE_PROVIDER'    => RuntimeCache::class,

	/**
	 * Persistent cache driver: data should survive after runtime.
	 */
	'OZ_PERSISTENT_CACHE_PROVIDER' => DbCache::class,

	/**
	 * Add Clear-Site-Data header on logout response.
	 *
	 * @see https://developer.mozilla.org/en-US/docs/Web/HTTP/Headers/Clear-Site-Data
	 *
	 * @default false
	 */
	'OZ_CLEAR_SITE_DATA_HEADER_ON_LOGOUT' => true,

	/**
	 * Value of Clear-Site-Data header on logout response.
	 *
	 * @see https://developer.mozilla.org/en-US/docs/Web/HTTP/Headers/Clear-Site-Data
	 */
	'OZ_CLEAR_SITE_DATA_HEADER_VALUE' => '"cache", "cookies", "storage", "executionContexts"',
];
