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

use OZONE\Core\App\Settings;
use OZONE\Core\Cache\Interfaces\CacheProviderInterface;
use OZONE\Core\Exceptions\RuntimeException;

/**
 * Class CacheRegistry.
 *
 * Central registry for cache stores.
 *
 * Three access patterns:
 *
 *   // 1. Named store — configured in `oz.cache.stores`, overridable by consuming projects:
 *   CacheRegistry::store('oz:form:sessions')
 *
 *   // 2. Default runtime (in-memory, per-request):
 *   CacheRegistry::runtime(__METHOD__)
 *
 *   // 3. Default persistent (survives restart):
 *   CacheRegistry::persistent(self::class)
 *
 * Named stores (`oz.cache.stores` group, separate from `oz.cache`) map a store
 * name to a driver config array:
 *
 *   return [
 *       'oz:form:sessions' => [
 *           'driver'          => DbCache::class,
 *           'options'         => [],
 *           'expiry_listener' => ResumableFormService::class,
 *       ],
 *   ];
 *
 * Consuming projects override individual entries in `app/settings/oz.cache.stores.php`.
 * Because the settings merge strategy uses `array_replace_recursive` for associative
 * arrays, overriding a single store does not affect others.
 */
final class CacheRegistry
{
	/** @var CacheStore[] */
	private static array $stores = [];

	/**
	 * Returns a named cache store configured in `oz.cache.stores`.
	 *
	 * Falls back to the default persistent driver when the store name is not
	 * present in the settings.
	 *
	 * @param string $name The store name (must match a key in `oz.cache.stores`).
	 *
	 * @return CacheStore
	 */
	public static function store(string $name): CacheStore
	{
		$config  = Settings::load('oz.cache.stores');
		$entry   = $config[$name] ?? [];
		$driver  = $entry['driver'] ?? Settings::get('oz.cache', 'OZ_CACHE_DEFAULT_PERSISTENT');
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
	 * @return CacheStore
	 */
	public static function runtime(string $namespace): CacheStore
	{
		$driver = Settings::get('oz.cache', 'OZ_CACHE_DEFAULT_RUNTIME');

		return self::resolve($driver, $namespace);
	}

	/**
	 * Returns a cache store backed by the configured default persistent driver.
	 *
	 * @param string $namespace namespace for isolation
	 *
	 * @return CacheStore
	 */
	public static function persistent(string $namespace): CacheStore
	{
		$driver = Settings::get('oz.cache', 'OZ_CACHE_DEFAULT_PERSISTENT');

		return self::resolve($driver, $namespace);
	}

	/**
	 * Resolves (or creates) a cached store instance for the given driver + namespace pair.
	 *
	 * @param string $driver    FQN of the driver class implementing CacheProviderInterface
	 * @param string $namespace the namespace / store name
	 * @param array  $options   driver-specific options forwarded to fromConfig()
	 *
	 * @return CacheStore
	 */
	private static function resolve(string $driver, string $namespace, array $options = []): CacheStore
	{
		$cache_key = $driver . ':' . $namespace;

		if (!isset(self::$stores[$cache_key])) {
			if (!\is_subclass_of($driver, CacheProviderInterface::class)) {
				throw new RuntimeException(\sprintf(
					'Cache driver "%s" must implement "%s".',
					$driver,
					CacheProviderInterface::class
				));
			}

			/** @var CacheProviderInterface $driver */
			self::$stores[$cache_key] = new CacheStore($namespace, $driver::fromConfig($namespace, $options));
		}

		return self::$stores[$cache_key];
	}
}
