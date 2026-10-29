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

use OZONE\Core\Stores\RecordingStore;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Class RecordingStoreTest.
 *
 * A store several requests hold at once keeps the writes made to it, so the UPDATE sends the keys
 * that changed instead of a document carrying every key as this request read them.
 *
 * @internal
 */
#[CoversClass(RecordingStore::class)]
final class RecordingStoreTest extends TestCase
{
	public function testAWriteIsKeptAsThePathItChanged(): void
	{
		$store = self::store(['uid' => 'u1']);

		$store->set('oz_upload_files.ref1', ['name' => 'a.png']);

		self::assertSame(
			[['op' => 'set', 'path' => ['oz_upload_files', 'ref1'], 'value' => ['name' => 'a.png']]],
			$store->recordedOperations()
		);
	}

	/**
	 * The shape that cost a partner their uploads: one entry of a map goes, and the branch stays.
	 */
	public function testRemovingTheLastEntryOfABranchRemovesTheEntry(): void
	{
		$store = self::store(['refs' => ['ref1' => true]]);

		$store->remove('refs.ref1');

		self::assertSame(
			[['op' => 'remove', 'path' => ['refs', 'ref1']]],
			$store->recordedOperations()
		);
		self::assertSame(['refs' => []], $store->getData());
	}

	/**
	 * A branch this request is the first to write is still written one entry at a time.
	 */
	public function testCreatingABranchWritesTheEntry(): void
	{
		$store = self::store([]);

		$store->set('refs.ref1', true);

		self::assertSame(
			[['op' => 'set', 'path' => ['refs', 'ref1'], 'value' => true]],
			$store->recordedOperations()
		);
	}

	/**
	 * An entry under a numeric id is a member of a map, not a position in a list: nothing about the
	 * write says otherwise, so nothing has to be read off the values to find out.
	 */
	public function testAnEntryUnderANumericIdIsAMember(): void
	{
		$store = self::store(['blocks' => ['12' => ['title' => 'a']]]);

		$store->set('blocks.12.title', 'b');

		self::assertSame(
			[['op' => 'set', 'path' => ['blocks', '12', 'title'], 'value' => 'b']],
			$store->recordedOperations()
		);
	}

	public function testManyWritesAreKeptInOrder(): void
	{
		$store = self::store(['a' => 1]);

		$store->set('b', 2)
			->remove('a')
			->set('c.d', 3);

		self::assertSame(
			[['set', ['b']], ['remove', ['a']], ['set', ['c', 'd']]],
			\array_map(
				static fn (array $o): array => [$o['op'], $o['path']],
				$store->recordedOperations()
			)
		);
	}

	/**
	 * `merge()` and the array syntax both go through `set()`, so neither escapes the record.
	 */
	public function testMergeAndArrayAccessAreRecorded(): void
	{
		$store = self::store(['oz' => ['lang' => 'en']]);

		$store->merge(['oz' => ['lang' => 'fr']]);
		$store['flag'] = true;
		unset($store['gone']);

		self::assertSame(
			[['set', ['oz']], ['set', ['flag']], ['remove', ['gone']]],
			\array_map(
				static fn (array $o): array => [$o['op'], $o['path']],
				$store->recordedOperations()
			)
		);
	}

	/**
	 * Replacing the whole document is not a change to the one that was there, and saying it is would
	 * be the lost update the record exists to prevent.
	 */
	public function testReplacingTheWholeDocumentAnswersNoPaths(): void
	{
		$store = self::store(['a' => 1]);

		$store->setData(['b' => 2]);

		self::assertNull($store->recordedPatch());
	}

	/**
	 * A write that goes around the store is noticed, rather than being quietly left out of the
	 * UPDATE: the caller is told to work the change out another way.
	 */
	public function testAWriteAroundTheStoreAnswersNoPaths(): void
	{
		$data  = ['a' => ['b' => 1]];
		$store = self::store($data);

		$store->set('c', 2);
		self::assertNotNull($store->recordedPatch(), 'the recorded write did not account for the document');

		// Reaching the data and changing it in place, which is what the store cannot see.
		$held = $store->getData();
		self::assertIsArray($held);

		$store->setData($held + ['sneaked' => true]);
		self::assertNull($store->recordedPatch());
	}

	public function testAnUntouchedStoreHasNothingToWrite(): void
	{
		$store = self::store(['a' => 1]);

		self::assertSame([], $store->recordedOperations());
		self::assertFalse($store->recordedPatch()?->hasOperations());
	}

	/**
	 * The key order a document comes back in is not a change to it.
	 */
	public function testKeyOrderIsNotAChange(): void
	{
		$store = self::store(['b' => 1, 'a' => ['y' => 1, 'x' => 2]]);

		$store->set('a.z', 3);

		self::assertNotNull($store->recordedPatch());
	}

	// =========================================================================
	// A branch of entries
	// =========================================================================

	/**
	 * An entry's name is a member's name, whatever it looks like: a branch keyed by numeric ids is
	 * written as members and never as positions in a list.
	 */
	public function testAnEntryNameIsAlwaysAMember(): void
	{
		$store = self::store([]);

		$store->entries('refs')->set('12', ['a' => 1]);

		self::assertSame(
			[['op' => 'set', 'path' => ['refs', '12'], 'value' => ['a' => 1]]],
			$store->recordedOperations()
		);
		self::assertSame(['refs' => ['12' => ['a' => 1]]], $store->getData());
	}

	public function testAnEntryIsRemovedOnItsOwn(): void
	{
		$store = self::store(['refs' => ['a' => 1, 'b' => 2]]);

		$store->entries('refs')->remove('a');

		self::assertSame([['op' => 'remove', 'path' => ['refs', 'a']]], $store->recordedOperations());
		self::assertSame(['refs' => ['b' => 2]], $store->getData());
	}

	public function testABranchOfEntriesReadsBackWhatItHolds(): void
	{
		$store   = self::store(['oz' => ['refs' => ['a' => 1]]]);
		$entries = $store->entries('oz.refs');

		$entries->set('b', 2);

		self::assertSame(['a' => 1, 'b' => 2], $entries->all());
		self::assertSame(['a', 'b'], $entries->names());
		self::assertSame(2, $entries->get('b'));
		self::assertTrue($entries->has('a'));
		self::assertFalse($entries->has('zz'));
		self::assertNull($entries->get('zz'));
		self::assertSame([['op' => 'set', 'path' => ['oz', 'refs', 'b'], 'value' => 2]], $store->recordedOperations());
	}

	/**
	 * A name holding a dot is one entry, where a dot path would read it as two.
	 */
	public function testAnEntryNameHoldingADotStaysOneEntry(): void
	{
		$store = self::store([]);

		$store->entries('refs')->set('a.b', 1);

		self::assertSame([['op' => 'set', 'path' => ['refs', 'a.b'], 'value' => 1]], $store->recordedOperations());
		self::assertSame(['refs' => ['a.b' => 1]], $store->getData());
	}

	private static function store(array $data): RecordingStore
	{
		return new class($data) extends RecordingStore {};
	}
}
