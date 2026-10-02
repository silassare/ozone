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

use OZONE\Core\FS\Enums\ImageDriverName;
use OZONE\Core\FS\Images\ImageRecipe;
use OZONE\Core\FS\Images\Images;
use OZONE\Core\FS\Images\InterventionImageProcessor;
use PHPUnit\Framework\TestCase;

/**
 * Class ImagesTest.
 *
 * Every driver is held to the same renditions: the test image has them all (docker/php/Dockerfile).
 *
 * @internal
 *
 * @covers \OZONE\Core\FS\Images\InterventionImageProcessor
 */
final class ImagesTest extends TestCase
{
	/**
	 * @return array<string, array{ImageDriverName}>
	 */
	public static function provideDrivers(): iterable
	{
		yield 'vips' => [ImageDriverName::VIPS];

		yield 'imagick' => [ImageDriverName::IMAGICK];

		yield 'gd' => [ImageDriverName::GD];
	}

	public function testTheFirstDriverTheServerHasIsUsed(): void
	{
		self::assertSame('vips', (new InterventionImageProcessor())->driver());
		self::assertSame('vips', Images::processor()->driver());
	}

	/**
	 * @dataProvider provideDrivers
	 */
	public function testResizesWithoutEverEnlarging(ImageDriverName $driver): void
	{
		$processor = new InterventionImageProcessor($driver);
		$png       = self::png(200, 100);

		self::assertSame([96, 48], self::size($processor->render($png, 'image/png', new ImageRecipe(width: 96))));
		self::assertSame([96, 96], self::size($processor->render($png, 'image/png', new ImageRecipe(96, 96, true))));
		self::assertSame([96, 48], self::size($processor->render($png, 'image/png', new ImageRecipe(96, 96))));
		// Asked for more than it has: its own size, the ratio asked kept.
		self::assertSame([200, 100], self::size($processor->render($png, 'image/png', new ImageRecipe(width: 400))));
		self::assertSame([100, 100], self::size($processor->render($png, 'image/png', new ImageRecipe(400, 400, true))));
	}

	/**
	 * @dataProvider provideDrivers
	 */
	public function testAPhotoIsServedUpright(ImageDriverName $driver): void
	{
		// Stored 200 x 100, its EXIF saying "turn it a quarter clockwise" (orientation 6).
		$jpeg = self::withExif(self::jpeg(200, 100), 6);

		$out = (new InterventionImageProcessor($driver))->render($jpeg, 'image/jpeg', new ImageRecipe());

		self::assertSame([100, 200], self::size($out));
	}

	/**
	 * @dataProvider provideDrivers
	 */
	public function testARenditionCarriesNoMetadata(ImageDriverName $driver): void
	{
		$processor = new InterventionImageProcessor($driver);
		$jpeg      = $processor->render(self::withExif(self::jpeg(64, 64), 1), 'image/jpeg', new ImageRecipe());
		$png       = $processor->render(self::pngWithMetadata(64, 64), 'image/png', new ImageRecipe());

		foreach (['jpeg' => $jpeg, 'png' => $png] as $format => $bytes) {
			self::assertStringNotContainsString('OZoneCamera', $bytes, $format);
			self::assertStringNotContainsString('OZoneSecret', $bytes, $format);
			self::assertStringNotContainsString("Exif\0\0", $bytes, $format);
		}
	}

	/**
	 * @dataProvider provideDrivers
	 */
	public function testEffectsKeepAValidImageOfTheSameSize(ImageDriverName $driver): void
	{
		$processor = new InterventionImageProcessor($driver);
		$recipe    = new ImageRecipe(effects: ['grayscale', 'sepia', 'sharpen', 'blur3']);

		self::assertSame([40, 30], self::size($processor->render(self::png(40, 30), 'image/png', $recipe)));
	}

	/**
	 * [width, height] of an image's bytes.
	 *
	 * @return array{int, int}
	 */
	private static function size(string $bytes): array
	{
		$info = \getimagesizefromstring($bytes);

		self::assertNotFalse($info, 'A valid image is expected.');

		return [$info[0], $info[1]];
	}

	private static function png(int $width, int $height): string
	{
		$img = \imagecreatetruecolor($width, $height);
		\ob_start();
		\imagepng($img);

		return (string) \ob_get_clean();
	}

	private static function jpeg(int $width, int $height): string
	{
		$img = \imagecreatetruecolor($width, $height);
		\ob_start();
		\imagejpeg($img);

		return (string) \ob_get_clean();
	}

	/**
	 * A TIFF block of EXIF: the camera's make (`OZoneCamera`) and an orientation.
	 */
	private static function tiff(int $orientation): string
	{
		$make = "OZoneCamera\0";

		return 'II' . \pack('vV', 42, 8)
			. \pack('v', 2)
			// Make: ASCII, at the data area after the IFD (8 + 2 + 2 * 12 + 4 = 38).
			. \pack('vvVV', 0x010F, 2, \strlen($make), 38)
			// Orientation: one SHORT, in the value field.
			. \pack('vvVvv', 0x0112, 3, 1, $orientation, 0)
			. \pack('V', 0)
			. $make;
	}

	/** A JPEG with an EXIF segment (APP1) right after its start. */
	private static function withExif(string $jpeg, int $orientation): string
	{
		$payload = "Exif\0\0" . self::tiff($orientation);

		return \substr($jpeg, 0, 2) . "\xFF\xE1" . \pack('n', \strlen($payload) + 2) . $payload . \substr($jpeg, 2);
	}

	/** A PNG with an EXIF chunk and a comment, before its end. */
	private static function pngWithMetadata(int $width, int $height): string
	{
		$png   = self::png($width, $height);
		$chunk = static fn (string $type, string $data): string => \pack('N', \strlen($data)) . $type . $data
			. \pack('N', \crc32($type . $data));
		$end   = \strrpos($png, 'IEND') - 4;

		return \substr($png, 0, $end)
			. $chunk('eXIf', self::tiff(1))
			. $chunk('tEXt', "Comment\0OZoneSecret")
			. \substr($png, $end);
	}
}
