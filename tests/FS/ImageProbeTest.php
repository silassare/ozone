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

use OZONE\Core\App\Settings;
use OZONE\Core\FS\FS;
use OZONE\Core\FS\Images\ImageProbe;
use PHPUnit\Framework\TestCase;

/**
 * Class ImageProbeTest.
 *
 * @internal
 *
 * @covers \OZONE\Core\FS\Images\ImageProbe
 */
final class ImageProbeTest extends TestCase
{
	protected function tearDown(): void
	{
		Settings::unset('oz.files', 'OZ_IMAGE_PROBE_MAX_SIZE');

		parent::tearDown();
	}

	public function testAStoredImageKeepsItsSizeAndColor(): void
	{
		$file = FS::getStorage(FS::PRIVATE_STORAGE)->saveRaw(self::png(120, 80), 'image/png', 'photo.png');

		$file->save();

		$image = FS::getFileByID((string) $file->getID())?->getData()[ImageProbe::DATA_KEY] ?? null;

		self::assertSame(['width' => 120, 'height' => 80, 'color' => '#0a141e'], $image);
	}

	public function testAClonedImageKeepsItsSourcesWithoutReadingItAgain(): void
	{
		$file = FS::getStorage(FS::PRIVATE_STORAGE)->saveRaw(self::png(40, 40), 'image/png', 'a.png');

		$file->save();

		$clone = $file->cloneFile();

		$clone->save();

		self::assertSame($file->getData()[ImageProbe::DATA_KEY], $clone->getData()[ImageProbe::DATA_KEY]);
	}

	public function testNothingIsKeptForWhatIsNotAnImageItCanRead(): void
	{
		$storage = FS::getStorage(FS::PRIVATE_STORAGE);
		$text    = $storage->saveRaw('just text', 'text/plain', 'a.txt');
		$broken  = $storage->saveRaw('not a png at all', 'image/png', 'broken.png');

		// An upload never fails on it: the file is stored, without the facts.
		self::assertTrue($text->save());
		self::assertTrue($broken->save());
		self::assertArrayNotHasKey(ImageProbe::DATA_KEY, $text->getData());
		self::assertArrayNotHasKey(ImageProbe::DATA_KEY, $broken->getData());
	}

	public function testAnImageLargerThanTheLimitIsNotDecoded(): void
	{
		Settings::set('oz.files', 'OZ_IMAGE_PROBE_MAX_SIZE', 10);

		$file = FS::getStorage(FS::PRIVATE_STORAGE)->saveRaw(self::png(40, 40), 'image/png', 'big.png');

		$file->save();

		self::assertArrayNotHasKey(ImageProbe::DATA_KEY, $file->getData());
	}

	private static function png(int $width, int $height): string
	{
		$img = \imagecreatetruecolor($width, $height);
		\imagefill($img, 0, 0, (int) \imagecolorallocate($img, 10, 20, 30));
		\ob_start();
		\imagepng($img);

		return (string) \ob_get_clean();
	}
}
