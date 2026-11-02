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
use OZONE\Core\Stores\CacheRegistry;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use OZONE\Core\Stores\KeyValueStore;

/**
 * `remember()` is the framework's lazy memo, and the factory handed to it is a caller's code.
 *
 * @internal
 */
#[CoversClass(KeyValueStore::class)]
final class RememberReentrancyTest extends TestCase
{
	public function testAFactoryThatReadsItsOwnKeyIsStopped(): void
	{
		// The entry is written once the factory has answered, so it says nothing while the factory
		// runs: a factory asking for the same key used to get another miss and run again, from
		// inside itself. The factories that matter do real work -- a query, a directory scan, a
		// `require` of project files -- so each turn cost one of those.
		$store = CacheRegistry::runtime('test.remember.' . \bin2hex(\random_bytes(4)));

		try {
			$store->remember('loop', static function () use ($store): string {
				return $store->remember('loop', static fn (): string => 'never reached');
			});

			self::fail('A factory reading its own key must be stopped.');
		} catch (RuntimeException $e) {
			self::assertStringContainsString('while it was being computed', $e->getMessage());
		}
	}

	public function testTheRegisterIsClearedSoTheKeyWorksAfterwards(): void
	{
		$store = CacheRegistry::runtime('test.remember.' . \bin2hex(\random_bytes(4)));

		try {
			$store->remember('k', static function (): string {
				throw new RuntimeException('the factory failed');
			});
		} catch (RuntimeException) {
			// expected
		}

		// A factory that threw must not leave the key marked as being computed for ever.
		self::assertSame('second try', $store->remember('k', static fn (): string => 'second try'));
	}

	public function testOneFactoryMayReadAnotherKey(): void
	{
		$store = CacheRegistry::runtime('test.remember.' . \bin2hex(\random_bytes(4)));

		$value = $store->remember('outer', static fn (): string => 'outer+' . $store->remember(
			'inner',
			static fn (): string => 'inner'
		));

		self::assertSame('outer+inner', $value);
	}
}
