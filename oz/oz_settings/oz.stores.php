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

use OZONE\Core\Stores\CacheRegistry;
use OZONE\Core\Stores\Drivers\DbStore;
use OZONE\Core\Stores\Drivers\MemoryStore;
use OZONE\Core\Stores\StateRegistry;

return [
	/**
	 * Default runtime cache driver.
	 *
	 * Runtime data is scoped to the current process and lost when it ends.
	 * Used by {@see CacheRegistry::runtime()}.
	 */
	'OZ_CACHE_DEFAULT_RUNTIME'    => MemoryStore::class,

	/**
	 * Default persistent cache driver.
	 *
	 * Persistent data survives process restarts.
	 * Used by {@see CacheRegistry::persistent()} and as the
	 * fallback when a named store in `oz.stores.cache` specifies no `driver` key.
	 */
	'OZ_CACHE_DEFAULT_PERSISTENT' => DbStore::class,

	/**
	 * Default driver of a state store in `oz.stores.state` that names none.
	 *
	 * It must promise `StoreCapabilities::$durable`: losing an entry of a state store is visible to
	 * a user, so it cannot land in `.ozone/cache`, which is per instance and deletable at any time.
	 *
	 * @see StateRegistry
	 */
	'OZ_STATE_DEFAULT'            => DbStore::class,
];
