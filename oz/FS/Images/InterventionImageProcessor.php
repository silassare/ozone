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
	private readonly string $driverName;

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
				$this->driverName = $name->value;
				$this->manager    = new ImageManager($found, autoOrientation: true, strip: true);

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
	public function render(string $bytes, string $mime, ImageRecipe $recipe): string
	{
		$image = $this->manager->decodeBinary($bytes);

		$this->resize($image, $recipe);

		foreach ($recipe->effects as $effect) {
			$this->effect($image, $effect);
		}

		// Imagick keeps a PNG's or a GIF's metadata (EXIF, comments) through the library's `strip`,
		// which only its JPEG, WebP, HEIC, JPEG 2000 and TIFF encoders apply.
		if (ImageDriverName::IMAGICK->value === $this->driverName) {
			$image->modify(new ImagickStripMeta());
		}

		return $image->encodeUsingMediaType($mime, quality: $recipe->quality, strip: true)->toString();
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
	 * keeping the ratio asked.
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

		if ($w && $h) {
			$recipe->cover ? $image->cover($w, $h) : $image->scale($w, $h);
		} elseif ($w) {
			$image->scale(width: $w);
		} elseif ($h) {
			$image->scale(height: $h);
		}
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
		} elseif (\preg_match('~^blur(\d+)$~', $effect, $m)) {
			// One blur pass of the former renderer is 5 levels of the library's 0 to 100.
			$image->blur(\min(100, 5 * (int) $m[1]));
		}
	}
}
