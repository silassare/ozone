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

use InvalidArgumentException;
use OZONE\Core\Cache\Interfaces\CacheProviderInterface;

/**
 * Class CacheStore.
 *
 * The primary interface for components interacting with the cache.
 * Wraps a {@see CacheProviderInterface} driver with a convenient high-level API.
 *
 * Obtain instances via {@see CacheRegistry}:
 *   - `CacheRegistry::runtime($ns)` — per-request, in-memory
 *   - `CacheRegistry::persistent($ns)` — persistent (configured driver)
 *   - `CacheRegistry::store('oz:form:sessions')` — named store from `oz.cache.stores` settings
 */
final class CacheStore
{
	/**
	 * CacheStore constructor.
	 *
	 * @param string                 $name     namespace / store name
	 * @param CacheProviderInterface $provider the underlying driver
	 */
	public function __construct(
		private readonly string $name,
		private readonly CacheProviderInterface $provider,
	) {}

	/**
	 * Returns the store name (namespace).
	 *
	 * @return string
	 */
	public function getName(): string
	{
		return $this->name;
	}

	/**
	 * Returns the underlying cache provider driver.
	 *
	 * Use this when you need direct provider access, e.g. for garbage collection.
	 *
	 * @return CacheProviderInterface
	 */
	public function getProvider(): CacheProviderInterface
	{
		return $this->provider;
	}

	/**
	 * Returns the cached value for the given key, or `$default` when missing/expired.
	 *
	 * @param string $key
	 * @param mixed  $default
	 *
	 * @return mixed
	 */
	public function get(string $key, mixed $default = null): mixed
	{
		$entry = $this->provider->get($key);

		return null !== $entry ? $entry->value : $default;
	}

	/**
	 * Returns the raw cache entry for the given key, or null when missing/expired.
	 *
	 * Use this when you need access to expiry metadata alongside the value.
	 *
	 * @param string $key
	 *
	 * @return null|CacheEntry
	 */
	public function entry(string $key): ?CacheEntry
	{
		return $this->provider->get($key);
	}

	/**
	 * Stores a value with an optional TTL in seconds.
	 *
	 * @param string   $key
	 * @param mixed    $value
	 * @param null|int $ttl   TTL in seconds; null means no expiry
	 *
	 * @return bool
	 */
	public function set(string $key, mixed $value, ?int $ttl = null): bool
	{
		self::assertValidKey($key);

		$expires_at = null !== $ttl ? \microtime(true) + $ttl : null;

		return $this->provider->set(new CacheEntry($key, $value, $expires_at));
	}

	/**
	 * Checks whether a non-expired entry exists for the given key.
	 *
	 * @param string $key
	 *
	 * @return bool
	 */
	public function has(string $key): bool
	{
		return null !== $this->provider->get($key);
	}

	/**
	 * Deletes the entry for the given key.
	 *
	 * @param string $key
	 *
	 * @return bool
	 */
	public function delete(string $key): bool
	{
		return $this->provider->delete($key);
	}

	/**
	 * Deletes multiple entries.
	 *
	 * @param string[] $keys
	 *
	 * @return bool
	 */
	public function deleteMultiple(array $keys): bool
	{
		return $this->provider->deleteMultiple($keys);
	}

	/**
	 * Clears all entries in this store's namespace.
	 *
	 * @return bool
	 */
	public function clear(): bool
	{
		return $this->provider->clear();
	}

	/**
	 * Increments the numeric value stored at `$key` by `$by`.
	 *
	 * Returns false when the key does not exist.
	 *
	 * @param string    $key
	 * @param float|int $by
	 *
	 * @return bool
	 */
	public function increment(string $key, float|int $by = 1): bool
	{
		return $this->provider->increment($key, (float) $by);
	}

	/**
	 * Decrements the numeric value stored at `$key` by `$by`.
	 *
	 * Returns false when the key does not exist.
	 *
	 * @param string    $key
	 * @param float|int $by
	 *
	 * @return bool
	 */
	public function decrement(string $key, float|int $by = 1): bool
	{
		return $this->provider->decrement($key, (float) $by);
	}

	/**
	 * Returns the cached value if present and not expired; otherwise calls
	 * `$factory`, stores the result with the given TTL, and returns it.
	 *
	 * @param string   $key
	 * @param callable $factory called with no arguments when cache misses
	 * @param null|int $ttl     TTL in seconds; null means no expiry
	 *
	 * @return mixed
	 */
	public function remember(string $key, callable $factory, ?int $ttl = null): mixed
	{
		self::assertValidKey($key);

		$entry = $this->provider->get($key);

		if (null !== $entry) {
			return $entry->value;
		}

		$value = $factory();
		$this->set($key, $value, $ttl);

		return $value;
	}

	/**
	 * Validates a cache key.
	 *
	 * @param string $key
	 */
	private static function assertValidKey(string $key): void
	{
		if ('' === \trim($key)) {
			throw new InvalidArgumentException('Cache key must be a non-empty, non-whitespace string.');
		}
	}
}
