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

/**
 * Class StoreEntry.
 *
 * Immutable cache entry value object.
 */
final readonly class StoreEntry
{
	/**
	 * StoreEntry constructor.
	 *
	 * @param string     $key       the cache key
	 * @param mixed      $value     the cached value
	 * @param null|float $expiresAt absolute expiry timestamp as microtime float, or null for no expiry
	 * @param null|int   $version   the write that put this value there ({@see self::nextVersion()});
	 *                              null where the driver keeps none, or on an entry not read from one
	 * @param bool       $removed   this key was removed: what a client reading changes is told, since
	 *                              a value of null is a value a store may legitimately hold
	 */
	public function __construct(
		public string $key,
		public mixed $value,
		public ?float $expiresAt = null,
		public ?int $version = null,
		public bool $removed = false,
	) {}

	/**
	 * The version a write happening now takes: microseconds since the epoch.
	 *
	 * **The time and not a counter**, which is one choice answering two questions. A conditional
	 * write needs a token to compare ("only if it is still the one I read") and a client reading
	 * changes needs a cursor ("what has moved since"); the microsecond an entry was written is
	 * both, so there is one column, one fact and nothing to keep in step. A counter would need its
	 * own allocation, and a sequence allocated apart from the write it belongs to can be read by a
	 * client between the two -- a change the client then never sees, which is the one failure a
	 * sync must not have.
	 *
	 * Monotonic as far as the clock is: two writes to **one** key cannot share a version, since the
	 * second reads what the first wrote and a read of a store costs far more than a microsecond.
	 * Two writes to two keys can, which is why a client reading changes asks from slightly before
	 * its cursor and takes a repeat rather than risking a gap -- a repeat costs nothing, the value
	 * and its version being the same on both sides.
	 */
	public static function nextVersion(): int
	{
		return (int) \round(\microtime(true) * 1_000_000);
	}

	/**
	 * Creates an entry with a relative TTL in seconds from now.
	 *
	 * @param string $key        the cache key
	 * @param mixed  $value      the cached value
	 * @param float  $ttlSeconds TTL in seconds
	 */
	public static function forTTL(string $key, mixed $value, float $ttlSeconds): static
	{
		return new self($key, $value, \microtime(true) + $ttlSeconds);
	}

	/**
	 * Checks if this entry has expired.
	 */
	public function isExpired(): bool
	{
		return null !== $this->expiresAt && \microtime(true) > $this->expiresAt;
	}
}
