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

namespace OZONE\Core\Stores;

use Override;
use OZONE\Core\App\GarbageCollector;
use OZONE\Core\App\Settings;
use OZONE\Core\Hooks\Interfaces\BootHookReceiverInterface;
use OZONE\Core\Stores\Interfaces\StoreEntryExpiryListenerInterface;

/**
 * Class StoresGarbageCollector.
 *
 * Boot hook receiver that registers a {@see GarbageCollector} collector scanning for
 * expired entries in named persistent cache stores and firing expiry callbacks.
 *
 * Expiry callbacks are configured per named store in `oz.stores.cache`:
 *
 *   return [
 *       'oz:form:sessions' => [
 *           'driver'          => DbStore::class,
 *           'expiry_listener' => FormSessionStore::class,
 *       ],
 *   ];
 *
 * The `expiry_listener` class must implement {@see StoreEntryExpiryListenerInterface}.
 * It is called once per expired entry found. Entries are hard-deleted from the
 * store immediately after the callback returns (even if the callback throws).
 *
 * Only drivers that advertise `expiryCallbacks = true` via {@see StoreCapabilities}
 * are scanned.
 */
final class StoresGarbageCollector implements BootHookReceiverInterface
{
	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public static function boot(): void
	{
		GarbageCollector::register('oz:cache', self::gc(...));
	}

	/**
	 * Scans named stores for expired entries and fires any registered expiry listeners.
	 */
	private static function gc(): void
	{
		// Both groups: an expiry listener matters most on a state store (a form session that ends
		// unfinished has to be told about it), so scanning caches only would have missed them.
		$groups = [
			'oz.stores.cache'              => static fn (string $name) => CacheRegistry::store($name),
			StateRegistry::SETTINGS_GROUP  => static fn (string $name) => StateRegistry::store($name),
		];

		foreach ($groups as $group => $resolve) {
			self::gcGroup($group, $resolve);
		}
	}

	/**
	 * Scans the stores of one settings group.
	 *
	 * @param string   $group   the settings group declaring the stores
	 * @param callable $resolve returns the store for a name
	 */
	private static function gcGroup(string $group, callable $resolve): void
	{
		$stores_config = Settings::load($group);

		foreach ($stores_config as $name => $config) {
			$listener_class = \is_array($config) ? ($config['expiry_listener'] ?? null) : null;

			if (null === $listener_class) {
				continue;
			}

			$store    = $resolve((string) $name);
			$provider = $store->getProvider();

			// Skip drivers that cannot enumerate expired entries.
			if (!$provider->capabilities()->expiryCallbacks) {
				continue;
			}

			$expired = $provider->getExpiredEntries(100);

			foreach ($expired as $entry) {
				try {
					/** @var StoreEntryExpiryListenerInterface $listener_class */
					$listener_class::onCacheEntryExpiry($entry->key, $entry->value, (string) $name);
				} finally {
					// Always delete, even if the listener threw.
					$provider->delete($entry->key);
				}
			}
		}
	}
}
