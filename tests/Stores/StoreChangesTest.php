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

namespace OZONE\Tests\Stores;

use OZONE\Core\Exceptions\RuntimeException;
use OZONE\Core\Stores\Drivers\DbStore;
use OZONE\Core\Stores\Drivers\MemoryStore;
use OZONE\Core\Stores\KeyValueStore;
use OZONE\Core\Stores\StoreEntry;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Class StoreChangesTest.
 *
 * What a client reading a store since its last visit is told, and what it must never be left to
 * guess.
 *
 * A device that was away comes back with a cursor and asks what moved. Everything that can go wrong
 * here is silent: a removal nobody mentions leaves a value on the device forever; a change that
 * falls between two pages is never seen again; a store that keeps removals and never forgets them
 * grows until the table is the problem. Each of those is a case below.
 *
 * @internal
 */
#[CoversClass(DbStore::class)]
final class StoreChangesTest extends TestCase
{
	public function testWhatMovedComesBackOldestFirstWithItsVersion(): void
	{
		$store = self::changing();

		$store->set('a', 1);
		$store->set('b', 2);
		$store->set('c', 3);

		$changed = $store->changedSince(0);

		self::assertSame(['a', 'b', 'c'], \array_map(static fn (StoreEntry $e): string => $e->key, $changed));
		self::assertSame([1, 2, 3], \array_map(static fn (StoreEntry $e): mixed => $e->value, $changed));

		$versions = \array_map(static fn (StoreEntry $e): ?int => $e->version, $changed);

		self::assertSame($versions, \array_values(\array_filter($versions)));
		// Oldest first and never equal for one key: a reader paging through them cannot go
		// backwards, and the last one it saw is a cursor it can come back with.
		self::assertLessThanOrEqual($versions[1], $versions[0]);
		self::assertLessThanOrEqual($versions[2], $versions[1]);
	}

	public function testOnlyWhatMovedSinceTheCursorComesBack(): void
	{
		$store = self::changing();

		$store->set('a', 1);

		$cursor = $store->entry('a')?->version ?? 0;

		self::assertSame([], $store->changedSince($cursor));

		$store->set('b', 2);

		$changed = $store->changedSince($cursor);

		self::assertCount(1, $changed);
		self::assertSame('b', $changed[0]->key);
	}

	public function testARemovalIsToldAsOne(): void
	{
		$store = self::changing();

		$store->set('a', 1);

		$cursor = $store->entry('a')?->version ?? 0;

		$store->delete('a');

		$changed = $store->changedSince($cursor);

		self::assertCount(1, $changed);
		// Said as a removal and not as a value of null, which a store may legitimately hold: a
		// client that read the two the same way would keep an entry it should drop, or drop one
		// whose value really is null.
		self::assertTrue($changed[0]->removed);
		self::assertNull($changed[0]->value);
		self::assertGreaterThan($cursor, $changed[0]->version);
		// And the key holds nothing, whatever the removal left behind for a reader.
		self::assertNull($store->get('a'));
	}

	public function testClearingTellsAClientOfEveryKeyItHeld(): void
	{
		$store = self::changing();

		$store->set('a', 1);
		$store->set('b', 2);

		$cursor = $store->changedSince(0);
		$last   = $cursor[\count($cursor) - 1] ?? null;

		self::assertNotNull($last);

		$at = $last->version ?? 0;

		$store->clear();

		$changed = $store->changedSince($at);

		// A store that dropped its rows on `clear()` would leave a device holding all of them with
		// nothing to read: the device would never hear of the clearing at all.
		self::assertCount(2, $changed);
		self::assertSame([true, true], \array_map(static fn (StoreEntry $e): bool => $e->removed, $changed));
	}

	public function testAWrittenKeyComesBackOnceAndNotTwice(): void
	{
		$store = self::changing();

		$store->set('a', 1);
		$store->set('a', 2);

		$changed = $store->changedSince(0);

		// One entry for a key written twice: what a client needs is the value it holds now, and a
		// page of its history would cost the device a write per version it never asked about.
		self::assertCount(1, $changed);
		self::assertSame(2, $changed[0]->value);
	}

	public function testAPageIsAsLongAsAskedAndTheNextOneCarriesOn(): void
	{
		$store = self::changing();

		foreach (['a', 'b', 'c', 'd'] as $index => $key) {
			$store->set($key, $index);
		}

		$first = $store->changedSince(0, 2);

		self::assertCount(2, $first);

		$second = $store->changedSince($first[1]->version ?? 0, 2);

		self::assertSame(['c', 'd'], \array_map(static fn (StoreEntry $e): string => $e->key, $second));
	}

	public function testOldRemovalsAreForgottenAndTheRestKept(): void
	{
		$store = self::changing();

		$store->set('old', 1);
		$store->delete('old');

		$boundary = StoreEntry::nextVersion();

		$store->set('recent', 2);
		$store->delete('recent');

		self::assertCount(2, $store->changedSince(0));

		$forgotten = $store->getProvider()->pruneRemoved($boundary);

		self::assertSame(1, $forgotten);

		$changed = $store->changedSince(0);

		// The recent removal is still there to be told; the old one is gone, and a client whose
		// cursor is older than the retention has to read the store whole -- which it can work out
		// for itself, a version being a microsecond of the clock.
		self::assertCount(1, $changed);
		self::assertSame('recent', $changed[0]->key);
	}

	public function testAStoreThatKeepsNoChangesSaysSoRatherThanAnsweringNothing(): void
	{
		$store = new KeyValueStore('plain', new DbStore(self::namespace()));

		// Answering an empty list would read as "nothing moved", which is the one answer a sync
		// must not be given wrongly: the device would keep everything it has forever.
		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessageMatches('/does not keep what changed/');

		$store->changedSince(0);
	}

	public function testADriverThatKeepsNoChangesForgetsNothing(): void
	{
		$memory = new MemoryStore(self::namespace());

		self::assertFalse($memory->capabilities()->changes);
		self::assertSame(0, $memory->pruneRemoved(StoreEntry::nextVersion()));
	}

	/**
	 * A store on the database, keeping what changed: what a client store runs on.
	 */
	private static function changing(): KeyValueStore
	{
		return new KeyValueStore('changing', new DbStore(self::namespace(), true));
	}

	private static function namespace(): string
	{
		return 'changes:' . \bin2hex(\random_bytes(4));
	}
}
