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

use Intervention\Image\Interfaces\ImageInterface;
use OZONE\Core\App\Settings;
use OZONE\Core\FS\Enums\ImageDriverName;
use OZONE\Core\FS\Images\ImageRecipe;
use OZONE\Core\FS\Images\Images;
use OZONE\Core\FS\Images\ImageTokens;
use OZONE\Core\FS\Images\Interfaces\ImageTokenInterface;
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

		self::assertSame([96, 48], self::size($processor->render($png, 'image/png', new ImageRecipe(width: 96))->bytes));
		self::assertSame([96, 96], self::size($processor->render($png, 'image/png', new ImageRecipe(96, 96, true))->bytes));
		self::assertSame([96, 48], self::size($processor->render($png, 'image/png', new ImageRecipe(96, 96))->bytes));
		// Asked for more than it has: its own size, the ratio asked kept.
		self::assertSame([200, 100], self::size($processor->render($png, 'image/png', new ImageRecipe(width: 400))->bytes));
		self::assertSame([100, 100], self::size($processor->render($png, 'image/png', new ImageRecipe(400, 400, true))->bytes));
	}

	/**
	 * @dataProvider provideDrivers
	 */
	public function testAPhotoIsServedUpright(ImageDriverName $driver): void
	{
		// Stored 200 x 100, its EXIF saying "turn it a quarter clockwise" (orientation 6).
		$jpeg = self::withExif(self::jpeg(200, 100), 6);

		$out = (new InterventionImageProcessor($driver))->render($jpeg, 'image/jpeg', new ImageRecipe())->bytes;

		self::assertSame([100, 200], self::size($out));
	}

	/**
	 * @dataProvider provideDrivers
	 */
	public function testARenditionCarriesNoMetadata(ImageDriverName $driver): void
	{
		$processor = new InterventionImageProcessor($driver);
		$jpeg      = $processor->render(self::withExif(self::jpeg(64, 64), 1), 'image/jpeg', new ImageRecipe())->bytes;
		$png       = $processor->render(self::pngWithMetadata(64, 64), 'image/png', new ImageRecipe())->bytes;

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

		self::assertSame([40, 30], self::size($processor->render(self::png(40, 30), 'image/png', $recipe)->bytes));
	}

	/**
	 * @dataProvider provideDrivers
	 */
	public function testTurnsMirrorsAndKeepsAnArea(ImageDriverName $driver): void
	{
		$processor = new InterventionImageProcessor($driver);
		// Left half red, right half blue.
		$halves = self::halves(200, 100);

		self::assertSame([100, 200], self::size($processor->render($halves, 'image/png', new ImageRecipe(rotate: 90))->bytes));

		$flipped = $processor->render($halves, 'image/png', new ImageRecipe(flip: 'h'))->bytes;

		self::assertSame('blue', self::colorAt($flipped, 10, 50));
		self::assertSame('red', self::colorAt($flipped, 190, 50));

		// The right half's top-right quarter: 50% of the width from the middle, 50% of the height.
		$area = $processor->render($halves, 'image/png', new ImageRecipe(area: [500, 0, 500, 500]))->bytes;

		self::assertSame([100, 50], self::size($area));
		self::assertSame('blue', self::colorAt($area, 50, 25));
	}

	/**
	 * @dataProvider provideDrivers
	 */
	public function testACropToASizeKeepsTheFocalPointInView(ImageDriverName $driver): void
	{
		$processor = new InterventionImageProcessor($driver);
		$halves    = self::halves(200, 100);

		$centered = $processor->render($halves, 'image/png', new ImageRecipe(50, 50, true))->bytes;
		$right    = $processor->render($halves, 'image/png', new ImageRecipe(50, 50, true, focal: [900, 500]))->bytes;
		$left     = $processor->render($halves, 'image/png', new ImageRecipe(50, 50, true, focal: [100, 500]))->bytes;

		self::assertSame('red', self::colorAt($centered, 10, 25));
		self::assertSame('blue', self::colorAt($centered, 40, 25));
		self::assertSame(['blue', 'blue'], [self::colorAt($right, 5, 25), self::colorAt($right, 45, 25)]);
		self::assertSame(['red', 'red'], [self::colorAt($left, 5, 25), self::colorAt($left, 45, 25)]);
	}

	/**
	 * @dataProvider provideDrivers
	 */
	public function testBrightensDarkensInvertsAndPixelates(ImageDriverName $driver): void
	{
		$processor = new InterventionImageProcessor($driver);
		$gray      = self::filled(20, 20, 128, 128, 128);
		$level     = static fn (string $bytes): int => self::rgbAt($bytes, 10, 10)[0];

		self::assertGreaterThan(140, $level($processor->render($gray, 'image/png', new ImageRecipe(effects: ['bright40']))->bytes));
		self::assertLessThan(116, $level($processor->render($gray, 'image/png', new ImageRecipe(effects: ['brightn40']))->bytes));
		self::assertSame(
			[255, 255, 255],
			self::rgbAt($processor->render(self::filled(10, 10, 0, 0, 0), 'image/png', new ImageRecipe(effects: ['invert']))->bytes, 5, 5)
		);
		self::assertSame([20, 20], self::size($processor->render($gray, 'image/png', new ImageRecipe(effects: ['contrast20', 'pixel4']))->bytes));
	}

	/**
	 * @dataProvider provideDrivers
	 */
	public function testWritesTheFormatAsked(ImageDriverName $driver): void
	{
		$processor = new InterventionImageProcessor($driver);
		$webp      = $processor->render(self::png(32, 32), 'image/png', new ImageRecipe(format: 'webp'));

		self::assertSame('image/webp', $webp->mime);
		self::assertSame('WEBP', \substr($webp->bytes, 8, 4));

		if ($processor->supports('image/avif')) {
			$avif = $processor->render(self::png(32, 32), 'image/png', new ImageRecipe(format: 'avif'));

			self::assertSame('image/avif', $avif->mime);
			self::assertStringContainsString('ftypavif', \substr($avif->bytes, 0, 32));
		}
	}

	/**
	 * @dataProvider provideDrivers
	 */
	public function testAWatermarkSitsWhereItIsDeclared(ImageDriverName $driver): void
	{
		$mark = \tempnam(\sys_get_temp_dir(), 'oz-mark') . '.png';

		\file_put_contents($mark, self::filled(10, 10, 255, 255, 255));
		Settings::set('oz.files', 'OZ_IMAGE_WATERMARKS', [
			'corner' => ['path' => $mark, 'position' => 'bottom-right', 'opacity' => 1, 'width' => 25, 'margin' => 0],
			'faint'  => ['path' => $mark, 'position' => 'top-left', 'opacity' => 0.5, 'width' => 25, 'margin' => 0],
		]);

		try {
			$processor = new InterventionImageProcessor($driver);
			$black     = self::filled(100, 100, 0, 0, 0);
			$corner    = $processor->render($black, 'image/png', new ImageRecipe(watermark: 'corner'))->bytes;
			$faint     = $processor->render($black, 'image/png', new ImageRecipe(watermark: 'faint'))->bytes;

			// A quarter of the width, in the bottom right corner; the rest untouched.
			self::assertSame([255, 255, 255], self::rgbAt($corner, 90, 90));
			self::assertSame([0, 0, 0], self::rgbAt($corner, 70, 70));
			// Half seen through.
			self::assertEqualsWithDelta(128, self::rgbAt($faint, 10, 10)[0], 20);
		} finally {
			Settings::unset('oz.files', 'OZ_IMAGE_WATERMARKS');
			\unlink($mark);
		}
	}

	/**
	 * @dataProvider provideDrivers
	 */
	public function testAProjectsOwnTokenDrawsWithTheLibrary(ImageDriverName $driver): void
	{
		ImageTokens::register(new class implements ImageTokenInterface {
			public function canonical(string $token): ?string
			{
				return 'redden' === $token ? 'redden' : null;
			}

			public function apply(ImageInterface $image, string $token): void
			{
				$image->fill('ff0000');
			}
		});

		try {
			$out = (new InterventionImageProcessor($driver))
				->render(self::png(10, 10), 'image/png', new ImageRecipe(effects: ['redden']))->bytes;

			self::assertSame('red', self::colorAt($out, 5, 5));
		} finally {
			ImageTokens::reset();
		}
	}

	/**
	 * @dataProvider provideDrivers
	 */
	public function testProbesAnImagesSizeAsShownAndItsColor(ImageDriverName $driver): void
	{
		$processor = new InterventionImageProcessor($driver);
		$info      = $processor->probe(self::withExif(self::jpeg(200, 100), 6));

		self::assertSame([100, 200], [$info->width, $info->height]);

		// Mostly red: a red half and a blue strip.
		$img = \imagecreatetruecolor(100, 100);
		\imagefill($img, 0, 0, (int) \imagecolorallocate($img, 220, 30, 30));
		\imagefilledrectangle($img, 0, 0, 9, 99, (int) \imagecolorallocate($img, 0, 0, 255));
		\ob_start();
		\imagepng($img);

		$color = $processor->probe((string) \ob_get_clean())->color;

		self::assertMatchesRegularExpression('~^#[0-9a-f]{6}$~', $color);
		self::assertGreaterThan(180, \hexdec(\substr($color, 1, 2)));
		self::assertLessThan(80, \hexdec(\substr($color, 5, 2)));
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

	/** An image of one color. */
	private static function filled(int $width, int $height, int $r, int $g, int $b): string
	{
		$img = \imagecreatetruecolor($width, $height);
		\imagefill($img, 0, 0, (int) \imagecolorallocate($img, $r, $g, $b));
		\ob_start();
		\imagepng($img);

		return (string) \ob_get_clean();
	}

	/** Its left half red, its right half blue. */
	private static function halves(int $width, int $height): string
	{
		$img = \imagecreatetruecolor($width, $height);
		\imagefilledrectangle($img, 0, 0, \intdiv($width, 2) - 1, $height - 1, (int) \imagecolorallocate($img, 255, 0, 0));
		\imagefilledrectangle($img, \intdiv($width, 2), 0, $width - 1, $height - 1, (int) \imagecolorallocate($img, 0, 0, 255));
		\ob_start();
		\imagepng($img);

		return (string) \ob_get_clean();
	}

	/**
	 * A pixel's red, green and blue.
	 *
	 * @return array{int, int, int}
	 */
	private static function rgbAt(string $bytes, int $x, int $y): array
	{
		$img = \imagecreatefromstring($bytes);

		self::assertNotFalse($img, 'A valid image is expected.');

		$rgb = \imagecolorsforindex($img, (int) \imagecolorat($img, $x, $y));

		return [$rgb['red'], $rgb['green'], $rgb['blue']];
	}

	/** Which of red and blue a pixel is closest to. */
	private static function colorAt(string $bytes, int $x, int $y): string
	{
		[$r, , $b] = self::rgbAt($bytes, $x, $y);

		return $r > $b ? 'red' : 'blue';
	}
}
