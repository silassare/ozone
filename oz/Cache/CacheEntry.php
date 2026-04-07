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

/**
 * Class CacheEntry.
 *
 * Immutable cache entry value object.
 */
final class CacheEntry
{
	/**
	 * CacheEntry constructor.
	 *
	 * @param string     $key       the cache key
	 * @param mixed      $value     the cached value
	 * @param null|float $expiresAt absolute expiry timestamp as microtime float, or null for no expiry
	 */
	public function __construct(
		public readonly string $key,
		public readonly mixed $value,
		public readonly ?float $expiresAt = null,
	) {}

	/**
	 * Creates an entry with a relative TTL in seconds from now.
	 *
	 * @param string $key        the cache key
	 * @param mixed  $value      the cached value
	 * @param float  $ttlSeconds TTL in seconds
	 *
	 * @return static
	 */
	public static function forTTL(string $key, mixed $value, float $ttlSeconds): static
	{
		return new self($key, $value, \microtime(true) + $ttlSeconds);
	}

	/**
	 * Checks if this entry has expired.
	 *
	 * @return bool
	 */
	public function isExpired(): bool
	{
		return null !== $this->expiresAt && \microtime(true) > $this->expiresAt;
	}
}
