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

namespace OZONE\Core\FS;

use claviska\SimpleImage;
use Exception;
use OZONE\Core\App\Settings;

/**
 * Class ImagesUtils.
 *
 * @deprecated
 */
class ImagesUtils
{
	protected ?SimpleImage $image;

	/**
	 * The current file source path.
	 *
	 * @var string
	 */
	protected string $source_path;

	/**
	 * Allowed image extension.
	 *
	 * @var array
	 */
	protected array $image_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

	/**
	 * ImagesUtils constructor.
	 *
	 * @param string $source_path the image file source path
	 */
	public function __construct(string $source_path)
	{
		$this->source_path = $source_path;
	}

	/**
	 * ImagesUtils destructor.
	 */
	public function __destruct()
	{
		$this->destroy();
	}

	/**
	 * Destroy image object.
	 */
	public function destroy(): void
	{
		if ($this->image) {
			$this->image = null;
		}
	}

	/**
	 * @param string $mime
	 * @param int    $quality
	 *
	 * @return string
	 */
	public function getString(string $mime = 'image/jpeg', int $quality = 100): string
	{
		return $this->image->toString($mime, $quality);
	}

	/**
	 * Outputs the image to browser in jpeg format.
	 *
	 * @param string $mime
	 * @param int    $quality Image quality
	 */
	public function output(string $mime = 'image/jpeg', int $quality = 100): void
	{
		// important because the size will change when response is gzipped
		\header_remove('Content-Length');
		$this->image->toScreen($mime, $quality);
	}

	/**
	 * Loads the image file.
	 *
	 * @return bool the returned value is true if successful, false otherwise
	 *
	 * @throws Exception
	 */
	public function load(): bool
	{
		if (!$this->isValidImage()) {
			return false;
		}

		$this->image = new SimpleImage();

		$this->image->fromFile($this->source_path);

		return true;
	}

	/**
	 * Checks if this image is valid.
	 *
	 * @return bool
	 */
	public function isValidImage(): bool
	{
		$src       = $this->source_path;
		$extension = \strtolower(\substr($src, \strrpos($src, '.') + 1));

		if (!\in_array($extension, $this->image_extensions, true)) {
			return false;
		}

		$r = @\imagecreatefromstring(\file_get_contents($src));

		/**
		 * @psalm-suppress TypeDoesNotContainType
		 * @psalm-suppress NoValue
		 */
		return \is_resource($r) && 'gd' === \get_resource_type($r);
	}

	/**
	 * Copy the current modified image to the desired destination path.
	 *
	 * @param string $destination_path
	 *
	 * @return ImagesUtils
	 *
	 * @throws Exception
	 */
	public function copyImage(string $destination_path): self
	{
		$this->image->toFile($destination_path);

		return $this;
	}

	/**
	 * Advice on the best crop/resize width and height
	 * for the image with a given max output width and height.
	 *
	 * @param int $max_out_width  the max output width in pixel
	 * @param int $max_out_height the max output height in pixel
	 *
	 * @return array
	 */
	public function adviceBestSize(int $max_out_width, int $max_out_height): array
	{
		$iW    = $this->getWidth();
		$iH    = $this->getHeight();
		$ratio = ($iW > $iH) ? $iH / $iW : $iW / $iH;
		$crop  = $ratio < 0.6;

		if ($iW <= $max_out_width && $iH <= $max_out_height) {
			$W    = $iW;
			$H    = $iH;
			$crop = false;
		} elseif (!$crop) {
			if ($iW > $iH) {
				$W = $max_out_width * $ratio;
				$H = $W * $ratio;
			} elseif ($iW < $iH) {
				$H = $max_out_height * $ratio;
				$W = $H * $ratio;
			} else {
				$W = \min($max_out_width, $max_out_height) * $ratio;
				$H = \min($max_out_width, $max_out_height) * $ratio;
			}
		} else {
			$W = \min($max_out_width, $iW);
			$H = \min($max_out_height, $iH);
		}

		return ['w' => \ceil($W), 'h' => \ceil($H), 'crop' => $crop];
	}

	/**
	 * Resize the image to the given width and height.
	 *
	 * @param int  $width  The desired width in pixel
	 * @param int  $height The desired height in pixel
	 * @param bool $crop   Should we crop when required? default is true
	 *
	 * @return ImagesUtils
	 */
	public function resizeImage(int $width, int $height, bool $crop = true): self
	{
		$iWidth  = $this->getWidth();
		$iHeight = $this->getHeight();

		if (!$width) {
			$width = $iWidth;
		} else {
			$width = \min($width, $iWidth);
		}

		if (!$height) {
			$height = $iHeight;
		} else {
			$height = \min($height, $iHeight);
		}

		if ($crop) {
			$this->image->thumbnail($width, $height, 'center');
		} else {
			$this->image->resize($width, $height);
		}

		return $this;
	}

	/**
	 * Crop the current image.
	 *
	 * @param int $left   The left start position in pixel
	 * @param int $top    The top start position in pixel
	 * @param int $width  The output width in pixel
	 * @param int $height The output height in pixel
	 *
	 * @return ImagesUtils
	 */
	public function cropImage(int $left, int $top, int $width, int $height): self
	{
		$this->image = $this->image->crop($left, $top, $width, $height);

		return $this;
	}

	/**
	 * Save the current image to a given desired destination path.
	 *
	 * If no destination path, the source file will be overwrite.
	 *
	 * @param null|string $destination_path The destination file path
	 * @param int         $quality          The image quality between 0 and 100, default is 90
	 *
	 * @return ImagesUtils
	 *
	 * @throws Exception
	 */
	public function saveImage(?string $destination_path = null, int $quality = 90): self
	{
		if (!isset($destination_path)) {
			$this->image->toFile($this->source_path, null, $quality);
		} else {
			$this->image->toFile($destination_path, null, $quality);
		}

		return $this;
	}

	/**
	 * Gets current image width.
	 *
	 * @return int
	 */
	public function getWidth(): int
	{
		return $this->image->getWidth();
	}

	/**
	 * Gets current image height.
	 *
	 * @return int
	 */
	public function getHeight(): int
	{
		return $this->image->getHeight();
	}

	/**
	 * Crop the current image and save it to a given destination path.
	 *
	 * If no destination path, the source file will be overwrite.
	 *
	 * @param string     $destination_path The destination file path
	 * @param int        $quality          The image quality between 0 and 100, default is 90
	 * @param int        $max_width        The max output width in pixel
	 * @param int        $max_height       The max output height in pixel
	 * @param null|array $coordinate       The crop zone coordinate
	 * @param bool       $resize           Should we resize when required? default is true
	 *
	 * @return ImagesUtils
	 *
	 * @throws Exception
	 */
	public function cropAndSave(
		string $destination_path,
		int $quality,
		int $max_width,
		int $max_height,
		?array $coordinate = null,
		bool $resize = true
	): self {
		$quality = empty($quality) ? 90 : $quality;

		if (!empty($coordinate) && $this->safeCoordinate($coordinate)) {
			$x = $coordinate['x'];
			$y = $coordinate['y'];
			$w = $coordinate['w'];
			$h = $coordinate['h'];

			if ($resize) {
				return $this->cropImage($x, $y, $w, $h)
					->resizeImage($max_width, $max_height)
					->saveImage($destination_path, $quality);
			}

			return $this->cropImage($x, $y, $w, $h)
				->saveImage($destination_path, $quality);
		}

		return $this->resizeImage($max_width, $max_height)
			->saveImage($destination_path, $quality);
	}

	/**
	 * Checks if a given coordinate is safe for cropping.
	 *
	 * @param array $coordinate
	 *
	 * @return bool
	 */
	private function safeCoordinate(array $coordinate): bool
	{
		$x        = $coordinate['x'];
		$y        = $coordinate['y'];
		$w        = $coordinate['w'];
		$h        = $coordinate['h'];
		$min_size = Settings::get('oz.users', 'OZ_USER_PIC_MIN_SIZE');

		return $x >= 0
			&& $y >= 0
			&& $w >= ($min_size + $x)
			&& $h >= ($min_size + $y)
			&& ($x + $w) <= $this->getWidth()
			&& ($y + $h) <= $this->getHeight();
	}
}
