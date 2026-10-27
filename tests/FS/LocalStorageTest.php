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

use OZONE\Core\FS\Drivers\AbstractLocalStorage;
use OZONE\Core\FS\FS;
use OZONE\Core\FS\Traits\FileEntityTrait;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversTrait;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Class LocalStorageTest.
 *
 * @internal
 */
#[CoversClass(AbstractLocalStorage::class)]
#[CoversTrait(FileEntityTrait::class)]
final class LocalStorageTest extends TestCase
{
	#[DataProvider('provideSavesRawContentCases')]
	public function testSavesRawContent(string $storage_name): void
	{
		$storage = FS::getStorage($storage_name);
		$file    = $storage->saveRaw('raw content', 'text/plain', 'raw.txt');

		self::assertSame(11, (int) $file->getSize());
		self::assertSame('raw content', (string) $storage->getStream($file));

		$storage->delete($file);
	}

	public static function provideSavesRawContentCases(): iterable
	{
		yield 'private' => [FS::PRIVATE_STORAGE];

		yield 'public' => [FS::PUBLIC_STORAGE];
	}

	public function testClonesShareTheContentOfTheirSource(): void
	{
		$storage = FS::getStorage(FS::PRIVATE_STORAGE);
		$file    = $storage->saveRaw('shared', 'text/plain', 'shared.txt');

		$file->save();

		$clone = $file->cloneFile();
		$clone->save();

		$second = $clone->cloneFile();
		$second->save();

		self::assertNotSame((string) $file->getID(), (string) $clone->getID());
		self::assertNotSame($file->getKey(), $clone->getKey());
		self::assertSame($file->getRef(), $clone->getRef());
		self::assertSame('shared', (string) $storage->getStream($clone));

		self::assertSame((string) $file->getID(), (string) $clone->getCloneID());
		self::assertSame((string) $file->getID(), (string) $clone->getSourceID());
		self::assertSame((string) $clone->getID(), (string) $second->getCloneID());
		self::assertSame((string) $file->getID(), (string) $second->getSourceID());

		self::assertTrue($file->hasClones());
		self::assertFalse($file->canDelete());
		self::assertFalse($clone->canDelete());
	}
}
