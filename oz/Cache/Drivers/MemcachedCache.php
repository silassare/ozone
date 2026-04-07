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

use Memcached;
use Override;
use OZONE\Core\Cache\CacheCapabilities;
use OZONE\Core\Cache\CacheEntry;
use OZONE\Core\Cache\Interfaces\CacheProviderInterface;
use OZONE\Core\Utils\Hasher;
use RuntimeException;

/** @noinspection ClassConstantCanBeUsedInspection */
if (!\class_exists('\Memcached')) {
	throw new RuntimeException('Memcached extension is not installed.');
}

/**
 * Class MemcachedCache.
 */
final class MemcachedCache implements CacheProviderInterface
{
	/**
	 * MemcachedCache constructor.
	 *
	 * @param Memcached $memcached
	 */
	private function __construct(private Memcached $memcached) {}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function capabilities(): CacheCapabilities
	{
		return new CacheCapabilities(
			perEntryTTL: true,
			persistent: true,
			expiryCallbacks: false,
			atomic: true,
		);
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function get(string $key): ?CacheEntry
	{
		$value = $this->memcached->get($key);

		if (Memcached::RES_NOTFOUND === $this->memcached->getResultCode()) {
			return null;
		}

		$expire = $this->memcached->get($key . ':expire');

		if (Memcached::RES_NOTFOUND === $this->memcached->getResultCode()) {
			return new CacheEntry($key, $value);
		}

		if ($expire > \microtime(true)) {
			return new CacheEntry($key, $value, (float) $expire);
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
	public function set(CacheEntry $entry): bool
	{
		$key                    = $entry->key;
		$expire                 = $entry->expiresAt;
		$expire_in_milliseconds = (null !== $expire) ? (int) ($expire * 1000) : null;

		$this->memcached->set($key, $entry->value, $expire_in_milliseconds);

		if (Memcached::RES_SUCCESS !== $this->memcached->getResultCode()) {
			return false;
		}

		if (null !== $expire) {
			$this->memcached->set($key . ':expire', $expire, $expire_in_milliseconds);
		}

		return Memcached::RES_SUCCESS === $this->memcached->getResultCode();
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function increment(string $key, float $factor = 1): bool
	{
		return $this->memcached->increment($key, $factor);
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function decrement(string $key, float $factor = 1): bool
	{
		return $this->memcached->decrement($key, $factor);
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function delete(string $key): bool
	{
		return $this->memcached->delete($key);
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function deleteMultiple(array $keys): bool
	{
		$deleted = true;

		foreach ($keys as $key) {
			$deleted = $this->delete($key) && $deleted;
		}

		return $deleted;
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function clear(): bool
	{
		return $this->memcached->flush();
	}

	/**
	 * {@inheritDoc}
	 *
	 * MemcachedCache does not support server-side expiry scanning.
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
		static $memcached_instances = [];

		$servers = [
			'_' => [],
		];

		if (isset($memcached_instances[$namespace])) {
			$instance = $memcached_instances[$namespace];
		} else {
			$instance = new Memcached($namespace);
			// Add servers if none connected yet.
			if (!\count($instance->getServerList())) {
				$prefix = Hasher::shorten($namespace);
				$instance->setOption(Memcached::OPT_PREFIX_KEY, $prefix . ':');
				$instance->setOption(Memcached::OPT_LIBKETAMA_COMPATIBLE, true);
				$instance->setOption(Memcached::OPT_RECV_TIMEOUT, 1000);
				$instance->setOption(Memcached::OPT_SEND_TIMEOUT, 3000);
				$instance->setOption(Memcached::OPT_TCP_NODELAY, true);
				$instance->addServers($servers);
			}

			$memcached_instances[$namespace] = $instance;
		}

		return new self($instance);
	}
}
