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

use Gobl\Exceptions\GoblException;
use Gobl\ORM\ORMOptions;
use Override;
use OZONE\Core\Crypt\SignedSerializer;
use OZONE\Core\Db\OZDbStore;
use OZONE\Core\Db\OZDbStoresQuery;
use OZONE\Core\Stores\Interfaces\StoreDriverInterface;
use OZONE\Core\Stores\StoreCapabilities;
use OZONE\Core\Stores\StoreEntry;

/**
 * Class DbStore.
 *
 * Database-backed persistent cache driver using the `oz_db_stores` table.
 *
 * Each cache entry is stored as an individual row:
 *   - `group`     = namespace (store name)
 *   - `key`       = md5(namespace + ':' + original_key) — always 32 chars, respects column constraint
 *   - `value`     = signed serialized entry value (see {@see SignedSerializer})
 *   - `expire_at` = Unix timestamp expiry (null = no expiry)
 *   - `label`     = original (human-readable) cache key, used by GC to reconstruct {@see StoreEntry}
 *
 * Unlike the old implementation, this driver stores one row per cache key
 * rather than one row per entire namespace, which eliminates serialized-blob
 * overflow and makes per-entry expiry and GC practical.
 */
final class DbStore implements StoreDriverInterface
{
	private const CACHE_VALUE = 'v';

	/**
	 * DbStore constructor.
	 *
	 * @param string $namespace
	 */
	public function __construct(private readonly string $namespace) {}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function capabilities(): StoreCapabilities
	{
		return new StoreCapabilities(
			perEntryTTL: true,
			persistent: true,
			expiryCallbacks: true,
			atomic: false,
			// The database is the project's own durable store.
			durable: true,
		);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @throws GoblException
	 */
	#[Override]
	public function get(string $key): ?StoreEntry
	{
		$store = $this->findRow($key);

		if (null === $store) {
			return null;
		}

		$expire_at = $store->getExpireAT();

		if (null !== $expire_at && (int) $expire_at <= \time()) {
			$store->selfDelete(false);

			return null;
		}

		[$signed, $data] = SignedSerializer::unserialize((string) $store->getValue());

		if (!$signed || !\is_array($data)) {
			return null;
		}

		$expires_at = null !== $expire_at ? (float) $expire_at : null;

		return new StoreEntry($key, $data[self::CACHE_VALUE], $expires_at);
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
	 *
	 * @throws GoblException
	 */
	#[Override]
	public function set(StoreEntry $entry): bool
	{
		$store = $this->findRow($entry->key) ?? $this->newRow($entry->key);

		$raw       = SignedSerializer::serialize([self::CACHE_VALUE => $entry->value]);
		$expire_at = null !== $entry->expiresAt ? (int) \ceil($entry->expiresAt) : null;

		$store->setValue($raw)
			->setExpireAT($expire_at)
			->save();

		return true;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @throws GoblException
	 */
	#[Override]
	public function delete(string $key): bool
	{
		$store = $this->findRow($key);

		if (null !== $store) {
			$store->selfDelete(false);
		}

		return true;
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function deleteMultiple(array $keys): bool
	{
		$ok = true;

		foreach ($keys as $key) {
			$ok = $this->delete($key) && $ok;
		}

		return $ok;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @throws GoblException
	 */
	#[Override]
	public function clear(): bool
	{
		(new OZDbStoresQuery())
			->whereGroupIs($this->namespace)
			->delete()
			->execute();

		return true;
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

		$new = new StoreEntry($key, $entry->value + $factor, $entry->expiresAt);

		return $this->set($new);
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

		$new = new StoreEntry($key, $entry->value - $factor, $entry->expiresAt);

		return $this->set($new);
	}

	/**
	 * {@inheritDoc}
	 *
	 * Queries the database for all rows in this namespace where
	 * `expire_at` is set and less than or equal to the current Unix time.
	 * The original key is reconstructed from the `label` column.
	 *
	 * @throws GoblException
	 */
	#[Override]
	public function getExpiredEntries(int $limit = 100): array
	{
		$results = (new OZDbStoresQuery())
			->whereGroupIs($this->namespace)
			->whereIsNotDeleted()
			->whereExpireAtIsNotNull()
			->whereExpireAtIsLte(\time())
			->find(ORMOptions::makePaginated($limit));

		$entries = [];

		while ($row = $results->fetchClass()) {
			[$signed, $data] = SignedSerializer::unserialize((string) $row->getValue());

			if (!$signed || !\is_array($data)) {
				// Unsigned (legacy or tampered): nothing to hand to a listener, and
				// nothing else would ever delete it.
				$row->selfDelete(false);

				continue;
			}

			$original_key           = $row->getLabel();
			$entries[$original_key] = new StoreEntry(
				$original_key,
				$data[self::CACHE_VALUE],
				(float) $row->getExpireAT(),
			);
		}

		return $entries;
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
	 * Finds a DB row for the given original key.
	 *
	 * @param string $key
	 *
	 * @return null|OZDbStore
	 *
	 * @throws GoblException
	 */
	private function findRow(string $key): ?OZDbStore
	{
		return (new OZDbStoresQuery())
			->whereGroupIs($this->namespace)
			->whereKeyIs($this->hashKey($key))
			->whereIsNotDeleted()
			->find(ORMOptions::makePaginated(1))
			->fetchClass();
	}

	/**
	 * Creates a new unsaved DB row for the given original key.
	 *
	 * @param string $key
	 *
	 * @return OZDbStore
	 */
	private function newRow(string $key): OZDbStore
	{
		return (new OZDbStore())
			->setGroup($this->namespace)
			->setKey($this->hashKey($key))
			->setLabel($key);
	}

	/**
	 * Returns the hashed (storage) key for a given original key.
	 *
	 * The `oz_db_stores.key` column has a min-length constraint of 32 chars,
	 * so we always use the md5 hash of (namespace + ':' + key).
	 *
	 * @param string $key
	 *
	 * @return string
	 */
	private function hashKey(string $key): string
	{
		return \md5($this->namespace . ':' . $key);
	}
}
