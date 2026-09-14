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
use OZONE\Core\Stores\StateRegistry;

/**
 * Named cache store definitions.
 *
 * Caches only: an entry here may be dropped at any time, and everything in it can be rebuilt. State
 * whose loss a user would notice -- form sessions, rate limits, replay windows -- is declared in
 * `oz.stores.state` and read with {@see StateRegistry::store()} instead.
 *
 * Each key is a store name used with {@see CacheRegistry::store()}.
 * Each value is a config array with these optional keys:
 *
 *   - `driver`          — FQN of a class implementing StoreDriverInterface.
 *                         Defaults to `OZ_CACHE_DEFAULT_PERSISTENT` from `oz.stores`.
 *   - `options`         — Driver-specific options array passed to `fromConfig()`.
 *   - `expiry_listener` — FQN of a class implementing StoreEntryExpiryListenerInterface.
 *                         When set, the StoresGarbageCollector calls its `onCacheEntryExpiry()`
 *                         static method for each expired entry found in this store.
 *
 * None ships: the framework's own caches are files (image filter renditions,
 * `\OZONE\Core\FS\Filters\ImageFileFilterHandler`) or per-request memory.
 *
 * Consuming projects override individual store entries in `app/settings/oz.stores.cache.php`.
 * The settings merge strategy (`array_replace_recursive`) means overriding one store
 * does not affect the others.
 */
return [];
