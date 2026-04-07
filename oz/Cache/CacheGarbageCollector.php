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

namespace OZONE\Core\Cache;

use Override;
use OZONE\Core\App\Settings;
use OZONE\Core\Cache\Interfaces\CacheEntryExpiryListenerInterface;
use OZONE\Core\Hooks\Events\FinishHook;
use OZONE\Core\Hooks\Interfaces\BootHookReceiverInterface;

/**
 * Class CacheGarbageCollector.
 *
 * Boot hook receiver that registers a {@see FinishHook} listener to scan for
 * expired entries in named persistent cache stores and fire expiry callbacks.
 *
 * Expiry callbacks are configured per named store in `oz.cache.stores`:
 *
 *   return [
 *       'oz:form:sessions' => [
 *           'driver'          => DbCache::class,
 *           'expiry_listener' => ResumableFormService::class,
 *       ],
 *   ];
 *
 * The `expiry_listener` class must implement {@see CacheEntryExpiryListenerInterface}.
 * It is called once per expired entry found. Entries are hard-deleted from the
 * store immediately after the callback returns (even if the callback throws).
 *
 * Only drivers that advertise `expiryCallbacks = true` via {@see CacheCapabilities}
 * are scanned.
 */
final class CacheGarbageCollector implements BootHookReceiverInterface
{
	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public static function boot(): void
	{
		FinishHook::listen(static function (): void {
			self::gc();
		});
	}

	/**
	 * Scans named stores for expired entries and fires any registered expiry listeners.
	 */
	private static function gc(): void
	{
		$stores_config = Settings::load('oz.cache.stores');

		foreach ($stores_config as $name => $config) {
			$listener_class = $config['expiry_listener'] ?? null;

			if (null === $listener_class) {
				continue;
			}

			$store    = CacheRegistry::store((string) $name);
			$provider = $store->getProvider();

			// Skip drivers that cannot enumerate expired entries.
			if (!$provider->capabilities()->expiryCallbacks) {
				continue;
			}

			$expired = $provider->getExpiredEntries(100);

			foreach ($expired as $entry) {
				try {
					/** @var CacheEntryExpiryListenerInterface $listener_class */
					$listener_class::onCacheEntryExpiry($entry->key, $entry->value, (string) $name);
				} finally {
					// Always delete, even if the listener threw.
					$provider->delete($entry->key);
				}
			}
		}
	}
}
