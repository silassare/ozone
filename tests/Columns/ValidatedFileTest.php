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

namespace OZONE\Tests\Columns;

use InvalidArgumentException;
use LogicException;
use OZONE\Core\Columns\ValidatedFile;
use OZONE\Core\Db\OZFile;
use OZONE\Core\FS\TempFS;
use PHPUnit\Framework\TestCase;

/**
 * Class ValidatedFileTest.
 *
 * @internal
 *
 * @covers \OZONE\Core\Columns\ValidatedFile
 */
final class ValidatedFileTest extends TestCase
{
	public function testForFileIDCreatesPersisted(): void
	{
		$vf = ValidatedFile::forFileID('42');
		self::assertTrue($vf->isPersisted());
		self::assertFalse($vf->isTemporary());
	}

	public function testForFileIDGetIdReturnsId(): void
	{
		$vf = ValidatedFile::forFileID('99');
		self::assertSame('99', $vf->getId());
	}

	public function testForFileIDToString(): void
	{
		$vf = ValidatedFile::forFileID('123');
		self::assertSame('123', (string) $vf);
	}

	public function testForFileIDGetPathThrows(): void
	{
		$this->expectException(LogicException::class);
		ValidatedFile::forFileID('42')->getPath();
	}

	public function testForTempPathCreatesTemporary(): void
	{
		$vf = ValidatedFile::forTempPath('/tmp/upload/abc.jpg');
		self::assertTrue($vf->isTemporary());
		self::assertFalse($vf->isPersisted());
	}

	public function testForTempPathGetPathReturnsPath(): void
	{
		$vf = ValidatedFile::forTempPath('/tmp/upload/foo.png');
		self::assertSame('/tmp/upload/foo.png', $vf->getPath());
	}

	public function testForTempPathToString(): void
	{
		$vf = ValidatedFile::forTempPath('/tmp/abc');
		self::assertSame('/tmp/abc', (string) $vf);
	}

	public function testForTempPathGetIdThrows(): void
	{
		$this->expectException(LogicException::class);
		ValidatedFile::forTempPath('/tmp/abc')->getId();
	}

	public function testForTempPathLoadFileReturnsNull(): void
	{
		$vf = ValidatedFile::forTempPath('/tmp/abc.jpg');
		self::assertNull($vf->loadFile());
	}

	public function testForFileCreatesPersisted(): void
	{
		$file = $this->createMock(OZFile::class);
		$file->method('isSaved')->willReturn(true);
		$file->method('getID')->willReturn('77');

		$vf = ValidatedFile::forFile($file);
		self::assertTrue($vf->isPersisted());
		self::assertFalse($vf->isTemporary());
		self::assertSame('77', $vf->getId());
	}

	public function testForFileReturnsCachedEntityOnLoadFile(): void
	{
		$file = $this->createMock(OZFile::class);
		$file->method('isSaved')->willReturn(true);
		$file->method('getID')->willReturn('55');

		$vf = ValidatedFile::forFile($file);
		// The entity must be cached instantly - no DB query should occur.
		self::assertSame($file, $vf->loadFile());
		// Second call still returns the same cached instance.
		self::assertSame($file, $vf->loadFile());
	}

	public function testForFileThrowsWhenNotSaved(): void
	{
		$this->expectException(InvalidArgumentException::class);

		$file = $this->createMock(OZFile::class);
		$file->method('isSaved')->willReturn(false);

		ValidatedFile::forFile($file);
	}

	public function testForTempFileStoresRefNotPath(): void
	{
		$vf = ValidatedFile::forTempFile('upload-2026-09-12-abcd1234', 'photo.jpg');

		self::assertTrue($vf->isTemporary());
		self::assertSame('upload-2026-09-12-abcd1234/photo.jpg', (string) $vf);
		self::assertSame('upload-2026-09-12-abcd1234/photo.jpg', $vf->jsonSerialize());
	}

	public function testForTempFileResolvesPathUnderTempRoot(): void
	{
		$vf = ValidatedFile::forTempFile('upload-2026-09-12-abcd1234', 'photo.jpg');

		self::assertSame(TempFS::path('upload-2026-09-12-abcd1234', 'photo.jpg'), $vf->getPath());
		self::assertStringStartsWith(TempFS::root()->getRoot(), $vf->getPath());
	}

	/**
	 * @dataProvider provideUnsafeSegments
	 */
	public function testForTempFileRejectsUnsafeRef(string $segment): void
	{
		$this->expectException(InvalidArgumentException::class);
		ValidatedFile::forTempFile($segment, 'photo.jpg');
	}

	/**
	 * @dataProvider provideUnsafeSegments
	 */
	public function testForTempFileRejectsUnsafeName(string $segment): void
	{
		$this->expectException(InvalidArgumentException::class);
		ValidatedFile::forTempFile('upload-2026-09-12-abcd1234', $segment);
	}

	public static function provideUnsafeSegments(): iterable
	{
		return [
			'empty'      => [''],
			'dot'        => ['.'],
			'parent'     => ['..'],
			'traversal'  => ['../../etc'],
			'separator'  => ['a/b'],
			'backslash'  => ['a\b'],
			'null byte'  => ["a\0b"],
		];
	}

	public function testForTempPathInsideTempRootBecomesRef(): void
	{
		$path = TempFS::path('upload-2026-09-12-abcd1234', 'photo.jpg');
		$vf   = ValidatedFile::forTempPath($path);

		self::assertSame('upload-2026-09-12-abcd1234/photo.jpg', (string) $vf);
		self::assertSame($path, $vf->getPath());
	}

	public function testForTempValueKeepsLegacyAbsolutePath(): void
	{
		$vf = ValidatedFile::forTempValue('/var/www/old-release/.ozone/cache/tmp-fs/up/photo.jpg');

		self::assertTrue($vf->isTemporary());
		self::assertSame('/var/www/old-release/.ozone/cache/tmp-fs/up/photo.jpg', $vf->getPath());
		self::assertFalse($vf->isAvailable());
	}

	public function testGetPathThrowsOnMalformedTempValue(): void
	{
		$this->expectException(InvalidArgumentException::class);
		ValidatedFile::forTempValue('no-slash-here')->getPath();
	}

	public function testIsAvailableFollowsTheTempFsRef(): void
	{
		$tmp = TempFS::get(60, 'test');
		$dir = $tmp->dir();

		\file_put_contents($dir->resolve('photo.jpg'), 'content');

		$vf = ValidatedFile::forTempFile($tmp->getRef(), 'photo.jpg');

		self::assertTrue($vf->isAvailable());

		// The ref outlived its lifetime: the file may still be there, it is not usable.
		$tmp->setLifetime(-1);

		self::assertFalse($vf->isAvailable());
	}

	public function testIsAvailableIsFalseForAnUnknownRef(): void
	{
		$vf = ValidatedFile::forTempFile('upload-2026-09-12-abcd1234', 'photo.jpg');

		self::assertFalse($vf->isAvailable());
	}
}
