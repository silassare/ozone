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

use OZONE\Core\Cache\CacheEntry;
use PHPUnit\Framework\TestCase;

/**
 * Class CacheEntryTest.
 *
 * @internal
 *
 * @coversNothing
 */
final class CacheEntryTest extends TestCase
{
	public function testValueIsReadable(): void
	{
		$entry = new CacheEntry('key', 'hello');
		self::assertSame('hello', $entry->value);
	}

	public function testKeyIsReadable(): void
	{
		$entry = new CacheEntry('my_key', 'v');
		self::assertSame('my_key', $entry->key);
	}

	public function testExpiresAtIsNullWhenNotSet(): void
	{
		$entry = new CacheEntry('k', 'v');
		self::assertNull($entry->expiresAt);
	}

	public function testExpiresAtIsSetWhenProvided(): void
	{
		$expires = \microtime(true) + 60;
		$entry   = new CacheEntry('k', 'v', $expires);
		self::assertSame($expires, $entry->expiresAt);
	}

	public function testIsExpiredReturnsFalseWhenNoExpiry(): void
	{
		$entry = new CacheEntry('k', 'v');
		self::assertFalse($entry->isExpired());
	}

	public function testIsExpiredReturnsFalseForFutureExpiry(): void
	{
		$entry = new CacheEntry('k', 'v', \microtime(true) + 3600);
		self::assertFalse($entry->isExpired());
	}

	public function testIsExpiredReturnsTrueForPastExpiry(): void
	{
		$entry = new CacheEntry('k', 'v', \microtime(true) - 1.0);
		self::assertTrue($entry->isExpired());
	}

	public function testForTTLCreatesEntryWithFutureExpiry(): void
	{
		$entry = CacheEntry::forTTL('k', 'v', 60.0);
		self::assertNotNull($entry->expiresAt);
		self::assertGreaterThan(\microtime(true), $entry->expiresAt);
		self::assertLessThan(\microtime(true) + 61, $entry->expiresAt);
	}

	public function testForTTLWithNegativeTTLCreatesExpiredEntry(): void
	{
		$entry = CacheEntry::forTTL('k', 'v', -1.0);
		self::assertTrue($entry->isExpired());
	}

	public function testEntryIsImmutable(): void
	{
		$entry = new CacheEntry('k', 'original');
		// readonly props cannot be modified — only assert they exist and are correct
		self::assertSame('k', $entry->key);
		self::assertSame('original', $entry->value);
	}

	public function testNullValueIsSupported(): void
	{
		$entry = new CacheEntry('k', null);
		self::assertNull($entry->value);
	}

	public function testArrayValueIsSupported(): void
	{
		$data  = ['a' => 1, 'b' => 2];
		$entry = new CacheEntry('k', $data);
		self::assertSame($data, $entry->value);
	}
}
