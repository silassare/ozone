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
use OZONE\Core\Stores\Drivers\FileStore;
use OZONE\Core\Stores\Drivers\MemcachedStore;
use OZONE\Core\Stores\Drivers\MemoryStore;
use OZONE\Core\Stores\Interfaces\StoreDriverInterface;
use OZONE\Core\Stores\KeyValueStore;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Class StoreVersionsTest.
 *
 * What every store that keeps a version does the same way, and what two writers must not both
 * believe they did.
 *
 * A conditional write is the one operation a store written from two sides cannot do without: a
 * device that filled a form offline sends back the version it had, and a write that arrives after
 * somebody else's must be refused rather than quietly losing a value it never saw. Every case here
 * is a way that goes wrong.
 *
 * @internal
 */
#[CoversNothing]
final class StoreVersionsTest extends TestCase
{
	/** @var list<int> the processes the last `inProcesses()` started */
	private static array $children = [];

	/**
	 * @param callable(): KeyValueStore $make
	 */
	#[DataProvider('provideStores')]
	public function testAWriteGivesTheEntryAVersion(callable $make): void
	{
		$store = $make();

		$store->set('who', 'Awa');

		$first = $store->entry('who');

		self::assertNotNull($first);
		self::assertNotNull($first->version);
		self::assertGreaterThan(0, $first->version);

		$store->set('who', 'Kofi');

		$second = $store->entry('who');

		// A write that did not change the version is a store that cannot be written from two
		// sides: a second writer would see its own value as the one it had read.
		self::assertNotNull($second);
		self::assertGreaterThan($first->version, $second->version);
	}

	/**
	 * @param callable(): KeyValueStore $make
	 */
	#[DataProvider('provideStores')]
	public function testAConditionalWriteAppliesOnTheVersionItRead(callable $make): void
	{
		$store = $make();

		$store->set('who', 'Awa');

		$read = $store->entry('who');

		self::assertNotNull($read);
		self::assertNotNull($read->version);

		$write = $store->setIf('who', 'Kofi', $read->version);

		self::assertTrue($write->applied);
		self::assertSame('Kofi', $store->get('who'));
		// The new version comes back with the answer: a caller that had to read again to learn it
		// would be reading something that may have moved once more.
		self::assertSame($write->version(), $store->entry('who')?->version);
		self::assertGreaterThan($read->version, $write->version());
	}

	/**
	 * @param callable(): KeyValueStore $make
	 */
	#[DataProvider('provideStores')]
	public function testAConditionalWriteIsRefusedOnAVersionThatMoved(callable $make): void
	{
		$store = $make();

		$store->set('who', 'Awa');

		$stale = $store->entry('who')?->version;

		self::assertNotNull($stale);

		// Somebody else wrote meanwhile.
		$store->set('who', 'Kofi');

		$write = $store->setIf('who', 'Ama', $stale);

		self::assertFalse($write->applied);
		// Refused **with what is there**, which is what the writer needs to decide what to do.
		self::assertSame('Kofi', $write->entry?->value);
		self::assertSame('Kofi', $store->get('who'));
		self::assertNotSame($stale, $write->version());
	}

	/**
	 * @param callable(): KeyValueStore $make
	 */
	#[DataProvider('provideStores')]
	public function testACreateWritesOnlyAKeyHoldingNothing(callable $make): void
	{
		$store = $make();

		$created = $store->setIf('who', 'Awa', null);

		self::assertTrue($created->applied);
		self::assertSame('Awa', $store->get('who'));

		$again = $store->setIf('who', 'Kofi', null);

		self::assertFalse($again->applied);
		self::assertSame('Awa', $store->get('who'));
		self::assertSame('Awa', $again->entry?->value);
	}

	/**
	 * @param callable(): KeyValueStore $make
	 */
	#[DataProvider('provideStores')]
	public function testACreateWritesAKeyThatWasRemoved(callable $make): void
	{
		$store = $make();

		$store->set('who', 'Awa');
		$store->delete('who');

		$created = $store->setIf('who', 'Kofi', null);

		// A key that was removed holds nothing, whatever the store keeps of the removal: a client
		// creating it again must not be refused by a tombstone it cannot see.
		self::assertTrue($created->applied);
		self::assertSame('Kofi', $store->get('who'));
	}

	/**
	 * @param callable(): KeyValueStore $make
	 */
	#[DataProvider('provideStores')]
	public function testAConditionalRemovalNeedsTheVersionItRead(callable $make): void
	{
		$store = $make();

		$store->set('who', 'Awa');

		$stale = $store->entry('who')?->version;

		self::assertNotNull($stale);

		$store->set('who', 'Kofi');

		$refused = $store->deleteIf('who', $stale);

		self::assertFalse($refused->applied);
		self::assertSame('Kofi', $store->get('who'));

		$applied = $store->deleteIf('who', $store->entry('who')?->version ?? 0);

		self::assertTrue($applied->applied);
		self::assertNull($store->get('who'));
		self::assertNull($applied->entry);
	}

	/**
	 * @param callable(): KeyValueStore $make
	 */
	#[DataProvider('provideStores')]
	public function testAConditionalWriteOnAKeyHoldingNothingIsRefused(callable $make): void
	{
		$store = $make();

		$write = $store->setIf('gone', 'Awa', 12_345);

		self::assertFalse($write->applied);
		self::assertNull($write->entry);
		self::assertNull($store->get('gone'));
	}

	public function testOnlyOneOfSeveralProcessesWritingOnOneVersionWins(): void
	{
		$namespace = self::namespace();
		$store     = self::kv(new FileStore($namespace));

		$store->set('who', 'Awa');

		$read = $store->entry('who')?->version;

		self::assertNotNull($read);

		// Six processes send the same conditional write, each having read the same version. One
		// value must survive and five must be told they lost: that is the whole promise, and a
		// read-then-write that is not one act under a lock keeps more than one of them.
		self::inProcesses(6, static function () use ($namespace, $read): void {
			$own = self::kv(new FileStore($namespace));

			$write = $own->setIf('who', 'from:' . \getmypid(), $read);

			// Each child says what it was told, in a key of its own: the parent counts them.
			$own->set('said:' . \getmypid(), $write->applied);
		});

		$applied = 0;

		foreach (self::$children as $pid) {
			if (true === $store->get('said:' . $pid)) {
				++$applied;
			}
		}

		self::assertSame(1, $applied);
		self::assertStringStartsWith('from:', (string) $store->get('who'));
	}

	public function testAStoreThatKeepsNoVersionSaysSoRatherThanWritingAnyway(): void
	{
		$store = self::kv(MemcachedStore::fromConfig('versions_' . \bin2hex(\random_bytes(4))));

		// Said, not treated as an ordinary write: a caller whose conditional write was quietly
		// unconditional has lost the only thing it asked for and would never know.
		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessageMatches('/keeps a version per entry/');

		$store->setIf('who', 'Awa', null);
	}

	/**
	 * The stores that keep a version: every driver that promises `versioned`.
	 *
	 * @return iterable<string, array{callable(): KeyValueStore}>
	 */
	public static function provideStores(): iterable
	{
		yield 'memory' => [static fn (): KeyValueStore => self::kv(new MemoryStore(self::namespace()))];

		yield 'file' => [static fn (): KeyValueStore => self::kv(new FileStore(self::namespace()))];

		yield 'database' => [static fn (): KeyValueStore => self::kv(new DbStore(self::namespace()))];

		// The database again, keeping what changed: the conditional writes must behave the same
		// whether or not removals are remembered, since a tombstone is a key holding nothing.
		yield 'database with changes' => [
			static fn (): KeyValueStore => self::kv(new DbStore(self::namespace(), true)),
		];
	}

	private static function kv(StoreDriverInterface $driver): KeyValueStore
	{
		return new KeyValueStore('test', $driver);
	}

	private static function namespace(): string
	{
		return 'versions:' . \bin2hex(\random_bytes(4));
	}

	/**
	 * Runs work in several processes at once, and waits for them all.
	 *
	 * @param callable(): void $work
	 */
	private static function inProcesses(int $count, callable $work): void
	{
		self::$children = [];

		for ($i = 0; $i < $count; ++$i) {
			$pid = \pcntl_fork();

			if (0 === $pid) {
				try {
					$work();
				} finally {
					// Gone at once: nothing of the test runner's runs again in a child.
					\posix_kill(\posix_getpid(), \SIGKILL);
				}
			}

			self::$children[] = $pid;
		}

		foreach (self::$children as $pid) {
			\pcntl_waitpid($pid, $status);
		}
	}
}
