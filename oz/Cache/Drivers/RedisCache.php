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

namespace OZONE\Core\Cache\Drivers;

use OZONE\Core\Cache\CacheItem;
use OZONE\Core\Cache\Interfaces\CacheProviderInterface;

/**
 * Class RedisCache.
 */
class RedisCache implements CacheProviderInterface
{
	/**
	 * RedisCache constructor.
	 *
	 * TODO: implement this class
	 *
	 * @param string $namespace
	 */
	public function __construct(string $namespace)
	{
	}

	/**
	 * {@inheritDoc}
	 */
	public function get(string $key): ?CacheItem
	{
		return null;
	}

	/**
	 * {@inheritDoc}
	 */
	public function getMultiple(array $keys): array
	{
		return [];
	}

	/**
	 * {@inheritDoc}
	 */
	public function set(CacheItem $item): bool
	{
		return false;
	}

	/**
	 * {@inheritDoc}
	 */
	public function increment(string $key, float $factor = 1): bool
	{
		return false;
	}

	/**
	 * {@inheritDoc}
	 */
	public function decrement(string $key, float $factor = 1): bool
	{
		return false;
	}

	/**
	 * {@inheritDoc}
	 */
	public function delete(string $key): bool
	{
		return false;
	}

	/**
	 * {@inheritDoc}
	 */
	public function deleteMultiple(array $keys): bool
	{
		return false;
	}

	/**
	 * {@inheritDoc}
	 */
	public function clear(): bool
	{
		return false;
	}

	/**
	 * {@inheritDoc}
	 */
	public static function getSharedInstance(?string $namespace = null): CacheProviderInterface
	{
		return new self($namespace ?? '_');
	}
}
