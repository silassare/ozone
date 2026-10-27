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
use OZONE\Core\Stores\Drivers\FileStore;
use OZONE\Core\Stores\Drivers\MemoryStore;
use OZONE\Core\Stores\Interfaces\StoreDriverInterface;
use OZONE\Core\Stores\KeyValueStore;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Class StoreCountingTest.
 *
 * What every store counts the same way, and what several processes counting at once must not lose.
 *
 * @internal
 */
#[CoversNothing]
final class StoreCountingTest extends TestCase
{
	/** @var list<int> the processes the last `inProcesses()` started */
	private static array $children = [];

	/**
	 * @param callable(): KeyValueStore $make
	 */
	#[DataProvider('provideStores')]
	public function testCountsFromItsCreationAndAnswersTheTotal(callable $make): void
	{
		$store = $make();

		self::assertSame(1, $store->count('hits', 1, 60));
		self::assertSame(3, $store->count('hits', 2, 60));
		self::assertSame(2.5, $store->count('hits', -0.5));
		self::assertSame(2.5, $store->get('hits'));
	}

	/**
	 * @param callable(): KeyValueStore $make
	 */
	#[DataProvider('provideStores')]
	public function testIncrementsOnlyWhatExists(callable $make): void
	{
		$store = $make();

		self::assertFalse($store->increment('none'));

		$store->set('n', 10);

		self::assertSame(15, $store->increment('n', 5));
		self::assertSame(12, $store->decrement('n', 3));
		self::assertSame(12, $store->get('n'));
	}

	/**
	 * @param callable(): KeyValueStore $make
	 */
	#[DataProvider('provideStores')]
	public function testAnExpiredCounterStartsAgain(callable $make): void
	{
		$store = $make();

		$store->count('window', 5, 1);
		\usleep(1_100_000);

		self::assertSame(1, $store->count('window', 1, 60));
	}

	/**
	 * @param callable(): KeyValueStore $make
	 */
	#[DataProvider('provideStores')]
	public function testRefusesToAddToWhatIsNotANumber(callable $make): void
	{
		$store = $make();

		$store->set('word', 'hello');

		$this->expectException(RuntimeException::class);

		$store->increment('word');
	}

	/**
	 * @return iterable<string, array{callable(): KeyValueStore}>
	 */
	public static function provideStores(): iterable
	{
		yield 'memory' => [static fn (): KeyValueStore => self::kv(new MemoryStore(self::namespace()))];

		yield 'file' => [static fn (): KeyValueStore => self::kv(new FileStore(self::namespace()))];
	}

	public function testFileStoreCountsEveryAddOfSeveralProcesses(): void
	{
		$namespace = self::namespace();

		self::inProcesses(6, static function () use ($namespace): void {
			$store = self::kv(new FileStore($namespace));

			for ($i = 0; $i < 40; ++$i) {
				$store->count('hits', 1, 600);
			}
		});

		self::assertSame(240, self::kv(new FileStore($namespace))->get('hits'));
	}

	public function testFileStoreKeepsWhatOtherProcessesWrote(): void
	{
		$namespace = self::namespace();

		self::inProcesses(6, static function () use ($namespace): void {
			$store = self::kv(new FileStore($namespace));

			for ($i = 0; $i < 30; ++$i) {
				$store->set('from:' . \getmypid() . ':' . $i, true);
			}
		});

		$store = self::kv(new FileStore($namespace));
		$lost  = [];

		foreach (self::$children as $pid) {
			for ($i = 0; $i < 30; ++$i) {
				if (true !== $store->get('from:' . $pid . ':' . $i)) {
					$lost[] = $pid . ':' . $i;
				}
			}
		}

		self::assertSame([], $lost);
	}

	private static function kv(StoreDriverInterface $driver): KeyValueStore
	{
		return new KeyValueStore('test', $driver);
	}

	private static function namespace(): string
	{
		return 'count:' . \bin2hex(\random_bytes(4));
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
