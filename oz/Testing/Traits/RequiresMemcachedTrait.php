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

namespace OZONE\Core\Testing\Traits;

use Memcached;
use OZONE\Core\Stores\Drivers\MemcachedStore;
use OZONE\Core\Stores\StoreEntry;
use Throwable;

/**
 * Trait RequiresMemcachedTrait.
 *
 * For tests of the `memcached` group: they are skipped without a reachable Memcached server, and
 * fail instead when `OZ_TEST_MEMCACHED_REQUIRED` is set (`make test-services`), so a CI job that
 * requires Memcached cannot silently pass without it.
 */
trait RequiresMemcachedTrait
{
	/**
	 * Skips (or fails) the test unless ext-memcached is loaded and the server answers.
	 */
	protected static function requireMemcached(): void
	{
		if (!\class_exists(Memcached::class)) {
			self::memcachedUnavailable('ext-memcached is not loaded.');
		}

		try {
			$store = MemcachedStore::fromConfig('oz_probe_' . \bin2hex(\random_bytes(4)));

			// A write, not a version string: a server listed but unreachable answers the second one
			// from the client's own list, and says nothing about whether it can be written to.
			if (!$store->set(new StoreEntry('probe', 1, \microtime(true) + 10))) {
				self::memcachedUnavailable('Memcached refused a write.');
			}

			$store->clear();
		} catch (Throwable $t) {
			self::memcachedUnavailable('Memcached is unreachable: ' . $t->getMessage());
		}
	}

	/**
	 * Skips the test, or fails it when Memcached is required.
	 */
	protected static function memcachedUnavailable(string $reason): never
	{
		if (\getenv('OZ_TEST_MEMCACHED_REQUIRED')) {
			self::fail($reason . ' (OZ_TEST_MEMCACHED_REQUIRED is set)');
		}

		self::markTestSkipped($reason);
	}
}
