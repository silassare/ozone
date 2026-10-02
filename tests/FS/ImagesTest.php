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
use OZONE\Tests\Support\ImageBytes;
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
		$png       = ImageBytes::png(200, 100);

		self::assertSame([96, 48], ImageBytes::size($processor->render($png, 'image/png', new ImageRecipe(width: 96))->bytes));
		self::assertSame([96, 96], ImageBytes::size($processor->render($png, 'image/png', new ImageRecipe(96, 96, true))->bytes));
		self::assertSame([96, 48], ImageBytes::size($processor->render($png, 'image/png', new ImageRecipe(96, 96))->bytes));
		// Asked for more than it has: its own size, the ratio asked kept.
		self::assertSame([200, 100], ImageBytes::size($processor->render($png, 'image/png', new ImageRecipe(width: 400))->bytes));
		self::assertSame([100, 100], ImageBytes::size($processor->render($png, 'image/png', new ImageRecipe(400, 400, true))->bytes));
	}

	/**
	 * @dataProvider provideDrivers
	 */
	public function testAPhotoIsServedUpright(ImageDriverName $driver): void
	{
		// Stored 200 x 100, its EXIF saying "turn it a quarter clockwise" (orientation 6).
		$jpeg = ImageBytes::withExif(ImageBytes::jpeg(200, 100), 6);

		$out = (new InterventionImageProcessor($driver))->render($jpeg, 'image/jpeg', new ImageRecipe())->bytes;

		self::assertSame([100, 200], ImageBytes::size($out));
	}

	/**
	 * @dataProvider provideDrivers
	 */
	public function testARenditionCarriesNoMetadata(ImageDriverName $driver): void
	{
		$processor = new InterventionImageProcessor($driver);
		$jpeg      = $processor->render(ImageBytes::withExif(ImageBytes::jpeg(64, 64), 1), 'image/jpeg', new ImageRecipe())->bytes;
		$png       = $processor->render(ImageBytes::pngWithMetadata(64, 64), 'image/png', new ImageRecipe())->bytes;

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

		self::assertSame([40, 30], ImageBytes::size($processor->render(ImageBytes::png(40, 30), 'image/png', $recipe)->bytes));
	}

	/**
	 * @dataProvider provideDrivers
	 */
	public function testTurnsMirrorsAndKeepsAnArea(ImageDriverName $driver): void
	{
		$processor = new InterventionImageProcessor($driver);
		// Left half red, right half blue.
		$halves = ImageBytes::halves(200, 100);

		self::assertSame([100, 200], ImageBytes::size($processor->render($halves, 'image/png', new ImageRecipe(rotate: 90))->bytes));

		$flipped = $processor->render($halves, 'image/png', new ImageRecipe(flip: 'h'))->bytes;

		self::assertSame('blue', ImageBytes::colorAt($flipped, 10, 50));
		self::assertSame('red', ImageBytes::colorAt($flipped, 190, 50));

		// The right half's top-right quarter: 50% of the width from the middle, 50% of the height.
		$area = $processor->render($halves, 'image/png', new ImageRecipe(area: [500, 0, 500, 500]))->bytes;

		self::assertSame([100, 50], ImageBytes::size($area));
		self::assertSame('blue', ImageBytes::colorAt($area, 50, 25));
	}

	/**
	 * @dataProvider provideDrivers
	 */
	public function testACropToASizeKeepsTheFocalPointInView(ImageDriverName $driver): void
	{
		$processor = new InterventionImageProcessor($driver);
		$halves    = ImageBytes::halves(200, 100);

		$centered = $processor->render($halves, 'image/png', new ImageRecipe(50, 50, true))->bytes;
		$right    = $processor->render($halves, 'image/png', new ImageRecipe(50, 50, true, focal: [900, 500]))->bytes;
		$left     = $processor->render($halves, 'image/png', new ImageRecipe(50, 50, true, focal: [100, 500]))->bytes;

		self::assertSame('red', ImageBytes::colorAt($centered, 10, 25));
		self::assertSame('blue', ImageBytes::colorAt($centered, 40, 25));
		self::assertSame(['blue', 'blue'], [ImageBytes::colorAt($right, 5, 25), ImageBytes::colorAt($right, 45, 25)]);
		self::assertSame(['red', 'red'], [ImageBytes::colorAt($left, 5, 25), ImageBytes::colorAt($left, 45, 25)]);
	}

	/**
	 * @dataProvider provideDrivers
	 */
	public function testBrightensDarkensInvertsAndPixelates(ImageDriverName $driver): void
	{
		$processor = new InterventionImageProcessor($driver);
		$gray      = ImageBytes::filled(20, 20, 128, 128, 128);
		$level     = static fn (string $bytes): int => ImageBytes::rgbAt($bytes, 10, 10)[0];

		self::assertGreaterThan(140, $level($processor->render($gray, 'image/png', new ImageRecipe(effects: ['bright40']))->bytes));
		self::assertLessThan(116, $level($processor->render($gray, 'image/png', new ImageRecipe(effects: ['brightn40']))->bytes));
		self::assertSame(
			[255, 255, 255],
			ImageBytes::rgbAt($processor->render(ImageBytes::filled(10, 10, 0, 0, 0), 'image/png', new ImageRecipe(effects: ['invert']))->bytes, 5, 5)
		);
		self::assertSame([20, 20], ImageBytes::size($processor->render($gray, 'image/png', new ImageRecipe(effects: ['contrast20', 'pixel4']))->bytes));
	}

	/**
	 * @dataProvider provideDrivers
	 */
	public function testWritesTheFormatAsked(ImageDriverName $driver): void
	{
		$processor = new InterventionImageProcessor($driver);
		$webp      = $processor->render(ImageBytes::png(32, 32), 'image/png', new ImageRecipe(format: 'webp'));

		self::assertSame('image/webp', $webp->mime);
		self::assertSame('WEBP', \substr($webp->bytes, 8, 4));

		if ($processor->supports('image/avif')) {
			$avif = $processor->render(ImageBytes::png(32, 32), 'image/png', new ImageRecipe(format: 'avif'));

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

		\file_put_contents($mark, ImageBytes::filled(10, 10, 255, 255, 255));
		Settings::set('oz.files', 'OZ_IMAGE_WATERMARKS', [
			'corner' => ['path' => $mark, 'position' => 'bottom-right', 'opacity' => 1, 'width' => 25, 'margin' => 0],
			'faint'  => ['path' => $mark, 'position' => 'top-left', 'opacity' => 0.5, 'width' => 25, 'margin' => 0],
		]);

		try {
			$processor = new InterventionImageProcessor($driver);
			$black     = ImageBytes::filled(100, 100, 0, 0, 0);
			$corner    = $processor->render($black, 'image/png', new ImageRecipe(watermark: 'corner'))->bytes;
			$faint     = $processor->render($black, 'image/png', new ImageRecipe(watermark: 'faint'))->bytes;

			// A quarter of the width, in the bottom right corner; the rest untouched.
			self::assertSame([255, 255, 255], ImageBytes::rgbAt($corner, 90, 90));
			self::assertSame([0, 0, 0], ImageBytes::rgbAt($corner, 70, 70));
			// Half seen through.
			self::assertEqualsWithDelta(128, ImageBytes::rgbAt($faint, 10, 10)[0], 20);
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
				->render(ImageBytes::png(10, 10), 'image/png', new ImageRecipe(effects: ['redden']))->bytes;

			self::assertSame('red', ImageBytes::colorAt($out, 5, 5));
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
		$info      = $processor->probe(ImageBytes::withExif(ImageBytes::jpeg(200, 100), 6));

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
}
