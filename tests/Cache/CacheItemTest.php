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

use DateInterval;
use OZONE\Core\Cache\CacheItem;
use PHPUnit\Framework\TestCase;

/**
 * Class CacheItemTest.
 *
 * @internal
 *
 * @coversNothing
 */
final class CacheItemTest extends TestCase
{
	public function testGetReturnsStoredValue(): void
	{
		$item = new CacheItem('key', 'value');
		self::assertSame('value', $item->get());
	}

	public function testGetKeyReturnsKey(): void
	{
		$item = new CacheItem('my_key', 'v');
		self::assertSame('my_key', $item->getKey());
	}

	public function testGetExpireReturnsNullWhenNotSet(): void
	{
		$item = new CacheItem('k', 'v');
		self::assertNull($item->getExpire());
	}

	public function testGetExpireReturnsSetExpire(): void
	{
		$expire = \microtime(true) + 60;
		$item   = new CacheItem('k', 'v', $expire);
		self::assertSame($expire, $item->getExpire());
	}

	public function testExpiredReturnsFalseWhenNoExpire(): void
	{
		$item = new CacheItem('k', 'v');
		self::assertFalse($item->expired());
	}

	public function testExpiredReturnsFalseForFutureExpire(): void
	{
		$item = new CacheItem('k', 'v', \microtime(true) + 3600);
		self::assertFalse($item->expired());
	}

	public function testExpiredReturnsTrueForPastExpire(): void
	{
		$item = new CacheItem('k', 'v', \microtime(true) - 1.0);
		self::assertTrue($item->expired());
	}

	public function testSetUpdatesValue(): void
	{
		$item = new CacheItem('k', 'initial');
		$item->set('updated');
		self::assertSame('updated', $item->get());
	}

	public function testSetReturnsSelf(): void
	{
		$item = new CacheItem('k', 'v');
		self::assertSame($item, $item->set('new'));
	}

	public function testExpiresAfterWithFloat(): void
	{
		$item = new CacheItem('k', 'v');
		$item->expiresAfter(60.0);

		$expire = $item->getExpire();
		self::assertNotNull($expire);
		self::assertGreaterThan(\microtime(true), $expire);
		// Should expire roughly 60 seconds from now
		self::assertLessThan(\microtime(true) + 61, $expire);
	}

	public function testExpiresAfterWithNull(): void
	{
		$item = new CacheItem('k', 'v', \microtime(true) + 3600);
		$item->expiresAfter(null);
		self::assertNull($item->getExpire());
	}

	public function testExpiresAfterWithDateInterval(): void
	{
		$item = new CacheItem('k', 'v');
		$item->expiresAfter(new DateInterval('PT30S')); // 30 seconds

		$expire = $item->getExpire();
		self::assertNotNull($expire);
		self::assertGreaterThan(\microtime(true), $expire);
		self::assertLessThan(\microtime(true) + 31, $expire);
	}

	public function testExpiresAfterReturnsSelf(): void
	{
		$item = new CacheItem('k', 'v');
		self::assertSame($item, $item->expiresAfter(10.0));
	}
}
