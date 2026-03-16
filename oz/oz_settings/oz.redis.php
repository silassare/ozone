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

return [
	/**
	 * Set to true to enable Redis integration.
	 *
	 * When enabled the {@link \OZONE\Core\Queue\Stores\RedisJobStore} is registered
	 * as an additional job store, and {@link \OZONE\Core\Cache\Drivers\RedisCache}
	 * can be used as the persistent cache provider.
	 *
	 * Requires the ext-redis PHP extension.
	 */
	'OZ_REDIS_ENABLED' => false,

	/**
	 * Redis server hostname or IP address.
	 */
	'OZ_REDIS_HOST' => env('OZ_REDIS_HOST', '127.0.0.1'),

	/**
	 * Redis server port.
	 */
	'OZ_REDIS_PORT' => env('OZ_REDIS_PORT', 6379),

	/**
	 * Redis password (null = no authentication).
	 *
	 * For security, prefer reading from the environment.
	 * Example: 'OZ_REDIS_PASSWORD=your_password' in .env file, and use env('OZ_REDIS_PASSWORD') here.
	 */
	'OZ_REDIS_PASSWORD' => env('OZ_REDIS_PASSWORD', null),

	/**
	 * Redis database index (0-15).
	 */
	'OZ_REDIS_DATABASE' => 0,

	/**
	 * Key prefix applied to every Redis key written by this application.
	 *
	 * Useful when multiple applications share a single Redis instance.
	 * Example: 'myapp' results in keys like 'myapp:oz:jobs:job:{ref}'.
	 */
	'OZ_REDIS_PREFIX' => '',

	/**
	 * Connection timeout in seconds.
	 */
	'OZ_REDIS_TIMEOUT' => 2.5,

	/**
	 * Use a persistent connection (pconnect instead of connect).
	 */
	'OZ_REDIS_PERSISTENT' => false,
];
