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

use OZONE\Core\Cache\CacheCapabilities;
use OZONE\Core\Cache\CacheEntry;

/**
 * Interface CacheProviderInterface.
 */
interface CacheProviderInterface
{
	/**
	 * Returns the capabilities of this cache driver.
	 *
	 * @return CacheCapabilities
	 */
	public function capabilities(): CacheCapabilities;

	/**
	 * Gets a cache entry by key, or null when not found or expired.
	 *
	 * @param string $key
	 *
	 * @return null|CacheEntry
	 */
	public function get(string $key): ?CacheEntry;

	/**
	 * Gets multiple cache entries. Missing or expired keys are omitted from the result.
	 *
	 * @param string[] $keys
	 *
	 * @return CacheEntry[] keyed by cache key
	 */
	public function getMultiple(array $keys): array;

	/**
	 * Stores a cache entry.
	 *
	 * @param CacheEntry $entry
	 *
	 * @return bool
	 */
	public function set(CacheEntry $entry): bool;

	/**
	 * Increments the value of a given key.
	 *
	 * Returns false when the key does not exist.
	 *
	 * @param string $key
	 * @param float  $factor
	 *
	 * @return bool
	 */
	public function increment(string $key, float $factor = 1): bool;

	/**
	 * Decrements the value of a given key.
	 *
	 * Returns false when the key does not exist.
	 *
	 * @param string $key
	 * @param float  $factor
	 *
	 * @return bool
	 */
	public function decrement(string $key, float $factor = 1): bool;

	/**
	 * Deletes a cache entry by key.
	 *
	 * @param string $key
	 *
	 * @return bool
	 */
	public function delete(string $key): bool;

	/**
	 * Deletes multiple cache entries.
	 *
	 * @param string[] $keys
	 *
	 * @return bool
	 */
	public function deleteMultiple(array $keys): bool;

	/**
	 * Clears all entries in this driver's namespace.
	 *
	 * @return bool
	 */
	public function clear(): bool;

	/**
	 * Returns expired entries for garbage collection.
	 *
	 * Drivers that do not support server-side expiry scanning should return an empty array.
	 * The caller is responsible for deleting the returned entries after processing.
	 *
	 * @param int $limit maximum number of expired entries to return per call
	 *
	 * @return CacheEntry[] keyed by original cache key
	 */
	public function getExpiredEntries(int $limit = 100): array;

	/**
	 * Creates a new driver instance for the given namespace and options.
	 *
	 * Drivers are responsible for reading their own config from `$options`;
	 * unknown keys are ignored.
	 *
	 * @param string $namespace the namespace (store name) for this driver instance
	 * @param array  $options   driver-specific configuration options
	 *
	 * @return static
	 */
	public static function fromConfig(string $namespace, array $options = []): static;
}
