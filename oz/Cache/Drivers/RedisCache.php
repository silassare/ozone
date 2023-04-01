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

namespace OZONE\OZ\Cache\Drivers;

use OZONE\OZ\Cache\CacheItem;
use OZONE\OZ\Cache\Interfaces\CacheProviderInterface;

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

	public function get(string $key): ?CacheItem
	{
		return null;
	}

	public function getMultiple(array $keys): array
	{
		return [];
	}

	public function set(CacheItem $item): bool
	{
		return false;
	}

	public function increment(string $key, float $factor = 1): bool
	{
		return false;
	}

	public function decrement(string $key, float $factor = 1): bool
	{
		return false;
	}

	public function delete(string $key): bool
	{
		return false;
	}

	public function deleteMultiple(array $keys): bool
	{
		return false;
	}

	public function clear(): bool
	{
		return false;
	}

	public static function getSharedInstance(?string $namespace = null): CacheProviderInterface
	{
		return new self($namespace ?? '_');
	}
}
