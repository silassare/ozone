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

use Override;
use OZONE\Core\Cache\CacheCapabilities;
use OZONE\Core\Cache\CacheEntry;
use OZONE\Core\Cache\Interfaces\CacheProviderInterface;
use OZONE\Core\Utils\RedisFactory;
use Redis as PhpRedis;
use RuntimeException;

/** @noinspection ClassConstantCanBeUsedInspection */
if (!\class_exists('\Redis')) {
	throw new RuntimeException('The PHP ext-redis extension is not installed.');
}

/**
 * Class RedisCache.
 *
 * Redis-backed cache provider using the ext-redis PHP extension.
 *
 * Each cache entry is stored as a single serialized blob at the namespaced key
 * `{namespace}:{key}`. The blob encodes both the cached value and the absolute
 * expiry timestamp. A native Redis TTL is also set when an expiry is present so
 * expired keys are automatically evicted.
 *
 * Namespace isolation: all existing keys in a namespace can be cleared in one
 * pass via {@link clear()}, which uses a Redis SCAN + DEL loop.
 *
 * Configuration comes from the `oz.redis` settings group (see `oz.redis.php`).
 */
class RedisCache implements CacheProviderInterface
{
	private const VALUE_KEY  = 'v';
	private const EXPIRE_KEY = 'e';

	/**
	 * RedisCache constructor.
	 *
	 * @param string $namespace
	 */
	public function __construct(protected readonly string $namespace) {}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function capabilities(): CacheCapabilities
	{
		return new CacheCapabilities(
			perEntryTTL: true,
			persistent: true,
			expiryCallbacks: false, // Redis TTL evicts passively; no push-based GC needed
			atomic: true,
		);
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function get(string $key): ?CacheEntry
	{
		$raw = RedisFactory::get()->get($this->buildKey($key));

		if (false === $raw) {
			return null;
		}

		$data = \unserialize($raw, ['allowed_classes' => false]);

		if (!\is_array($data)) {
			return null;
		}

		/** @var null|float $expire */
		$expire = $data[self::EXPIRE_KEY] ?? null;

		if (null !== $expire && $expire <= \microtime(true)) {
			RedisFactory::get()->del($this->buildKey($key));

			return null;
		}

		return new CacheEntry($key, $data[self::VALUE_KEY], $expire);
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
		$expire    = $entry->expiresAt;
		$redis_key = $this->buildKey($entry->key);
		$raw       = \serialize([
			self::VALUE_KEY  => $entry->value,
			self::EXPIRE_KEY => $expire,
		]);

		if (null !== $expire) {
			$ttl_ms = (int) \max(1, ($expire - \microtime(true)) * 1000);

			return RedisFactory::get()->set($redis_key, $raw, ['px' => $ttl_ms]);
		}

		return RedisFactory::get()->set($redis_key, $raw);
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function increment(string $key, float $factor = 1): bool
	{
		$entry = $this->get($key);

		if (null === $entry) {
			return false;
		}

		return $this->set(new CacheEntry($key, $entry->value + $factor, $entry->expiresAt));
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function decrement(string $key, float $factor = 1): bool
	{
		$entry = $this->get($key);

		if (null === $entry) {
			return false;
		}

		return $this->set(new CacheEntry($key, $entry->value - $factor, $entry->expiresAt));
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function delete(string $key): bool
	{
		return (bool) RedisFactory::get()->del($this->buildKey($key));
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function deleteMultiple(array $keys): bool
	{
		$redis_keys = \array_map($this->buildKey(...), $keys);

		return (bool) RedisFactory::get()->del($redis_keys);
	}

	/**
	 * {@inheritDoc}
	 *
	 * Scans all keys matching `{namespace}:*` and deletes them in batches.
	 * The scan pattern accounts for any global prefix configured via
	 * `OZ_REDIS_PREFIX` so that only keys belonging to this namespace are
	 * removed, without touching other applications sharing the same instance.
	 */
	#[Override]
	public function clear(): bool
	{
		$redis   = RedisFactory::get();
		$raw_pfx = (string) ($redis->getOption(PhpRedis::OPT_PREFIX) ?? '');
		$pattern = $raw_pfx . $this->namespace . ':*';
		$pfx_len = \strlen($raw_pfx);
		$cursor  = 0;

		do {
			$batch = $redis->scan($cursor, $pattern, 100);

			if (\is_array($batch) && !empty($batch)) {
				// SCAN returns raw Redis keys (including the global prefix).
				// Strip the prefix before passing to del(), which re-applies it.
				$keys = \array_map(static fn ($k) => \substr($k, $pfx_len), $batch);
				$redis->del($keys);
			}
		} while (0 !== $cursor);

		return true;
	}

	/**
	 * {@inheritDoc}
	 *
	 * RedisCache does not support server-side expiry scanning; Redis handles TTL eviction natively.
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
		return new self($namespace);
	}

	/**
	 * Builds the namespaced Redis key for a given cache key.
	 *
	 * @param string $key
	 *
	 * @return string
	 */
	private function buildKey(string $key): string
	{
		return $this->namespace . ':' . $key;
	}
}
