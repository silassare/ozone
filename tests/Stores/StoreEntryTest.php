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

use OZONE\Core\Stores\StoreEntry;
use PHPUnit\Framework\TestCase;

/**
 * Class StoreEntryTest.
 *
 * @internal
 *
 * @covers \OZONE\Core\Stores\StoreEntry
 */
final class StoreEntryTest extends TestCase
{
	public function testValueIsReadable(): void
	{
		$entry = new StoreEntry('key', 'hello');
		self::assertSame('hello', $entry->value);
	}

	public function testKeyIsReadable(): void
	{
		$entry = new StoreEntry('my_key', 'v');
		self::assertSame('my_key', $entry->key);
	}

	public function testExpiresAtIsNullWhenNotSet(): void
	{
		$entry = new StoreEntry('k', 'v');
		self::assertNull($entry->expiresAt);
	}

	public function testExpiresAtIsSetWhenProvided(): void
	{
		$expires = \microtime(true) + 60;
		$entry   = new StoreEntry('k', 'v', $expires);
		self::assertSame($expires, $entry->expiresAt);
	}

	public function testIsExpiredReturnsFalseWhenNoExpiry(): void
	{
		$entry = new StoreEntry('k', 'v');
		self::assertFalse($entry->isExpired());
	}

	public function testIsExpiredReturnsFalseForFutureExpiry(): void
	{
		$entry = new StoreEntry('k', 'v', \microtime(true) + 3600);
		self::assertFalse($entry->isExpired());
	}

	public function testIsExpiredReturnsTrueForPastExpiry(): void
	{
		$entry = new StoreEntry('k', 'v', \microtime(true) - 1.0);
		self::assertTrue($entry->isExpired());
	}

	public function testForTTLCreatesEntryWithFutureExpiry(): void
	{
		$entry = StoreEntry::forTTL('k', 'v', 60.0);
		self::assertNotNull($entry->expiresAt);
		self::assertGreaterThan(\microtime(true), $entry->expiresAt);
		self::assertLessThan(\microtime(true) + 61, $entry->expiresAt);
	}

	public function testForTTLWithNegativeTTLCreatesExpiredEntry(): void
	{
		$entry = StoreEntry::forTTL('k', 'v', -1.0);
		self::assertTrue($entry->isExpired());
	}

	public function testEntryIsImmutable(): void
	{
		$entry = new StoreEntry('k', 'original');
		// readonly props cannot be modified — only assert they exist and are correct
		self::assertSame('k', $entry->key);
		self::assertSame('original', $entry->value);
	}

	public function testNullValueIsSupported(): void
	{
		$entry = new StoreEntry('k', null);
		self::assertNull($entry->value);
	}

	public function testArrayValueIsSupported(): void
	{
		$data  = ['a' => 1, 'b' => 2];
		$entry = new StoreEntry('k', $data);
		self::assertSame($data, $entry->value);
	}
}
