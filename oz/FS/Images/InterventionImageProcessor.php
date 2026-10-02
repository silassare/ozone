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

namespace OZONE\Core\FS\Images;

use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use Intervention\Image\Drivers\Imagick\Driver as ImagickDriver;
use Intervention\Image\Drivers\Imagick\Modifiers\StripMetaModifier as ImagickStripMeta;
use Intervention\Image\Drivers\Vips\Driver as VipsDriver;
use Intervention\Image\Direction;
use Intervention\Image\ImageManager;
use Intervention\Image\Interfaces\DriverInterface;
use Intervention\Image\Interfaces\ImageInterface;
use Override;
use OZONE\Core\Exceptions\RuntimeException;
use OZONE\Core\FS\Enums\ImageDriverName;
use OZONE\Core\FS\Images\Interfaces\ImageProcessorInterface;
use Throwable;

/**
 * Images rendered by Intervention Image, with the driver asked for, or the first the server has
 * (libvips, Imagick, GD). Every rendition is decoded upright and encoded without metadata.
 */
final class InterventionImageProcessor implements ImageProcessorInterface
{
	private readonly ImageManager $manager;
	private readonly DriverInterface $imageDriver;
	private readonly string $driverName;

	/** @var array<string, bool> by media type: whether this driver writes it here */
	private array $writes = [];

	/**
	 * @throws RuntimeException when the driver asked for is not on this server, or none is
	 */
	public function __construct(ImageDriverName $driver = ImageDriverName::AUTO)
	{
		$names = ImageDriverName::AUTO === $driver
			? [ImageDriverName::VIPS, ImageDriverName::IMAGICK, ImageDriverName::GD]
			: [$driver];

		foreach ($names as $name) {
			$found = self::driverOf($name);

			if (null !== $found) {
				$this->driverName  = $name->value;
				$this->imageDriver = $found;
				$this->manager     = new ImageManager($found, autoOrientation: true, strip: true);

				return;
			}
		}

		throw new RuntimeException(
			ImageDriverName::AUTO === $driver
				? 'No image driver on this server: install libvips (with ext-ffi), ext-imagick or ext-gd.'
				: \sprintf('The image driver "%s" is not available on this server.', $driver->value)
		);
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function driver(): string
	{
		return $this->driverName;
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function supports(string $mime): bool
	{
		// A driver that reads a format may not write it (libheif built without an AV1 encoder says it
		// supports AVIF, and fails to encode it): writing a pixel once tells.
		if (!isset($this->writes[$mime])) {
			try {
				$this->writes[$mime] = $this->imageDriver->supports($mime)
					&& '' !== $this->manager->createImage(1, 1)->encodeUsingMediaType($mime)->toString();
			} catch (Throwable) {
				$this->writes[$mime] = false;
			}
		}

		return $this->writes[$mime];
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function render(string $bytes, string $mime, ImageRecipe $recipe): RenderedImage
	{
		$image = $this->manager->decodeBinary($bytes);

		if ($recipe->rotate) {
			$image->rotate($recipe->rotate);
		}

		if (null !== $recipe->flip) {
			$image->flip('h' === $recipe->flip ? Direction::HORIZONTAL : Direction::VERTICAL);
		}

		if (null !== $recipe->area) {
			[$x, $y, $w, $h] = $recipe->area;
			$width           = $image->width();
			$height          = $image->height();

			$image->crop(
				\max(1, (int) \round($width * $w / 1000)),
				\max(1, (int) \round($height * $h / 1000)),
				(int) \round($width * $x / 1000),
				(int) \round($height * $y / 1000)
			);
		}

		$this->resize($image, $recipe);

		foreach ($recipe->effects as $effect) {
			$this->effect($image, $effect);
		}

		if (null !== $recipe->watermark) {
			$this->watermark($image, $recipe->watermark);
		}

		// Imagick keeps a PNG's or a GIF's metadata (EXIF, comments) through the library's `strip`,
		// which only its JPEG, WebP, HEIC, JPEG 2000 and TIFF encoders apply.
		if (ImageDriverName::IMAGICK->value === $this->driverName) {
			$image->modify(new ImagickStripMeta());
		}

		$out = null === $recipe->format ? $mime : 'image/' . $recipe->format;

		return new RenderedImage(
			$image->encodeUsingMediaType($out, quality: $recipe->quality, strip: true)->toString(),
			$out
		);
	}

	/**
	 * A driver if this server has it: its extension, and for libvips its library through FFI.
	 */
	private static function driverOf(ImageDriverName $name): ?DriverInterface
	{
		try {
			$driver = match ($name) {
				ImageDriverName::VIPS => \class_exists(VipsDriver::class) && \extension_loaded('ffi')
					? new VipsDriver()
					: null,
				ImageDriverName::IMAGICK => \extension_loaded('imagick') ? new ImagickDriver() : null,
				ImageDriverName::GD      => \extension_loaded('gd') ? new GdDriver() : null,
				ImageDriverName::AUTO    => null,
			};

			$driver?->checkHealth();

			return $driver;
		} catch (Throwable) {
			// Installed but unusable here (libvips missing behind the FFI binding, for one).
			return null;
		}
	}

	/**
	 * Resized as asked, never beyond the image's own size: a size too large is brought down,
	 * keeping the ratio asked. Cropped to a size, it keeps the focal point in view (the center
	 * without one).
	 */
	private function resize(ImageInterface $image, ImageRecipe $recipe): void
	{
		if (null === $recipe->width && null === $recipe->height) {
			return;
		}

		$w     = $recipe->width ?? 0;
		$h     = $recipe->height ?? 0;
		$scale = \min(1, $w ? $image->width() / $w : 1, $h ? $image->height() / $h : 1);
		$w     = (int) \floor($w * $scale);
		$h     = (int) \floor($h * $scale);

		if ($w && $h && $recipe->cover) {
			if (null === $recipe->focal) {
				$image->cover($w, $h);
			} else {
				$this->coverAround($image, $w, $h, $recipe->focal);
			}
		} elseif ($w && $h) {
			$image->scale($w, $h);
		} elseif ($w) {
			$image->scale(width: $w);
		} elseif ($h) {
			$image->scale(height: $h);
		}
	}

	/**
	 * Cropped to the ratio asked, the window centered on the focal point as far as the image
	 * allows, then resized.
	 *
	 * @param array{int, int} $focal x, y in thousandths
	 */
	private function coverAround(ImageInterface $image, int $w, int $h, array $focal): void
	{
		$width  = $image->width();
		$height = $image->height();

		if ($width / $height > $w / $h) {
			$cw = (int) \round($height * $w / $h);
			$ch = $height;
		} else {
			$cw = $width;
			$ch = (int) \round($width * $h / $w);
		}

		$x = (int) \round($width * $focal[0] / 1000 - $cw / 2);
		$y = (int) \round($height * $focal[1] / 1000 - $ch / 2);

		$image->crop($cw, $ch, \max(0, \min($width - $cw, $x)), \max(0, \min($height - $ch, $y)));
		$image->resize($w, $h);
	}

	/**
	 * A declared watermark, sized to its share of the image's width, placed with its margin.
	 */
	private function watermark(ImageInterface $image, string $name): void
	{
		$spec = ImageWatermarks::get($name);

		if (null === $spec) {
			return;
		}

		$path = app()->getProjectDir()->resolve($spec['path']);

		if (!\is_file($path)) {
			throw new RuntimeException(\sprintf('The watermark "%s" has no image at "%s".', $name, $path));
		}

		$mark   = $this->manager->decodePath($path);
		$margin = (int) \round($image->width() * $spec['margin'] / 100);

		$mark->scale(width: \max(1, (int) \round($image->width() * $spec['width'] / 100)));
		$image->insert($mark, $margin, $margin, $spec['position'], $spec['opacity']);
	}

	private function effect(ImageInterface $image, string $effect): void
	{
		if ('grayscale' === $effect) {
			$image->grayscale();
		} elseif ('sepia' === $effect) {
			// No sepia in the library: gray, warmed toward brown.
			$image->grayscale()->colorize(30, 15, -10);
		} elseif ('sharpen' === $effect) {
			$image->sharpen(10);
		} elseif ('invert' === $effect) {
			$image->invert();
		} elseif (\preg_match('~^blur(\d+)$~', $effect, $m)) {
			// One blur pass of the former renderer is 5 levels of the library's 0 to 100.
			$image->blur(\min(100, 5 * (int) $m[1]));
		} elseif (\preg_match('~^(bright|contrast)(n?)(\d+)$~', $effect, $m)) {
			$level = ('n' === $m[2] ? -1 : 1) * (int) $m[3];

			'bright' === $m[1] ? $image->brightness($level) : $image->contrast($level);
		} elseif (\preg_match('~^pixel(\d+)$~', $effect, $m)) {
			$image->pixelate((int) $m[1]);
		} else {
			ImageTokens::handlerOf($effect)?->apply($image, $effect);
		}
	}
}
