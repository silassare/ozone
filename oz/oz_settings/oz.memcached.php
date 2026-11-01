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

use OZONE\Core\Stores\Drivers\MemcachedStore;

return [
	/**
	 * Enable Memcached support.
	 *
	 * When enabled {@link MemcachedStore} can be used as a cache store's driver. Never as a state
	 * store's: Memcached evicts under pressure, and what a state store holds cannot be lost.
	 *
	 * Requires the ext-memcached PHP extension.
	 */
	'OZ_MEMCACHED_ENABLED' => \extension_loaded('memcached'),

	/**
	 * The servers of the pool: `host` or `host:port`, several separated by a comma.
	 *
	 * A list of `[host, port]` pairs is accepted too, as `Memcached::addServers()` takes it; a
	 * comma-separated string is the form an environment variable can carry. The port defaults to
	 * 11211 where an entry leaves it out.
	 *
	 * A store may name its own pool instead, through the `servers` option of its entry in
	 * `oz.stores.cache`.
	 */
	'OZ_MEMCACHED_SERVERS' => env('OZ_MEMCACHED_SERVERS', MemcachedStore::DEFAULT_SERVERS),
];
