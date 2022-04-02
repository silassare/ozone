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

namespace OZONE\OZ\Cache\Interfaces;

use OZONE\OZ\Cache\CacheItem;

/**
 * Interface CacheProviderInterface.
 */
interface CacheProviderInterface
{
	/**
	 * CacheProviderInterface constructor.
	 *
	 * @param string $namespace
	 */
	public function __construct(string $namespace);

	/**
	 * Gets a cache entry.
	 *
	 * @param string $key
	 *
	 * @return null|\OZONE\OZ\Cache\CacheItem
	 */
	public function get(string $key): ?CacheItem;

	/**
	 * Gets a list of cache entry.
	 *
	 * @param string[] $keys
	 *
	 * @return \OZONE\OZ\Cache\CacheItem[]
	 */
	public function getMultiple(array $keys): array;

	/**
	 * Add or update a new cache entry.
	 *
	 * @param \OZONE\OZ\Cache\CacheItem $item
	 *
	 * @return bool
	 */
	public function set(CacheItem $item): bool;

	/**
	 * Increment value of a given key.
	 *
	 * @param string $key
	 * @param float  $factor
	 *
	 * @return bool
	 */
	public function increment(string $key, float $factor = 1): bool;

	/**
	 * Decrement value of a given key.
	 *
	 * @param string $key
	 * @param float  $factor
	 *
	 * @return bool
	 */
	public function decrement(string $key, float $factor = 1): bool;

	/**
	 * Delete a cache entry with a given key.
	 *
	 * @param string $key
	 *
	 * @return bool
	 */
	public function delete(string $key): bool;

	/**
	 * Deletes a list of cache entry with a given key list.
	 *
	 * @param string[] $keys
	 *
	 * @return bool
	 */
	public function deleteMultiple(array $keys): bool;

	/**
	 * Clear the cache.
	 *
	 * @return bool
	 */
	public function clear(): bool;

	/**
	 * Gets shared instance.
	 *
	 * @param null|string $namespace
	 *
	 * @return static
	 */
	public static function getSharedInstance(?string $namespace = null): self;
}
