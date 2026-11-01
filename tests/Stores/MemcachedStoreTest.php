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

use OZONE\Core\Stores\Drivers\MemcachedStore;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Class MemcachedStoreTest.
 *
 * The expiry a Memcached entry is given. The driver itself is not exercised here: the suite runs no
 * Memcached server, and the rule below is what was wrong -- an absolute microtime multiplied by a
 * thousand, which Memcached read as a timestamp tens of millennia away.
 *
 * @internal
 */
#[CoversClass(MemcachedStore::class)]
final class MemcachedStoreTest extends TestCase
{
	public function testAShortLifeIsSentAsTheSecondsItHasLeft(): void
	{
		$expiration = MemcachedStore::expiration(\microtime(true) + 60.0);

		// Seconds, not milliseconds, and a delay rather than a date: below Memcached's 30 days it
		// reads the number as the time the entry has left, which no clock difference can stretch.
		self::assertGreaterThan(0, $expiration);
		self::assertLessThanOrEqual(60, $expiration);
	}

	public function testALifeLongerThanMemcachedReadsAsADelayIsSentAsTheTimeItEndsAt(): void
	{
		$ends_at = \microtime(true) + MemcachedStore::RELATIVE_LIMIT + 60.0;

		// Sent as a delay it would be read as an absolute time in January 1970, so the entry would
		// be gone at once: over 30 days the absolute time is the only form that is read as meant.
		self::assertSame((int) \ceil($ends_at), MemcachedStore::expiration($ends_at));
	}

	public function testALifeAlreadyOverIsNotTurnedIntoNoExpiryAtAll(): void
	{
		$ended_at = \microtime(true) - 10.0;

		// 0 means "keep for ever" to Memcached, which is what a delay of zero or less would become;
		// a time in the past is what makes it drop the entry.
		$expiration = MemcachedStore::expiration($ended_at);

		self::assertSame((int) \ceil($ended_at), $expiration);
		self::assertGreaterThan(MemcachedStore::RELATIVE_LIMIT, $expiration);
	}
}
