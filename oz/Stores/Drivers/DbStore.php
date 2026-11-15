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
use OZONE\Core\Stores\StoreWrite;
use RuntimeException;

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
	private const string CACHE_VALUE = 'v';

	/**
	 * How long a removal is remembered, in seconds, where a store keeps what changed.
	 *
	 * Thirty days: long enough for a device that was away a month, short enough that a store nobody
	 * prunes does not grow without end. A client whose cursor is older than this must read the
	 * store whole, and it can tell: a version **is** a microsecond of the clock, so the oldest
	 * cursor still safe is "now minus the retention" and needs nothing kept to be known.
	 */
	public const int CHANGES_RETENTION = 2_592_000;

	/**
	 * DbStore constructor.
	 *
	 * @param string $namespace the store's name
	 * @param bool   $changes   keep removals, so a client can read what it is missing: a store says
	 *                          so in its options, since a cache has no use for the rows it costs
	 */
	public function __construct(
		private readonly string $namespace,
		private readonly bool $changes = false,
	) {}

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
			// A version per row, and a conditional write that is one statement: the version the
			// caller read is in its WHERE, so two writers cannot both believe they won.
			versioned: true,
			changes: $this->changes,
		);
	}

	/**
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

		return new StoreEntry($key, $data[self::CACHE_VALUE], $expires_at, (int) $store->getVersion());
	}

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
	 * @throws GoblException
	 */
	#[Override]
	public function set(StoreEntry $entry): bool
	{
		$this->write($entry, StoreEntry::nextVersion());

		return true;
	}

	/**
	 * {@inheritDoc}
	 *
	 * One statement, and the version the caller read is in its WHERE: the write happens where the
	 * entry is still the one that was read, and nowhere else, so two writers cannot both win. A
	 * create (`$expectedVersion` null) revives a row that is a removal or has expired, since the
	 * key is unique and such a row is still there.
	 *
	 * @throws GoblException
	 */
	#[Override]
	public function setIf(StoreEntry $entry, ?int $expectedVersion): StoreWrite
	{
		$version = StoreEntry::nextVersion();

		if (null !== $expectedVersion) {
			$written = $this->rowsOf($entry->key)
				->whereVersionIs($expectedVersion)
				->whereIsNotDeleted()
				->update($this->writtenColumns($entry, $version))
				->execute();

			return 1 === $written
				? StoreWrite::applied($this->entryOf($entry, $version))
				: StoreWrite::refused($this->get($entry->key));
		}

		// A create: the row may be there as a removal or expired, which is a key holding nothing.
		// `includeSoftDeletedRows()` because the ORM leaves removals out of an update by default,
		// which is right everywhere else and would make reviving one match no row at all.
		$revived = $this->rowsOf($entry->key)
			->includeSoftDeletedRows()
			->whereIsDeleted()
			->update($this->writtenColumns($entry, $version, revive: true))
			->execute();

		if (1 === $revived) {
			return StoreWrite::applied($this->entryOf($entry, $version));
		}

		if (null !== $this->get($entry->key)) {
			return StoreWrite::refused($this->get($entry->key));
		}

		// Absent, or expired and not a removal. Written where nothing live holds the key: two
		// requests creating it at once both insert, one is ignored, and the read below says whose
		// write is there -- which the insert's own answer cannot, the drivers not agreeing on what
		// an ignored insert returns.
		$expired = $this->rowsOf($entry->key)
			->whereIsNotDeleted()
			->whereExpireAtIsNotNull()
			->whereExpireAtIsLte(\time())
			->update($this->writtenColumns($entry, $version))
			->execute();

		if (1 !== $expired) {
			$row = $this->newRow($entry->key)
				->setValue($this->serialize($entry->value))
				->setExpireAT($this->expiryOf($entry))
				->setVersion($version)
				->toRow();

			unset($row[OZDbStore::COL_ID]);

			new OZDbStoresQuery()->insert($row)->ignoreOnConflict()->execute();
		}

		$current = $this->get($entry->key);

		return $current?->version === $version
			? StoreWrite::applied($current)
			: StoreWrite::refused($current);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @throws GoblException
	 */
	#[Override]
	public function deleteIf(string $key, int $expectedVersion): StoreWrite
	{
		$rows = $this->rowsOf($key)
			->whereVersionIs($expectedVersion)
			->whereIsNotDeleted();

		$written = $this->changes
			? $rows->update($this->removedColumns(StoreEntry::nextVersion()))->execute()
			: $rows->delete()->execute();

		return 1 === $written
			? StoreWrite::applied(null)
			: StoreWrite::refused($this->get($key));
	}

	/**
	 * {@inheritDoc}
	 *
	 * Removals included, as entries saying so: a client told nothing of them would keep a value the
	 * store no longer has. An entry that **expired** is reported as removed too, for the same
	 * reason, which is why a store read by clients is better off without per-entry expiry: an
	 * expiry moves no version, so a client that had already read that version is never told.
	 *
	 * @return StoreEntry[]
	 *
	 * @throws GoblException
	 */
	#[Override]
	public function changedSince(int $version, int $limit = 100): array
	{
		$results = new OZDbStoresQuery()
			// The removals are the point: a client told nothing of them keeps what the store lost.
			->includeSoftDeletedRows()
			->whereGroupIs($this->namespace)
			->whereVersionIsGt($version)
			->find(ORMOptions::makePaginated($limit, null, [
				// The id after the version, because two keys written in one microsecond share a
				// version: a page of changes then still has one order, whoever reads it.
				OZDbStore::COL_VERSION => 'ASC',
				OZDbStore::COL_ID      => 'ASC',
			]));

		$changed = [];

		while ($row = $results->fetchClass()) {
			$key       = $row->getLabel();
			$at        = (int) $row->getVersion();
			$expire_at = $row->getExpireAT();
			$expired   = null !== $expire_at && (int) $expire_at <= \time();

			if ($row->isDeleted() || $expired) {
				$changed[] = new StoreEntry($key, null, null, $at, removed: true);

				continue;
			}

			[$signed, $data] = SignedSerializer::unserialize((string) $row->getValue());

			if (!$signed || !\is_array($data)) {
				continue;
			}

			$changed[] = new StoreEntry(
				$key,
				$data[self::CACHE_VALUE],
				null !== $expire_at ? (float) $expire_at : null,
				$at
			);
		}

		return $changed;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @throws GoblException
	 */
	#[Override]
	public function pruneRemoved(int $beforeVersion): int
	{
		if (!$this->changes) {
			return 0;
		}

		return new OZDbStoresQuery()
			->includeSoftDeletedRows()
			->whereGroupIs($this->namespace)
			->whereIsDeleted()
			->whereVersionIsLt($beforeVersion)
			->delete()
			->execute();
	}

	/**
	 * {@inheritDoc}
	 *
	 * @throws GoblException
	 */
	#[Override]
	public function delete(string $key): bool
	{
		if ($this->changes) {
			// Kept as a removal with a version of its own, so a client reading changes is told the
			// key is gone. Without that it would keep a value forever, having nothing to read.
			$this->rowsOf($key)
				->whereIsNotDeleted()
				->update($this->removedColumns(StoreEntry::nextVersion()))
				->execute();

			return true;
		}

		$store = $this->findRow($key);

		if (null !== $store) {
			$store->selfDelete(false);
		}

		return true;
	}

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
		if ($this->changes) {
			// Every key removed is every key a client has to be told about: a cleared store that
			// dropped its rows would leave a device holding all of them with nothing to read.
			new OZDbStoresQuery()
				->whereGroupIs($this->namespace)
				->whereIsNotDeleted()
				->update($this->removedColumns(StoreEntry::nextVersion()))
				->execute();

			return true;
		}

		new OZDbStoresQuery()
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

				if (0 === $by) {
					return $sum;
				}
			}

			$written = new OZDbStoresQuery()
				->whereIdIs($store->getID() ?? throw new RuntimeException(
					'A store row read from the database has no id.'
				))
				->whereValueIs($raw)
				->update([
					OZDbStore::COL_VALUE      => SignedSerializer::serialize([self::CACHE_VALUE => $sum]),
					OZDbStore::COL_EXPIRE_AT  => $new_expire,
					OZDbStore::COL_VERSION    => StoreEntry::nextVersion(),
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
		$results = new OZDbStoresQuery()
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

	#[Override]
	public static function fromConfig(string $namespace, array $options = []): static
	{
		return new self($namespace, true === ($options['changes'] ?? false));
	}

	/**
	 * The rows of one key in this namespace: what a conditional write and a removal narrow further.
	 */
	private function rowsOf(string $key): OZDbStoresQuery
	{
		return new OZDbStoresQuery()
			->whereGroupIs($this->namespace)
			->whereKeyIs($this->hashKey($key));
	}

	/**
	 * Writes the entry at that version, inserted or updated in place.
	 *
	 * One statement: reading first, then inserting, let two requests writing a new key at once (two
	 * first hits of a rate limit) collide on its unique key.
	 *
	 * @throws GoblException
	 */
	private function write(StoreEntry $entry, int $version): void
	{
		$row = $this->newRow($entry->key)
			->setValue($this->serialize($entry->value))
			->setExpireAT($this->expiryOf($entry))
			->setVersion($version)
			->toRow();

		unset($row[OZDbStore::COL_ID]);

		new OZDbStoresQuery()
			->insert($row)
			->doUpdateOnConflict(
				[OZDbStore::COL_GROUP, OZDbStore::COL_KEY],
				[
					OZDbStore::COL_VALUE,
					OZDbStore::COL_EXPIRE_AT,
					OZDbStore::COL_LABEL,
					OZDbStore::COL_VERSION,
					OZDbStore::COL_UPDATED_AT,
					OZDbStore::COL_DELETED,
					OZDbStore::COL_DELETED_AT,
				]
			)
			->execute();
	}

	/**
	 * The columns a write sets: the value, when it expires, and the version it takes.
	 *
	 * @param bool $revive also brings back a row that was a removal
	 *
	 * @return array<string, null|bool|int|string>
	 */
	private function writtenColumns(StoreEntry $entry, int $version, bool $revive = false): array
	{
		$columns = [
			OZDbStore::COL_VALUE      => $this->serialize($entry->value),
			OZDbStore::COL_EXPIRE_AT  => $this->expiryOf($entry),
			OZDbStore::COL_VERSION    => $version,
			OZDbStore::COL_UPDATED_AT => \time(),
		];

		if ($revive) {
			$columns[OZDbStore::COL_DELETED]    = false;
			$columns[OZDbStore::COL_DELETED_AT] = null;
		}

		return $columns;
	}

	/**
	 * The columns a removal sets: a version of its own, so a client reading changes sees it.
	 *
	 * @return array<string, null|bool|int|string>
	 */
	private function removedColumns(int $version): array
	{
		return [
			OZDbStore::COL_VALUE      => null,
			OZDbStore::COL_DELETED    => true,
			OZDbStore::COL_DELETED_AT => \time(),
			OZDbStore::COL_VERSION    => $version,
			OZDbStore::COL_UPDATED_AT => \time(),
		];
	}

	/**
	 * The entry as it was written: what a conditional write answers with.
	 */
	private function entryOf(StoreEntry $entry, int $version): StoreEntry
	{
		return new StoreEntry($entry->key, $entry->value, $entry->expiresAt, $version);
	}

	/**
	 * A value as the column holds it: signed, so a row changed outside the app is not read back.
	 */
	private function serialize(mixed $value): string
	{
		return SignedSerializer::serialize([self::CACHE_VALUE => $value]);
	}

	/**
	 * When an entry expires, as the column holds it.
	 */
	private function expiryOf(StoreEntry $entry): ?int
	{
		return null !== $entry->expiresAt ? (int) \ceil($entry->expiresAt) : null;
	}

	/**
	 * Finds a DB row for the given original key.
	 *
	 * @throws GoblException
	 */
	private function findRow(string $key): ?OZDbStore
	{
		return new OZDbStoresQuery()
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
			->setVersion(StoreEntry::nextVersion())
			->toRow();

		unset($row[OZDbStore::COL_ID]);

		new OZDbStoresQuery()->insert($row)->ignoreOnConflict()->execute();
	}

	/**
	 * Creates a new unsaved DB row for the given original key.
	 */
	private function newRow(string $key): OZDbStore
	{
		return new OZDbStore()
			->setGroup($this->namespace)
			->setKey($this->hashKey($key))
			->setLabel($key);
	}

	/**
	 * Returns the hashed (storage) key for a given original key.
	 *
	 * The `oz_db_stores.key` column has a min-length constraint of 32 chars,
	 * so we always use the md5 hash of (namespace + ':' + key).
	 */
	private function hashKey(string $key): string
	{
		return \md5($this->namespace . ':' . $key);
	}
}
