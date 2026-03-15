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

namespace OZONE\Tests\Cache;

use OZONE\Core\Cache\CacheItem;
use OZONE\Core\Cache\Drivers\RuntimeCache;
use PHPUnit\Framework\TestCase;

/**
 * Class RuntimeCacheTest.
 *
 * @internal
 *
 * @coversNothing
 */
final class RuntimeCacheTest extends TestCase
{
	private RuntimeCache $cache;

	protected function setUp(): void
	{
		// Each test gets an isolated namespace so tests do not interfere with each other
		$this->cache = new RuntimeCache('test_' . \spl_object_id($this));
		$this->cache->clear();
	}

	public function testSetAndGet(): void
	{
		$item = new CacheItem('hello', 'world');
		$this->cache->set($item);

		$retrieved = $this->cache->get('hello');
		self::assertNotNull($retrieved);
		self::assertSame('world', $retrieved->get());
	}

	public function testGetReturnsNullForMissingKey(): void
	{
		self::assertNull($this->cache->get('missing'));
	}

	public function testGetReturnsNullForExpiredItem(): void
	{
		$item = (new CacheItem('expired', 'value'))->expiresAfter(-1.0); // expired in the past
		$this->cache->set($item);

		self::assertNull($this->cache->get('expired'));
	}

	public function testDeleteRemovesItem(): void
	{
		$this->cache->set(new CacheItem('key', 'val'));
		$this->cache->delete('key');

		self::assertNull($this->cache->get('key'));
	}

	public function testClearEmptiesAllItems(): void
	{
		$this->cache->set(new CacheItem('a', 1));
		$this->cache->set(new CacheItem('b', 2));
		$this->cache->clear();

		self::assertNull($this->cache->get('a'));
		self::assertNull($this->cache->get('b'));
	}

	public function testGetMultipleReturnsOnlyExistingItems(): void
	{
		$this->cache->set(new CacheItem('x', 10));
		$this->cache->set(new CacheItem('y', 20));

		$items = $this->cache->getMultiple(['x', 'y', 'z']);
		self::assertCount(2, $items);
		self::assertArrayHasKey('x', $items);
		self::assertArrayHasKey('y', $items);
		self::assertArrayNotHasKey('z', $items);
	}

	public function testDeleteMultipleRemovesMultipleItems(): void
	{
		$this->cache->set(new CacheItem('a', 1));
		$this->cache->set(new CacheItem('b', 2));
		$this->cache->set(new CacheItem('c', 3));
		$this->cache->deleteMultiple(['a', 'b']);

		self::assertNull($this->cache->get('a'));
		self::assertNull($this->cache->get('b'));
		self::assertNotNull($this->cache->get('c'));
	}

	public function testIncrementIncreasesValue(): void
	{
		$this->cache->set(new CacheItem('counter', 10));
		$this->cache->increment('counter', 5);

		$item = $this->cache->get('counter');
		self::assertNotNull($item);
		self::assertSame(15.0, $item->get());
	}

	public function testIncrementReturnsFalseForMissingKey(): void
	{
		self::assertFalse($this->cache->increment('nonexistent'));
	}

	public function testDecrementDecreasesValue(): void
	{
		$this->cache->set(new CacheItem('counter', 10));
		$this->cache->decrement('counter', 3);

		$item = $this->cache->get('counter');
		self::assertNotNull($item);
		self::assertSame(7.0, $item->get());
	}

	public function testDecrementReturnsFalseForMissingKey(): void
	{
		self::assertFalse($this->cache->decrement('nonexistent'));
	}

	public function testGetSharedInstanceReturnsSameNamespace(): void
	{
		$a = RuntimeCache::getSharedInstance('shared_ns');
		$a->set(new CacheItem('ping', 'pong'));

		$b    = RuntimeCache::getSharedInstance('shared_ns');
		$item = $b->get('ping');
		self::assertNotNull($item);
		self::assertSame('pong', $item->get());

		// Cleanup
		$a->clear();
	}

	public function testDifferentNamespacesAreIsolated(): void
	{
		$ns1 = new RuntimeCache('ns1_isolation');
		$ns2 = new RuntimeCache('ns2_isolation');

		$ns1->set(new CacheItem('k', 'from_ns1'));

		self::assertNull($ns2->get('k'));

		$ns1->clear();
	}
}
