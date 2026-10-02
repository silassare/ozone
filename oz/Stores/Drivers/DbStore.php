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
use OZONE\Core\Stores\StoreNumbers;

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
			atomic: true,
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
		$raw       = SignedSerializer::serialize([self::CACHE_VALUE => $entry->value]);
		$expire_at = null !== $entry->expiresAt ? (int) \ceil($entry->expiresAt) : null;
		$row       = $this->newRow($entry->key)
			->setValue($raw)
			->setExpireAT($expire_at)
			->toRow();

		unset($row[OZDbStore::COL_ID]);

		// One statement, inserted or updated in place: reading first, then inserting, let two requests
		// writing a new key at once (two first hits of a rate limit) collide on its unique key.
		(new OZDbStoresQuery())
			->insert($row)
			->doUpdateOnConflict(
				[OZDbStore::COL_GROUP, OZDbStore::COL_KEY],
				[
					OZDbStore::COL_VALUE,
					OZDbStore::COL_EXPIRE_AT,
					OZDbStore::COL_LABEL,
					OZDbStore::COL_UPDATED_AT,
					OZDbStore::COL_DELETED,
					OZDbStore::COL_DELETED_AT,
				]
			)
			->execute();

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
	 *
	 * Compare-and-set: the new number is written only where the entry still holds what was read, and
	 * read again otherwise. A missing entry is first created at zero (skipped when another request
	 * created it meanwhile), then added to as any other.
	 *
	 * @throws GoblException
	 */
	#[Override]
	public function add(string $key, float|int $by, bool $create = false, ?float $expiresAt = null): float|int|null
	{
		for ($attempt = 0; $attempt < StoreNumbers::MAX_ATTEMPTS; ++$attempt) {
			$store = $this->findRow($key);

			if (null === $store) {
				if (!$create) {
					return null;
				}

				$this->insertIfMissing($key, 0, $expiresAt);

				continue;
			}

			$raw       = (string) $store->getValue();
			$expire_at = $store->getExpireAT();
			$expired   = null !== $expire_at && (int) $expire_at <= \time();

			if ($expired && !$create) {
				return null;
			}

			if ($expired) {
				$sum        = $by;
				$new_expire = null !== $expiresAt ? (int) \ceil($expiresAt) : null;
			} else {
				[$signed, $data] = SignedSerializer::unserialize($raw);
				$sum             = StoreNumbers::sum(
					$key,
					$signed && \is_array($data) ? ($data[self::CACHE_VALUE] ?? null) : null,
					$by
				);
				$new_expire = null !== $expire_at ? (int) $expire_at : null;

				if (0 == $by) {
					return $sum;
				}
			}

			$written = (new OZDbStoresQuery())
				->whereIdIs($store->getID())
				->whereValueIs($raw)
				->update([
					OZDbStore::COL_VALUE      => SignedSerializer::serialize([self::CACHE_VALUE => $sum]),
					OZDbStore::COL_EXPIRE_AT  => $new_expire,
					OZDbStore::COL_UPDATED_AT => \time(),
				])
				->execute();

			if (1 === $written) {
				return $sum;
			}
		}

		throw StoreNumbers::contended($key);
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
	 * Creates an entry holding a number unless one exists by that key (another request may have just
	 * created it): one statement, so nothing collides.
	 */
	private function insertIfMissing(string $key, float|int $value, ?float $expiresAt): void
	{
		$row = $this->newRow($key)
			->setValue(SignedSerializer::serialize([self::CACHE_VALUE => $value]))
			->setExpireAT(null !== $expiresAt ? (int) \ceil($expiresAt) : null)
			->toRow();

		unset($row[OZDbStore::COL_ID]);

		(new OZDbStoresQuery())->insert($row)->ignoreOnConflict()->execute();
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
