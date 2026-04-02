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
use OZONE\Core\Cache\CacheItem;
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
	public function get(string $key): ?CacheItem
	{
		$value = $this->memcached->get($key);

		if (Memcached::RES_NOTFOUND === $this->memcached->getResultCode()) {
			return null;
		}

		$expire = $this->memcached->get($key . ':expire');

		if (Memcached::RES_NOTFOUND === $this->memcached->getResultCode()) {
			return new CacheItem($key, $value);
		}

		if ($expire > \microtime(true)) {
			return new CacheItem($key, $value, (float) $expire);
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
	public function set(CacheItem $item): bool
	{
		$key                    = $item->getKey();
		$expire                 = $item->getExpire();
		$expire_in_milliseconds = (null !== $expire) ? (int) ($expire * 1000) : null;

		$this->memcached->set($key, $item->get(), $expire_in_milliseconds);

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
	 */
	#[Override]
	public static function getSharedInstance(?string $namespace = null): static
	{
		// one instantiation per-connection per-request
		static $memcached_instances = [];

		$servers   = [
			'_' => [],
		];
		$namespace ??= '_';

		if (isset($memcached_instances[$namespace])) {
			$instance = $memcached_instances[$namespace];
		} else {
			$instance = new Memcached($namespace);
			// Add servers if no connections listed.
			// In a production environment with multiple server sets you may wish to prevent typos from silently adding data
			// to the default pool, in which case return an error on no match instead of defaulting
			if (!\count($instance->getServerList())) {
				$prefix = Hasher::shorten($namespace);
				$instance->setOption(Memcached::OPT_PREFIX_KEY, $prefix . ':');
				// advisable option
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
