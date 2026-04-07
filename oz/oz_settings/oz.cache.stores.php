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

use OZONE\Core\Cache\CacheRegistry;
use OZONE\Core\Cache\Drivers\DbCache;
use OZONE\Core\Forms\Form;
use OZONE\Core\Forms\Services\ResumableFormService;
use OZONE\Core\FS\Filters\ImageFileFilterHandler;

/**
 * Named cache store definitions.
 *
 * Each key is a store name used with {@see CacheRegistry::store()}.
 * Each value is a config array with these optional keys:
 *
 *   - `driver`          — FQN of a class implementing CacheProviderInterface.
 *                         Defaults to `OZ_CACHE_DEFAULT_PERSISTENT` from `oz.cache`.
 *   - `options`         — Driver-specific options array passed to `fromConfig()`.
 *   - `expiry_listener` — FQN of a class implementing CacheEntryExpiryListenerInterface.
 *                         When set, the CacheGarbageCollector calls its `onCacheEntryExpiry()`
 *                         static method for each expired entry found in this store.
 *
 * Consuming projects override individual store entries in `app/settings/oz.cache.stores.php`.
 * The settings merge strategy (`array_replace_recursive`) means overriding one store
 * does not affect the others.
 */
return [
	/**
	 * Store for route rate-limit counters.
	 */
	'oz:rate_limit' => [
		'driver' => DbCache::class,
	],

	/**
	 * Store for in-progress multi-step form resume data.
	 *
	 * @see Form::FORM_DATA_RESUME_CACHE_NAMESPACE
	 */
	'oz:form:resume' => [
		'driver' => DbCache::class,
	],

	/**
	 * Store for resumable form sessions.
	 *
	 * @see ResumableFormService::CACHE_NAMESPACE
	 */
	'oz:form:sessions' => [
		'driver'          => DbCache::class,
		'expiry_listener' => ResumableFormService::class,
	],

	/**
	 * Store for processed image filter output cache.
	 *
	 * @see ImageFileFilterHandler
	 */
	'oz:fs:image:filters' => [
		'driver' => DbCache::class,
	],
];
