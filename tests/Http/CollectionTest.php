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

namespace OZONE\Tests\Http;

use OZONE\Core\Http\Collection;
use PHPUnit\Framework\TestCase;

/**
 * Class CollectionTest.
 *
 * @internal
 *
 * @coversNothing
 */
final class CollectionTest extends TestCase
{
	public function testConstructorPrePopulatesItems(): void
	{
		$col = new Collection(['a' => 1, 'b' => 2]);
		self::assertSame(1, $col->get('a'));
		self::assertSame(2, $col->get('b'));
	}

	public function testSetAndGet(): void
	{
		$col = new Collection();
		$col->set('key', 'value');
		self::assertSame('value', $col->get('key'));
	}

	public function testGetDefaultWhenKeyAbsent(): void
	{
		$col = new Collection();
		self::assertNull($col->get('missing'));
		self::assertSame('fallback', $col->get('missing', 'fallback'));
	}

	public function testHasReturnsTrueForExistingKey(): void
	{
		$col = new Collection(['x' => null]);
		self::assertTrue($col->has('x'));
		self::assertFalse($col->has('y'));
	}

	public function testRemoveDeletesKey(): void
	{
		$col = new Collection(['a' => 1]);
		$col->remove('a');
		self::assertFalse($col->has('a'));
	}

	public function testReplaceOverwritesExistingKeys(): void
	{
		$col = new Collection(['a' => 1]);
		$col->replace(['a' => 99, 'b' => 2]);
		self::assertSame(99, $col->get('a'));
		self::assertSame(2, $col->get('b'));
	}

	public function testAllReturnsAllItems(): void
	{
		$data = ['x' => 10, 'y' => 20];
		$col  = new Collection($data);
		self::assertSame($data, $col->all());
	}

	public function testKeysReturnsAllKeys(): void
	{
		$col = new Collection(['a' => 1, 'b' => 2]);
		self::assertSame(['a', 'b'], $col->keys());
	}

	public function testCountReturnsNumberOfItems(): void
	{
		$col = new Collection(['a' => 1, 'b' => 2, 'c' => 3]);
		self::assertCount(3, $col);
	}

	public function testClearRemovesAllItems(): void
	{
		$col = new Collection(['a' => 1]);
		$col->clear();
		self::assertCount(0, $col);
	}

	public function testArrayAccessInterface(): void
	{
		$col        = new Collection();
		$col['foo'] = 'bar';
		self::assertTrue(isset($col['foo']));
		self::assertSame('bar', $col['foo']);
		unset($col['foo']);
		self::assertFalse(isset($col['foo']));
	}

	public function testIteratorAggregateInterface(): void
	{
		$data = ['p' => 1, 'q' => 2];
		$col  = new Collection($data);
		$out  = [];

		foreach ($col as $k => $v) {
			$out[$k] = $v;
		}

		self::assertSame($data, $out);
	}
}
