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

namespace OZONE\Core\Cache\Interfaces;

use OZONE\Core\Cache\CacheGarbageCollector;

/**
 * Interface CacheEntryExpiryListenerInterface.
 *
 * Implement this on a class registered in `oz.cache.stores` as `expiry_listener`
 * to receive a callback when entries in a named persistent cache store expire.
 *
 * The garbage collector ({@see CacheGarbageCollector}) fires
 * this method for each expired entry it discovers and immediately deletes the
 * entry afterward.
 */
interface CacheEntryExpiryListenerInterface
{
	/**
	 * Called by the garbage collector when a cache entry expires.
	 *
	 * @param string $key        the original cache key that expired
	 * @param mixed  $value      the value that was stored at the time of expiry
	 * @param string $store_name The name of the named cache store (from `oz.cache.stores`).
	 */
	public static function onCacheEntryExpiry(string $key, mixed $value, string $store_name): void;
}
