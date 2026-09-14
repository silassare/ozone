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

namespace OZONE\Core\Stores\Drivers;

use Override;
use OZONE\Core\Hooks\Events\EndRequestHook;
use OZONE\Core\Stores\Interfaces\StoreDriverInterface;
use OZONE\Core\Stores\StoreCapabilities;
use OZONE\Core\Stores\StoreEntry;

/**
 * Class MemoryStore.
 *
 * In-memory cache backed by a static PHP array. Data is lost when the process
 * ends. Suitable for per-request memoization.
 */
class MemoryStore implements StoreDriverInterface
{
	protected const CACHE_VALUE_PROP  = 'value';

	protected const CACHE_EXPIRE_PROP = 'expire';

	protected string $namespace;

	protected static array $cache_data = [];

	/**
	 * MemoryStore constructor.
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
	 * Empties every runtime namespace.
	 *
	 * `CacheRegistry::runtime()` is documented as per-request memoization, and it is per *process*:
	 * a persistent worker would answer the second request from the first one's memoized values.
	 *
	 * @internal on {@see EndRequestHook}
	 */
	public static function release(): void
	{
		self::$cache_data = [];
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function capabilities(): StoreCapabilities
	{
		return new StoreCapabilities(
			perEntryTTL: true,
			persistent: false,
			expiryCallbacks: false,
			atomic: false,
			// In-memory, gone with the process.
			durable: false,
		);
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function get(string $key): ?StoreEntry
	{
		if (isset(self::$cache_data[$this->namespace][$key])) {
			$item   = self::$cache_data[$this->namespace][$key];
			$expire = $item[self::CACHE_EXPIRE_PROP] ?? null;

			if (null === $expire || $expire > \microtime(true)) {
				return new StoreEntry($key, $item[self::CACHE_VALUE_PROP], $expire);
			}

			$this->delete($key);
		}

		return null;
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
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
	#[Override]
	public function set(StoreEntry $entry): bool
	{
		self::$cache_data[$this->namespace][$entry->key] = [
			self::CACHE_VALUE_PROP  => $entry->value,
			self::CACHE_EXPIRE_PROP => $entry->expiresAt,
		];

		return $this->save();
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function delete(string $key): bool
	{
		unset(self::$cache_data[$this->namespace][$key]);

		return $this->save();
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
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
	#[Override]
	public function clear(): bool
	{
		self::$cache_data[$this->namespace] = [];

		return $this->save();
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function increment(string $key, float $factor = 1): bool
	{
		if (!isset(self::$cache_data[$this->namespace][$key])) {
			return false;
		}

		$val = self::$cache_data[$this->namespace][$key][self::CACHE_VALUE_PROP] ?? 0;

		self::$cache_data[$this->namespace][$key][self::CACHE_VALUE_PROP] = $val + $factor;

		return $this->save();
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function decrement(string $key, float $factor = 1): bool
	{
		if (!isset(self::$cache_data[$this->namespace][$key][self::CACHE_VALUE_PROP])) {
			return false;
		}

		$val = self::$cache_data[$this->namespace][$key][self::CACHE_VALUE_PROP] ?? 0;

		self::$cache_data[$this->namespace][$key][self::CACHE_VALUE_PROP] = $val - $factor;

		return $this->save();
	}

	/**
	 * {@inheritDoc}
	 *
	 * MemoryStore does not support server-side expiry scanning; returns an empty array.
	 */
	#[Override]
	public function getExpiredEntries(int $limit = 100): array
	{
		return [];
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public static function fromConfig(string $namespace, array $options = []): static
	{
		return new static($namespace);
	}

	/**
	 * Called when the cache is being initialized.
	 *
	 * @return array
	 */
	protected function load(): array
	{
		return [];
	}

	/**
	 * Called after each write operation.
	 *
	 * @return bool
	 */
	protected function save(): bool
	{
		return true;
	}
}
