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

namespace OZONE\Tests\FS;

use InvalidArgumentException;
use OZONE\Core\FS\TempFS;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Class TempFSTest.
 *
 * @internal
 *
 * @covers \OZONE\Core\FS\TempFS
 */
final class TempFSTest extends TestCase
{
	public function testPathIsUnderTheRoot(): void
	{
		$root = TempFS::root()->getRoot();

		self::assertSame($root . DS . 'my-ref', TempFS::path('my-ref'));
		self::assertSame($root . DS . 'my-ref' . DS . 'a.txt', TempFS::path('my-ref', 'a.txt'));
	}

	/**
	 * @dataProvider provideUnsafeSegments
	 */
	public function testPathRejectsUnsafeRef(string $segment): void
	{
		$this->expectException(InvalidArgumentException::class);
		TempFS::path($segment);
	}

	/**
	 * @dataProvider provideUnsafeSegments
	 */
	public function testPathRejectsUnsafeName(string $segment): void
	{
		if ('' === $segment) {
			// An empty name means "the ref directory itself".
			self::assertSame(TempFS::path('my-ref'), TempFS::path('my-ref', ''));

			return;
		}

		$this->expectException(InvalidArgumentException::class);
		TempFS::path('my-ref', $segment);
	}

	public static function provideUnsafeSegments(): iterable
	{
		return [
			'empty'     => [''],
			'dot'       => ['.'],
			'parent'    => ['..'],
			'traversal' => ['../../etc'],
			'separator' => ['a/b'],
			'backslash' => ['a\b'],
			'null byte' => ["a\0b"],
		];
	}

	public function testCanUseIsFalseRatherThanThrowingOnAnUnsafeRef(): void
	{
		self::assertFalse(TempFS::canUse('../../etc'));
		self::assertFalse(TempFS::canUse(''));
	}

	public function testCanUseFollowsTheLifetime(): void
	{
		$tmp = TempFS::get(60, 'test');

		self::assertTrue(TempFS::canUse($tmp->getRef()));

		$tmp->setLifetime(-1);

		self::assertFalse(TempFS::canUse($tmp->getRef()));
	}

	public function testUseRejectsAnExpiredRef(): void
	{
		$tmp = TempFS::get(60, 'test');
		$tmp->setLifetime(-1);

		$this->expectException(RuntimeException::class);
		TempFS::use($tmp->getRef());
	}

	public function testUseReturnsTheSameDirectory(): void
	{
		$tmp = TempFS::get(60, 'test');

		self::assertSame($tmp->dir()->getRoot(), TempFS::use($tmp->getRef())->dir()->getRoot());
	}
}
