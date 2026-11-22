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

use OZONE\Core\CRUD\TableCRUD;
use OZONE\Core\Db\OZFile;
use OZONE\Core\Exceptions\RuntimeException;
use OZONE\Core\FS\FS;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Throwable;

/**
 * Class FileDeletionTest.
 *
 * A file's deletion refused by a listener of it -- a plugin whose pages still show the file --
 * leaves the file whole: its row and its bytes.
 *
 * @internal
 */
#[CoversClass(FS::class)]
final class FileDeletionTest extends TestCase
{
	public function testAFileWhoseDeletionIsRefusedKeepsItsRowAndItsBytes(): void
	{
		$storage = FS::getStorage(FS::PRIVATE_STORAGE);
		$file    = $storage->saveRaw('still shown', 'text/plain', 'shown.txt');

		$file->save();

		$id = (string) $file->getID();

		TableCRUD::events(OZFile::TABLE_NAME)->onBeforeEntityDeletion(
			static function (OZFile $deleted) use ($id): void {
				if ($id === (string) $deleted->getID()) {
					throw new RuntimeException('A page still shows this file.');
				}
			}
		);

		try {
			FS::deleteFileByID($id);
			self::fail('The deletion was not refused.');
		} catch (RuntimeException $e) {
			self::assertStringContainsString('Unable to delete file', $e->getMessage());
		}

		$kept = FS::getFileByID($id);

		self::assertNotNull($kept);
		self::assertTrue($storage->exists($kept));
		self::assertSame('still shown', (string) $storage->getStream($kept));
	}

	public function testAFileNothingRefusesLosesItsRowAndItsBytes(): void
	{
		$storage = FS::getStorage(FS::PRIVATE_STORAGE);
		$file    = $storage->saveRaw('gone', 'text/plain', 'gone.txt');

		$file->save();

		FS::deleteFileByID((string) $file->getID());

		self::assertNull(FS::getFileByID((string) $file->getID()));

		try {
			self::assertFalse($storage->exists($file));
		} catch (Throwable) {
			// A driver that answers a missing file with a throw has also said it is gone.
		}
	}
}
