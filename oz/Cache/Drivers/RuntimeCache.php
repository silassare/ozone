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
 * Class RuntimeCache.
 */
class RuntimeCache implements CacheProviderInterface
{
	public const CACHE_VALUE_PROP = 'value';

	public const CACHE_EXPIRE_PROP = 'expire';

	protected string $namespace;

	protected static array $cache_data = [];

	/**
	 * RuntimeCache constructor.
	 *
	 * @param null|string $namespace
	 */
	public function __construct(?string $namespace = null)
	{
		$this->namespace = empty($namespace) ? '_' : $namespace;

		if (!isset(self::$cache_data[$this->namespace])) {
			self::$cache_data[$this->namespace] = $this->load();
		}
	}

	/**
	 * {@inheritDoc}
	 */
	public function get(string $key): ?CacheItem
	{
		if (isset(self::$cache_data[$this->namespace][$key])) {
			$item   = self::$cache_data[$this->namespace][$key];
			$expire = $item[self::CACHE_EXPIRE_PROP] ?? null;

			if (null === $expire || $expire > \microtime(true)) {
				return new CacheItem($key, $item[self::CACHE_VALUE_PROP], (float) $expire);
			}

			$this->delete($key);
		}

		return null;
	}

	/**
	 * {@inheritDoc}
	 */
	public function getMultiple(array $keys): array
	{
		$items = [];

		foreach ($keys as $key) {
			$item = $this->get($key);

			if (null !== $item) {
				$items[$key] = $item;
			}
		}

		return $items;
	}

	/**
	 * {@inheritDoc}
	 */
	public function set(CacheItem $item): bool
	{
		self::$cache_data[$this->namespace][$item->getKey()] = [
			self::CACHE_VALUE_PROP  => $item->get(),
			self::CACHE_EXPIRE_PROP => $item->getExpire(),
		];

		return $this->save();
	}

	/**
	 * {@inheritDoc}
	 */
	public function delete(string $key): bool
	{
		unset(self::$cache_data[$this->namespace][$key]);

		return $this->save();
	}

	/**
	 * {@inheritDoc}
	 */
	public function deleteMultiple(array $keys): bool
	{
		foreach ($keys as $key) {
			unset(self::$cache_data[$this->namespace][$key]);
		}

		return $this->save();
	}

	/**
	 * {@inheritDoc}
	 */
	public function clear(): bool
	{
		self::$cache_data[$this->namespace] = [];

		return $this->save();
	}

	/**
	 * {@inheritDoc}
	 */
	public function increment(string $key, float $factor = 1): bool
	{
		if (!isset(self::$cache_data[$this->namespace][$key])) {
			return false;
		}

		self::$cache_data[$this->namespace][$key][self::CACHE_VALUE_PROP] += $factor;

		return $this->save();
	}

	/**
	 * {@inheritDoc}
	 */
	public function decrement(string $key, float $factor = 1): bool
	{
		if (!isset(self::$cache_data[$this->namespace][$key])) {
			return false;
		}

		self::$cache_data[$this->namespace][$key][self::CACHE_VALUE_PROP] -= $factor;

		return $this->save();
	}

	/**
	 * {@inheritDoc}
	 */
	public static function getSharedInstance(?string $namespace = null): self
	{
		return new self($namespace);
	}

	/**
	 * This method is called when the cache is initialized.
	 *
	 * @return array
	 */
	protected function load(): array
	{
		return [];
	}

	/**
	 * This method is called after each cache operation.
	 *
	 * @return bool
	 */
	protected function save(): bool
	{
		return true;
	}
}
