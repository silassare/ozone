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

use OZONE\Core\Stores\Drivers\RedisStore;
use OZONE\Core\Stores\StoreEntry;
use OZONE\Tests\Support\RequiresRedisTrait;
use PHPUnit\Framework\TestCase;

/**
 * Class RedisStoreTest.
 *
 * Needs ext-redis and a Redis server (`oz.redis`); run with `make test-redis`.
 *
 * @internal
 *
 * @group redis
 *
 * @covers \OZONE\Core\Stores\Drivers\RedisStore
 */
final class RedisStoreTest extends TestCase
{
	use RequiresRedisTrait;

	private RedisStore $cache;

	protected function setUp(): void
	{
		self::requireRedis();

		$this->cache = RedisStore::fromConfig('oz_test_' . \bin2hex(\random_bytes(4)));
	}

	protected function tearDown(): void
	{
		if (isset($this->cache)) {
			$this->cache->clear();
		}
	}

	public function testSetGetAndDelete(): void
	{
		$this->cache->set(new StoreEntry('hello', ['world' => true]));

		self::assertSame(['world' => true], $this->cache->get('hello')?->value);

		$this->cache->delete('hello');

		self::assertNull($this->cache->get('hello'));
	}

	public function testMissingKeyIsAMiss(): void
	{
		self::assertNull($this->cache->get('missing'));
	}

	public function testEntryKeepsItsExpiry(): void
	{
		$this->cache->set(StoreEntry::forTTL('ttl', 'v', 60));

		$entry = $this->cache->get('ttl');

		self::assertNotNull($entry);
		self::assertNotNull($entry->expiresAt);
		self::assertFalse($entry->isExpired());
	}

	public function testIncrementIsCumulative(): void
	{
		$this->cache->set(new StoreEntry('n', 1));
		$this->cache->increment('n', 2);
		$this->cache->increment('n');

		self::assertSame(4, $this->cache->get('n')?->value);
	}

	public function testClearOnlyTouchesItsNamespace(): void
	{
		$other = RedisStore::fromConfig('oz_test_other_' . \bin2hex(\random_bytes(4)));
		$other->set(new StoreEntry('kept', 1));
		$this->cache->set(new StoreEntry('dropped', 1));

		$this->cache->clear();

		self::assertNull($this->cache->get('dropped'));
		self::assertNotNull($other->get('kept'));

		$other->clear();
	}
}
