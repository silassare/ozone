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

use OZONE\Core\App\Settings;
use OZONE\Core\Exceptions\RuntimeException;
use OZONE\Core\Stores\Interfaces\StoreDriverInterface;

/**
 * Class CacheRegistry.
 *
 * Central registry for cache stores.
 *
 * Three access patterns:
 *
 *   // 1. Named store — configured in `oz.stores.cache`, overridable by consuming projects:
 *   CacheRegistry::store('oz:form:sessions')
 *
 *   // 2. Default runtime (in-memory, per-request):
 *   CacheRegistry::runtime(__METHOD__)
 *
 *   // 3. Default persistent (survives restart):
 *   CacheRegistry::persistent(self::class)
 *
 * Named stores (`oz.stores.cache` group, separate from `oz.stores`) map a store
 * name to a driver config array:
 *
 *   return [
 *       'oz:form:sessions' => [
 *           'driver'          => DbStore::class,
 *           'options'         => [],
 *           'expiry_listener' => FormSessionStore::class,
 *       ],
 *   ];
 *
 * Consuming projects override individual entries in `app/settings/oz.stores.cache.php`.
 * Because the settings merge strategy uses `array_replace_recursive` for associative
 * arrays, overriding a single store does not affect others.
 */
final class CacheRegistry
{
	/** @var KeyValueStore[] */
	private static array $stores = [];

	/**
	 * Returns a named cache store configured in `oz.stores.cache`.
	 *
	 * Falls back to the default persistent driver when the store name is not
	 * present in the settings, and refuses a name that belongs to `oz.stores.state`.
	 *
	 * @param string $name The store name (must match a key in `oz.stores.cache`).
	 *
	 * @return KeyValueStore
	 *
	 * @throws RuntimeException when the name is that of a state store
	 */
	public static function store(string $name): KeyValueStore
	{
		$config = Settings::load('oz.stores.cache');

		if (!isset($config[$name]) && \array_key_exists($name, Settings::load(StateRegistry::SETTINGS_GROUP))) {
			// Without this the split would be advisory: an unknown name falls back to the default
			// persistent driver, so a state store read through here would quietly get a second
			// provider, and clearing one would not clear the other.
			throw new RuntimeException(\sprintf(
				'"%s" is a state store, not a cache: read it with %s::store(). Losing one of its'
					. ' entries is visible to a user.',
				$name,
				StateRegistry::class
			));
		}

		$entry   = $config[$name] ?? [];
		$driver  = $entry['driver'] ?? Settings::get('oz.stores', 'OZ_CACHE_DEFAULT_PERSISTENT');
		$options = $entry['options'] ?? [];

		return self::resolve($driver, $name, $options);
	}

	/**
	 * Returns a cache store backed by the configured default runtime driver.
	 *
	 * Runtime stores are scoped to the current process and are suitable for
	 * per-request memoization.
	 *
	 * @param string $namespace Namespace for isolation (e.g. `__METHOD__` or `self::class`).
	 *
	 * @return KeyValueStore
	 */
	public static function runtime(string $namespace): KeyValueStore
	{
		$driver = Settings::get('oz.stores', 'OZ_CACHE_DEFAULT_RUNTIME');

		return self::resolve($driver, $namespace);
	}

	/**
	 * Returns a cache store backed by the configured default persistent driver.
	 *
	 * @param string $namespace namespace for isolation
	 *
	 * @return KeyValueStore
	 */
	public static function persistent(string $namespace): KeyValueStore
	{
		$driver = Settings::get('oz.stores', 'OZ_CACHE_DEFAULT_PERSISTENT');

		return self::resolve($driver, $namespace);
	}

	/**
	 * Resolves (or creates) a cached store instance for the given driver + namespace pair.
	 *
	 * @param string $driver    FQN of the driver class implementing StoreDriverInterface
	 * @param string $namespace the namespace / store name
	 * @param array  $options   driver-specific options forwarded to fromConfig()
	 *
	 * @return KeyValueStore
	 */
	private static function resolve(string $driver, string $namespace, array $options = []): KeyValueStore
	{
		$cache_key = $driver . ':' . $namespace;

		if (!isset(self::$stores[$cache_key])) {
			if (!\is_subclass_of($driver, StoreDriverInterface::class)) {
				throw new RuntimeException(\sprintf(
					'Cache driver "%s" must implement "%s".',
					$driver,
					StoreDriverInterface::class
				));
			}

			/** @var StoreDriverInterface $driver */
			self::$stores[$cache_key] = new KeyValueStore($namespace, $driver::fromConfig($namespace, $options));
		}

		return self::$stores[$cache_key];
	}
}
