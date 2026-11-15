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

use OZONE\Core\Exceptions\RuntimeException;
use OZONE\Core\Stores\StoreCapabilities;
use OZONE\Core\Stores\StoreEntry;
use OZONE\Core\Stores\StoreWrite;

/**
 * Interface StoreDriverInterface.
 */
interface StoreDriverInterface
{
	/**
	 * Returns the capabilities of this cache driver.
	 */
	public function capabilities(): StoreCapabilities;

	/**
	 * Gets a cache entry by key, or null when not found or expired.
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
	 */
	public function set(StoreEntry $entry): bool;

	/**
	 * Stores an entry only where its version is still the one the caller read.
	 *
	 * What makes a store writable by two sides at once: a client that read version V and writes
	 * back says so, and a write arriving after somebody else's is refused rather than silently
	 * losing what it never saw. The answer carries the entry either way ({@see StoreWrite}).
	 *
	 * Drivers declaring `versioned: false` are not asked: {@see \OZONE\Core\Stores\KeyValueStore}
	 * refuses before reaching them.
	 *
	 * @param StoreEntry $entry           what to write; its own `version` is ignored, the store
	 *                                    gives the new one
	 * @param null|int   $expectedVersion the version the caller read; null: only where the key
	 *                                    holds nothing, which is how a client creates one
	 */
	public function setIf(StoreEntry $entry, ?int $expectedVersion): StoreWrite;

	/**
	 * Deletes an entry only where its version is still the one the caller read.
	 *
	 * A removal is a write like any other: one sent from a device that had not seen the latest
	 * value must be refused, not applied over it.
	 */
	public function deleteIf(string $key, int $expectedVersion): StoreWrite;

	/**
	 * The entries written after that version, oldest first: what a client reads changes with.
	 *
	 * A **removal is included**, as an entry whose `removed` says so, which is why a driver keeps
	 * them only where its store asked for it (`changes` in the store's options). A client asks from
	 * slightly before the version it last saw and takes a repeat rather than risk a gap: two keys
	 * written in one microsecond share a version, and the value and its version being the same on
	 * both sides make a repeat cost nothing.
	 *
	 * Drivers declaring `changes: false` are not asked.
	 *
	 * @param int $version the version a client last saw; 0 for everything the store holds
	 * @param int $limit   at most this many entries, so a store of any size is read in pages
	 *
	 * @return StoreEntry[] oldest first, each with its version
	 */
	public function changedSince(int $version, int $limit = 100): array;

	/**
	 * Forgets removals older than that version, and answers how many were forgotten.
	 *
	 * What keeps a store that remembers removals from growing without end. The boundary is the
	 * caller's: a version **is** a microsecond of the clock, so "older than a month" is a number
	 * anybody can work out, and a client whose cursor is older than the retention has to read the
	 * store whole rather than be told a removal that is no longer kept.
	 *
	 * Drivers that keep no removals answer 0.
	 */
	public function pruneRemoved(int $beforeVersion): int;

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
	 * @throws RuntimeException when the entry holds something else than a number
	 */
	public function add(string $key, float|int $by, bool $create = false, ?float $expiresAt = null): float|int|null;

	/**
	 * Deletes a cache entry by key.
	 */
	public function delete(string $key): bool;

	/**
	 * Deletes multiple cache entries.
	 *
	 * @param string[] $keys
	 */
	public function deleteMultiple(array $keys): bool;

	/**
	 * Clears all entries in this driver's namespace.
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
	 */
	public static function fromConfig(string $namespace, array $options = []): static;
}
