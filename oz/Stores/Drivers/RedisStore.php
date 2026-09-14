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
use OZONE\Core\Stores\Interfaces\StoreDriverInterface;
use OZONE\Core\Stores\StoreCapabilities;
use OZONE\Core\Stores\StoreEntry;
use OZONE\Core\Utils\RedisFactory;
use Redis as PhpRedis;
use RuntimeException;

/**
 * Class RedisStore.
 *
 * Redis-backed cache provider using the ext-redis PHP extension.
 *
 * The extension is checked in the constructor rather than at file level, so the class can be named
 * and inspected on a host that does not have it.
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
class RedisStore implements StoreDriverInterface
{
	private const VALUE_KEY  = 'v';
	private const EXPIRE_KEY = 'e';

	/**
	 * RedisStore constructor.
	 *
	 * @param string $namespace
	 */
	/**
	 * @param string $namespace
	 *
	 * @throws RuntimeException when `ext-redis` is missing
	 */
	public function __construct(protected readonly string $namespace)
	{
		// Here and not at the top of the file: a file-level `throw` fires while the class is being
		// **autoloaded**, so merely naming it -- `RedisStore::class` in a settings array, an
		// `is_subclass_of()` check, a reflection pass -- would blow up on a host without the
		// extension, long before anyone asked for a Redis store.
		/** @noinspection ClassConstantCanBeUsedInspection */
		if (!\class_exists('\Redis')) {
			throw new RuntimeException(
				'The Redis cache driver needs the PHP ext-redis extension, which is not installed.'
			);
		}
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function capabilities(): StoreCapabilities
	{
		return new StoreCapabilities(
			perEntryTTL: true,
			persistent: true,
			expiryCallbacks: false, // Redis TTL evicts passively; no push-based GC needed
			atomic: true,
			// Redis is a server shared by every instance.
			durable: true,
		);
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function get(string $key): ?StoreEntry
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

		return new StoreEntry($key, $data[self::VALUE_KEY], $expire);
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

		return $this->set(new StoreEntry($key, $entry->value + $factor, $entry->expiresAt));
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

		return $this->set(new StoreEntry($key, $entry->value - $factor, $entry->expiresAt));
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

		// The cursor starts at null, never at 0: phpredis reads 0 as "iteration finished", so the
		// first scan() returns false and nothing is ever deleted.
		$cursor = null;

		do {
			$batch = $redis->scan($cursor, $pattern, 100);

			if (\is_array($batch) && !empty($batch)) {
				// SCAN returns raw Redis keys (including the global prefix).
				// Strip the prefix before passing to del(), which re-applies it.
				$keys = \array_map(static fn ($k) => \substr($k, $pfx_len), $batch);
				$redis->del($keys);
			}
		} while ($cursor);

		return true;
	}

	/**
	 * {@inheritDoc}
	 *
	 * RedisStore does not support server-side expiry scanning; Redis handles TTL eviction natively.
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
