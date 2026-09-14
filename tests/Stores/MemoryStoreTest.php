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

use OZONE\Core\Stores\Drivers\MemoryStore;
use OZONE\Core\Stores\StoreEntry;
use PHPUnit\Framework\TestCase;

/**
 * Class MemoryStoreTest.
 *
 * @internal
 *
 * @covers \OZONE\Core\Stores\Drivers\MemoryStore
 */
final class MemoryStoreTest extends TestCase
{
	private MemoryStore $cache;

	protected function setUp(): void
	{
		// Each test gets an isolated namespace so tests do not interfere with each other
		$this->cache = new MemoryStore('test_' . \spl_object_id($this));
		$this->cache->clear();
	}

	public function testSetAndGet(): void
	{
		$entry = new StoreEntry('hello', 'world');
		$this->cache->set($entry);

		$retrieved = $this->cache->get('hello');
		self::assertNotNull($retrieved);
		self::assertSame('world', $retrieved->value);
	}

	public function testGetReturnsNullForMissingKey(): void
	{
		self::assertNull($this->cache->get('missing'));
	}

	public function testGetReturnsNullForExpiredItem(): void
	{
		$entry = StoreEntry::forTTL('expired', 'value', -1.0); // expired in the past
		$this->cache->set($entry);

		self::assertNull($this->cache->get('expired'));
	}

	public function testDeleteRemovesItem(): void
	{
		$this->cache->set(new StoreEntry('key', 'val'));
		$this->cache->delete('key');

		self::assertNull($this->cache->get('key'));
	}

	public function testClearEmptiesAllItems(): void
	{
		$this->cache->set(new StoreEntry('a', 1));
		$this->cache->set(new StoreEntry('b', 2));
		$this->cache->clear();

		self::assertNull($this->cache->get('a'));
		self::assertNull($this->cache->get('b'));
	}

	public function testGetMultipleReturnsOnlyExistingItems(): void
	{
		$this->cache->set(new StoreEntry('x', 10));
		$this->cache->set(new StoreEntry('y', 20));

		$items = $this->cache->getMultiple(['x', 'y', 'z']);
		self::assertCount(2, $items);
		self::assertArrayHasKey('x', $items);
		self::assertArrayHasKey('y', $items);
		self::assertArrayNotHasKey('z', $items);
	}

	public function testDeleteMultipleRemovesMultipleItems(): void
	{
		$this->cache->set(new StoreEntry('a', 1));
		$this->cache->set(new StoreEntry('b', 2));
		$this->cache->set(new StoreEntry('c', 3));
		$this->cache->deleteMultiple(['a', 'b']);

		self::assertNull($this->cache->get('a'));
		self::assertNull($this->cache->get('b'));
		self::assertNotNull($this->cache->get('c'));
	}

	public function testIncrementIncreasesValue(): void
	{
		$this->cache->set(new StoreEntry('counter', 10));
		$this->cache->increment('counter', 5);

		$entry = $this->cache->get('counter');
		self::assertNotNull($entry);
		self::assertSame(15.0, $entry->value);
	}

	public function testIncrementReturnsFalseForMissingKey(): void
	{
		self::assertFalse($this->cache->increment('nonexistent'));
	}

	public function testDecrementDecreasesValue(): void
	{
		$this->cache->set(new StoreEntry('counter', 10));
		$this->cache->decrement('counter', 3);

		$entry = $this->cache->get('counter');
		self::assertNotNull($entry);
		self::assertSame(7.0, $entry->value);
	}

	public function testDecrementReturnsFalseForMissingKey(): void
	{
		self::assertFalse($this->cache->decrement('nonexistent'));
	}

	public function testFromConfigReturnsSameNamespace(): void
	{
		$a = MemoryStore::fromConfig('shared_ns');
		$a->set(new StoreEntry('ping', 'pong'));

		$b     = MemoryStore::fromConfig('shared_ns');
		$entry = $b->get('ping');
		self::assertNotNull($entry);
		self::assertSame('pong', $entry->value);

		// Cleanup
		$a->clear();
	}

	public function testDifferentNamespacesAreIsolated(): void
	{
		$ns1 = new MemoryStore('ns1_isolation');
		$ns2 = new MemoryStore('ns2_isolation');

		$ns1->set(new StoreEntry('k', 'from_ns1'));

		self::assertNull($ns2->get('k'));

		$ns1->clear();
	}
}
