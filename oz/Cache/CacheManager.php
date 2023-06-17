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

use DateInterval;
use InvalidArgumentException;
use OZONE\Core\App\Settings;
use OZONE\Core\Cache\Interfaces\CacheProviderInterface;
use OZONE\Core\Exceptions\RuntimeException;

/**
 * Class CacheManager.
 */
final class CacheManager
{
	/**
	 * @var \OZONE\Core\Cache\CacheManager[]
	 */
	private static array $sharedCM = [];

	private CacheProviderInterface $cache;

	/**
	 * CacheManager constructor.
	 *
	 * @param \OZONE\Core\Cache\Interfaces\CacheProviderInterface $cache
	 */
	private function __construct(CacheProviderInterface $cache)
	{
		$this->cache = $cache;
	}

	/**
	 * CacheManager destructor.
	 */
	public function __destruct()
	{
		unset($this->cache);
	}

	/**
	 * Gets value of a given key.
	 *
	 * @param string     $key
	 * @param null|mixed $default
	 *
	 * @return mixed
	 */
	public function get(string $key, mixed $default = null): mixed
	{
		$item = $this->getItem($key);

		if (!$item->expired()) {
			return $item->get() ?? $default;
		}

		return $default;
	}

	/**
	 * Sets value for a given key.
	 *
	 * @param string                  $key
	 * @param mixed                   $value
	 * @param null|DateInterval|float $lifetime
	 *
	 * @return bool
	 */
	public function set(string $key, mixed $value, float|DateInterval|null $lifetime = null): bool
	{
		$item = $this->getItem($key);

		$item->set($value)
			->expiresAfter($lifetime);

		return $this->cache->set($item);
	}

	/**
	 * Set cache item.
	 *
	 * @param \OZONE\Core\Cache\CacheItem $item
	 *
	 * @return bool
	 */
	public function setItem(CacheItem $item): bool
	{
		return $this->cache->set($item);
	}

	/**
	 * Increment value of a given key.
	 *
	 * @param string $key
	 * @param float  $factor
	 *
	 * @return bool
	 */
	public function increment(string $key, float $factor = 1): bool
	{
		return $this->has($key) && $this->cache->increment($key, $factor);
	}

	/**
	 * Decrement value of a given key.
	 *
	 * @param string $key
	 * @param float  $factor
	 *
	 * @return bool
	 */
	public function decrement(string $key, float $factor = 1): bool
	{
		return $this->has($key) && $this->cache->decrement($key, $factor);
	}

	/**
	 * Get value if found and not expired or set value using factory.
	 *
	 * @param string                  $key
	 * @param callable                $factory
	 * @param null|DateInterval|float $lifetime
	 *
	 * @return \OZONE\Core\Cache\CacheItem
	 */
	public function factory(string $key, callable $factory, float|DateInterval|null $lifetime = null): CacheItem
	{
		$item = $this->getItem($key);

		if ($item->expired()) {
			$value = $factory();
			$item->set($value)
				->expiresAfter($lifetime);
		}

		return $item;
	}

	/**
	 * Gets a cache item entry.
	 *
	 * @param string $key
	 *
	 * @return \OZONE\Core\Cache\CacheItem
	 */
	public function getItem(string $key): CacheItem
	{
		self::assertValidKey($key);

		$item = $this->cache->get($key);

		if ($item instanceof CacheItem) {
			return $item;
		}

		return self::notFound($key);
	}

	/**
	 * Gets a list of cache entry.
	 *
	 * @param string[] $keys
	 *
	 * @return \OZONE\Core\Cache\CacheItem[]
	 */
	public function getItems(array $keys = []): array
	{
		foreach ($keys as $key) {
			self::assertValidKey($key);
		}

		$items = $this->cache->getMultiple($keys);

		foreach ($keys as $key) {
			if (!isset($items[$key])) {
				$items[$key] = self::notFound($key);
			}
		}

		return $items;
	}

	/**
	 * Checks if a cache entry with a given key exists.
	 *
	 * @param string $key
	 *
	 * @return bool
	 */
	public function has(string $key): bool
	{
		self::assertValidKey($key);

		return null !== $this->cache->get($key);
	}

	/**
	 * Clears the cache.
	 *
	 * @return bool
	 */
	public function clear(): bool
	{
		return $this->cache->clear();
	}

	/**
	 * Deletes the cache entry with given key.
	 *
	 * @param string $key
	 *
	 * @return bool
	 */
	public function delete(string $key): bool
	{
		self::assertValidKey($key);

		return $this->cache->delete($key);
	}

	/**
	 * Deletes a list of cache entry with a given key list.
	 *
	 * @param string[] $keys
	 *
	 * @return bool
	 */
	public function deleteItems(array $keys): bool
	{
		foreach ($keys as $key) {
			self::assertValidKey($key);
		}

		return $this->cache->deleteMultiple($keys);
	}

	/**
	 * Gets shared runtime cache.
	 *
	 * @param null|string $namespace
	 *
	 * @return \OZONE\Core\Cache\CacheManager
	 */
	public static function runtime(?string $namespace = null): self
	{
		$class_fqn = Settings::get('oz.cache', 'OZ_RUNTIME_CACHE_PROVIDER');

		return self::sharedInstance($class_fqn, $namespace);
	}

	/**
	 * Gets shared persistent cache.
	 *
	 * @param null|string $namespace
	 *
	 * @return \OZONE\Core\Cache\CacheManager
	 */
	public static function persistent(?string $namespace = null): self
	{
		$class_fqn = Settings::get('oz.cache', 'OZ_PERSISTENT_CACHE_PROVIDER');

		return self::sharedInstance($class_fqn, $namespace);
	}

	/**
	 * Gets shared instance of a given cache provider and namespace.
	 *
	 * @param string      $class_fqn
	 * @param null|string $namespace
	 *
	 * @return \OZONE\Core\Cache\CacheManager
	 */
	private static function sharedInstance(string $class_fqn, ?string $namespace = null): self
	{
		$key = $class_fqn . ($namespace ?? '');

		if (!isset(self::$sharedCM[$key])) {
			if (!\is_subclass_of($class_fqn, CacheProviderInterface::class)) {
				throw new RuntimeException(\sprintf(
					'Cache provider "%s" should implements "%s".',
					$class_fqn,
					CacheProviderInterface::class
				));
			}

			/* @var CacheProviderInterface $class_fqn */
			self::$sharedCM[$key] = new self($class_fqn::getSharedInstance($namespace));
		}

		return self::$sharedCM[$key];
	}

	/**
	 * @param $key
	 */
	private static function assertValidKey($key): void
	{
		if (!\is_string($key) || '' === $key) {
			throw new InvalidArgumentException('Cache key must be a non empty string.');
		}
	}

	/**
	 * @param string $key
	 *
	 * @return \OZONE\Core\Cache\CacheItem
	 */
	private static function notFound(string $key): CacheItem
	{
		return new CacheItem($key, null, 0);
	}
}
