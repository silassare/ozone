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

namespace OZONE\Core\Stores\Interfaces;

use OZONE\Core\Stores\StoreCapabilities;
use OZONE\Core\Stores\StoreEntry;

/**
 * Interface StoreDriverInterface.
 */
interface StoreDriverInterface
{
	/**
	 * Returns the capabilities of this cache driver.
	 *
	 * @return StoreCapabilities
	 */
	public function capabilities(): StoreCapabilities;

	/**
	 * Gets a cache entry by key, or null when not found or expired.
	 *
	 * @param string $key
	 *
	 * @return null|StoreEntry
	 */
	public function get(string $key): ?StoreEntry;

	/**
	 * Gets multiple cache entries. Missing or expired keys are omitted from the result.
	 *
	 * @param string[] $keys
	 *
	 * @return StoreEntry[] keyed by cache key
	 */
	public function getMultiple(array $keys): array;

	/**
	 * Stores a cache entry.
	 *
	 * @param StoreEntry $entry
	 *
	 * @return bool
	 */
	public function set(StoreEntry $entry): bool;

	/**
	 * Adds to a number, atomically: concurrent adds, from requests or processes, are never lost.
	 * A whole number stays one when what is added is one.
	 *
	 * @param string     $key       the entry's key
	 * @param float|int  $by        what to add (negative: subtract)
	 * @param bool       $create    a missing (or expired) entry is created at `$by`, else nothing is
	 *                              done and null answered
	 * @param null|float $expiresAt the expiry of an entry this creates; an existing one keeps its own
	 *
	 * @return null|float|int the new number; null when the entry is missing and not created
	 *
	 * @throws \OZONE\Core\Exceptions\RuntimeException when the entry holds something else than a number
	 */
	public function add(string $key, float|int $by, bool $create = false, ?float $expiresAt = null): float|int|null;

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
	 * @return StoreEntry[] keyed by original cache key
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
