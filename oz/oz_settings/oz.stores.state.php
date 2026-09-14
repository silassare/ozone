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

use OZONE\Core\Auth\Methods\DigestAuth;
use OZONE\Core\Cli\Cron\CronRunner;
use OZONE\Core\Forms\Form;
use OZONE\Core\Forms\Resume\FormSessionStore;
use OZONE\Core\Router\RouteRateLimiter;
use OZONE\Core\Stores\Drivers\DbStore;
use OZONE\Core\Stores\StateRegistry;

/**
 * Durable state stores, read with {@see StateRegistry::store()}.
 *
 * Separate from `oz.stores.cache` on purpose: losing a cache entry costs a recomputation, losing one
 * of these is visible to a user -- a wizard that forgot its answers, a rate limit that reset, a
 * replay window that re-opened. The split is what makes that difference visible at the call site
 * instead of hidden in a driver choice.
 *
 * Each value is a config array:
 *
 *   - `driver`          -- FQN of a class implementing StoreDriverInterface. It has to promise
 *                          `StoreCapabilities::$durable`, or the store is refused: `DbStore` and
 *                          `RedisStore` always do, `FileStore` only with `options: {root: state}`
 *                          (which puts its files in `data/state/{scope}` rather than `.ozone/cache`).
 *                          Defaults to `OZ_STATE_DEFAULT` from `oz.stores`.
 *   - `options`         -- driver-specific options passed to `fromConfig()`.
 *   - `expiry_listener` -- FQN of a class implementing StoreEntryExpiryListenerInterface.
 *
 * Consuming projects override individual entries in `app/settings/oz.stores.state.php`; the
 * `array_replace_recursive` merge strategy means overriding one does not affect the others.
 */
return [
	/**
	 * Resumable form sessions: a user's answers to a wizard, mid-flow.
	 *
	 * @see FormSessionStore::CACHE_NAMESPACE
	 */
	'oz:form:sessions' => [
		'driver'          => DbStore::class,
		'expiry_listener' => FormSessionStore::class,
	],

	/**
	 * What a failed form submission already validated, so the client resends only the rest.
	 *
	 * @see Form::FORM_DATA_RESUME_CACHE_NAMESPACE
	 */
	'oz:form:resume' => [
		'driver' => DbStore::class,
	],

	/**
	 * Route rate-limit counters.
	 *
	 * State, not cache: losing them resets a security control, and lets a caller past the limit it
	 * had already reached.
	 *
	 * @see RouteRateLimiter::CACHE_NAMESPACE
	 */
	'oz:rate_limit' => [
		'driver' => DbStore::class,
	],

	/**
	 * The highest nonce count accepted per digest auth nonce.
	 *
	 * State for the same reason: losing it re-opens a replay window.
	 *
	 * @see DigestAuth::NONCE_CACHE_NAMESPACE
	 */
	'oz:auth:digest:nonces' => [
		'driver' => DbStore::class,
	],

	/**
	 * When cron last ran, and whether a scheduler ran it. Shared by the servers of a project: it is
	 * how a request knows a scheduler runs, and whether it has to run the due tasks.
	 *
	 * @see CronRunner::STORE
	 */
	'oz:cron' => [
		'driver' => DbStore::class,
	],
];
