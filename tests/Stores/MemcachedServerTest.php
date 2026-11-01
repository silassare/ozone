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

use Override;
use OZONE\Core\Stores\Drivers\MemcachedStore;
use OZONE\Core\Stores\StoreEntry;
use OZONE\Core\Testing\Traits\RequiresMemcachedTrait;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Class MemcachedServerTest.
 *
 * The Memcached driver against a real server (`oz.memcached`); run by `make test-services`.
 *
 * Nothing exercised this driver before, which is how it came to hold two faults at once: it named
 * no server, so it could reach none, and it gave an expiry in milliseconds, so what it did write
 * never expired. Both are the kind only a server can answer for.
 *
 * @internal
 */
#[Group('memcached')]
#[CoversClass(MemcachedStore::class)]
final class MemcachedServerTest extends TestCase
{
	use RequiresMemcachedTrait;

	private ?MemcachedStore $cache = null;

	#[Override]
	protected function setUp(): void
	{
		self::requireMemcached();

		$this->cache = MemcachedStore::fromConfig('oz_test_' . \bin2hex(\random_bytes(4)));
	}

	#[Override]
	protected function tearDown(): void
	{
		$this->cache?->clear();
	}

	public function testSetGetAndDelete(): void
	{
		self::assertTrue($this->cache()->set(new StoreEntry('hello', ['world' => true])));

		self::assertSame(['world' => true], $this->cache()->get('hello')?->value);

		$this->cache()->delete('hello');

		self::assertNull($this->cache()->get('hello'));
	}

	public function testAnEntryKeepsItsExpiry(): void
	{
		$expires_at = \microtime(true) + 600.0;

		$this->cache()->set(new StoreEntry('kept', 'value', $expires_at));

		$entry = $this->cache()->get('kept');

		self::assertInstanceOf(StoreEntry::class, $entry);
		self::assertSame($expires_at, $entry->expiresAt);
	}

	public function testAnEntryIsGoneFromTheServerOnceItsLifeIsOver(): void
	{
		// The whole of what the millisecond expiry cost: the entry was given a timestamp tens of
		// millennia away, so the server kept it and only an eviction ever took it back. Read through
		// the driver an expired entry looked gone -- it compares the expiry it stored alongside --
		// which is why nothing short of asking the server could see this.
		$this->cache()->set(new StoreEntry('short', 'value', \microtime(true) + 1.0));

		self::assertSame('value', $this->cache()->get('short')?->value);

		// Memcached counts whole seconds, so the second after the one it expires in is the first
		// that is certainly past it.
		\sleep(2);

		self::assertNull($this->cache()->get('short'), 'the driver reports it as gone');

		// And the server itself no longer holds it, which is the half the driver cannot fake.
		self::assertSame([], $this->cache()->getMultiple(['short']));
	}

	public function testCountsAcrossCalls(): void
	{
		self::assertSame(1, $this->cache()->add('hits', 1, true, \microtime(true) + 600.0));
		self::assertSame(3, $this->cache()->add('hits', 2, true, \microtime(true) + 600.0));
		self::assertSame(3, $this->cache()->get('hits')?->value);
	}

	public function testAStoreOnlyAnswersForItsOwnNamespace(): void
	{
		$other = MemcachedStore::fromConfig('oz_test_' . \bin2hex(\random_bytes(4)));

		$this->cache()->set(new StoreEntry('shared', 'mine'));
		$other->set(new StoreEntry('shared', 'theirs'));

		self::assertSame('mine', $this->cache()->get('shared')?->value);
		self::assertSame('theirs', $other->get('shared')?->value);

		$other->clear();
	}

	/**
	 * The store this test writes to, made in `setUp()`.
	 */
	private function cache(): MemcachedStore
	{
		$cache = $this->cache;

		self::assertNotNull($cache, 'the store was not created');

		return $cache;
	}
}
