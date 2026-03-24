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
use PHPUnit\Framework\TestCase;

/**
 * Class ValidatedFileTest.
 *
 * @internal
 *
 * @coversNothing
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
}
