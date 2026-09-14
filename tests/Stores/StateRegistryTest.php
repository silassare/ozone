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

use OZONE\Core\App\Settings;
use OZONE\Core\Exceptions\RuntimeException;
use OZONE\Core\Stores\CacheRegistry;
use OZONE\Core\Stores\Drivers\DbStore;
use OZONE\Core\Stores\Drivers\FileStore;
use OZONE\Core\Stores\Drivers\MemcachedStore;
use OZONE\Core\Stores\Drivers\MemoryStore;
use OZONE\Core\Stores\Interfaces\StoreDriverInterface;
use OZONE\Core\Stores\KeyValueStore;
use OZONE\Core\Stores\StateRegistry;
use OZONE\Core\Stores\StoreEntry;
use PHPUnit\Framework\TestCase;

/**
 * Class StateRegistryTest.
 *
 * @internal
 *
 * @covers \OZONE\Core\Stores\CacheRegistry
 * @covers \OZONE\Core\Stores\StateRegistry
 * @covers \OZONE\Core\Stores\StoreCapabilities
 */
final class StateRegistryTest extends TestCase
{
	public function testTheShippedStateStoresAreDeclared(): void
	{
		$names = StateRegistry::names();

		// The stores whose loss a user would notice.
		self::assertContains('oz:form:sessions', $names);
		self::assertContains('oz:form:resume', $names);
		self::assertContains('oz:rate_limit', $names);
		self::assertContains('oz:auth:digest:nonces', $names);
	}

	public function testARecomputableStoreStaysACache(): void
	{
		self::assertNotContains('oz:fs:image:filters', StateRegistry::names());
		self::assertInstanceOf(KeyValueStore::class, CacheRegistry::store('oz:fs:image:filters'));
	}

	public function testAStateStoreIsUsable(): void
	{
		$store = StateRegistry::store('oz:form:resume');

		$store->set('probe', ['a' => 1]);

		self::assertSame(['a' => 1], $store->get('probe'));

		$store->delete('probe');

		self::assertNull($store->get('probe'));
	}

	public function testTheSameStoreIsReturnedTwice(): void
	{
		self::assertSame(StateRegistry::store('oz:form:resume'), StateRegistry::store('oz:form:resume'));
	}

	public function testAnUndeclaredStateStoreIsRefused(): void
	{
		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessageMatches('~Unknown state store~');

		StateRegistry::store('oz:not:declared:anywhere');
	}

	public function testAStateStoreCannotBeReadAsACache(): void
	{
		// Otherwise the split would be advisory: CacheRegistry falls back to the default persistent
		// driver for an unknown name, so the caller would silently get a second provider.
		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessageMatches('~is a state store, not a cache~');

		CacheRegistry::store('oz:form:sessions');
	}

	/**
	 * @dataProvider provideANonDurableDriverCannotBackAStateStoreCases
	 */
	public function testANonDurableDriverCannotBackAStateStore(string $driver): void
	{
		Settings::set(StateRegistry::SETTINGS_GROUP, 'oz:test:durability', ['driver' => $driver]);

		try {
			$this->expectException(RuntimeException::class);
			$this->expectExceptionMessageMatches('~does not promise durability~');

			StateRegistry::store('oz:test:durability');
		} finally {
			Settings::unset(StateRegistry::SETTINGS_GROUP, 'oz:test:durability');
		}
	}

	/**
	 * @return array<string, array{string}>
	 */
	public static function provideANonDurableDriverCannotBackAStateStoreCases(): iterable
	{
		// Memcached is deliberately absent: it declares itself non-durable, but constructing it needs
		// ext-memcached, so on a host without the extension the refusal here would be about the
		// extension rather than about durability. That the class can be *named* without it is
		// covered by testADriverClassCanBeNamedWithoutItsExtension().
		return [
			'runtime: gone with the process' => [MemoryStore::class],
			'php cache under .ozone/cache'   => [FileStore::class],
		];
	}

	public function testPhpCacheIsDurableOnlyWhenRootedInTheStateDirectory(): void
	{
		// The distinction the whole split rests on: the same driver, two different promises,
		// decided by where it was configured to write.
		self::assertFalse(FileStore::fromConfig('oz:test:cache')->capabilities()->durable);
		self::assertTrue(
			FileStore::fromConfig('oz:test:state', ['root' => FileStore::ROOT_STATE])->capabilities()->durable
		);

		// It is persistent either way: persistence and durability are not the same question.
		self::assertTrue(FileStore::fromConfig('oz:test:cache')->capabilities()->persistent);
	}

	public function testPhpCacheWritesUnderTheStateDirectoryWhenRootedThere(): void
	{
		$store = FileStore::fromConfig('oz:test:state:path', ['root' => FileStore::ROOT_STATE]);

		$store->set(new StoreEntry('k', 'v'));

		$expected = scope()->getStateStoreDir()->getRoot();
		$found    = false;

		foreach (self::filesUnder($expected) as $file) {
			if (\str_ends_with($file, '.cache')) {
				$found = true;

				break;
			}
		}

		self::assertTrue($found, 'No cache file under ' . $expected);
	}

	public function testADriverClassCanBeNamedWithoutItsExtension(): void
	{
		// A file-level `throw` used to fire while the class was being autoloaded, so a settings array
		// naming a driver, or any `is_subclass_of()` check, blew up on a host without the extension.
		self::assertTrue(\class_exists(MemcachedStore::class));
		self::assertTrue(\is_subclass_of(MemcachedStore::class, StoreDriverInterface::class));
	}

	public function testDbIsDurableAndRuntimeNeverIs(): void
	{
		self::assertTrue(DbStore::fromConfig('oz:test:db')->capabilities()->durable);
		self::assertFalse(MemoryStore::fromConfig('oz:test:runtime')->capabilities()->durable);
	}

	/**
	 * @return list<string>
	 */
	private static function filesUnder(string $dir): array
	{
		$found = [];

		foreach (\scandir($dir) ?: [] as $entry) {
			if ('.' === $entry || '..' === $entry) {
				continue;
			}

			$path = $dir . \DIRECTORY_SEPARATOR . $entry;

			$found = \is_dir($path) ? \array_merge($found, self::filesUnder($path)) : \array_merge($found, [$path]);
		}

		return $found;
	}
}
