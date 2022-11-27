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
 * Class PHPCache.
 */
class PHPCache implements CacheProviderInterface
{
	public function __construct(protected string $namespace)
	{
	}

	/**
	 * @param string $key
	 *
	 * @return string
	 */
	public function getFilePath(string $key): string
	{
		$hash = \md5($this->namespace . '/' . $key);
		$dir1 = \substr($hash, 0, 2);
		$dir2 = \substr($hash, 2, 2);

		return OZ_CACHE_DIR . 'php_cache' . DS . $dir1 . DS . $dir2 . DS . $hash . '.cache';
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
		return true;
	}

	public function delete(string $key): bool
	{
		return true;
	}

	public function deleteMultiple(array $keys): bool
	{
		return true;
	}

	public function clear(): bool
	{
		return true;
	}

	public static function getSharedInstance(?string $namespace = null): self
	{
		return new self($namespace);
	}

	public function increment(string $key, float $factor = 1): bool
	{
		return true;
	}

	public function decrement(string $key, float $factor = 1): bool
	{
		return true;
	}
}
