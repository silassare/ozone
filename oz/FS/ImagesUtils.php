<?php
	/**
	 * Copyright (c) 2017-present, Emile Silas Sare
	 *
	 * This file is part of OZone (O'Zone) package.
	 *
	 * For the full copyright and license information, please view the LICENSE
	 * file that was distributed with this source code.
	 */

	namespace OZONE\OZ\FS;

	use claviska\SimpleImage;
	use OZONE\OZ\Core\SettingsManager;

	defined('OZ_SELF_SECURITY_CHECK') or die;

	class ImagesUtils
	{
		/**
		 * SimpleImage object.
		 *
		 * @var \claviska\SimpleImage
		 */
		protected $image;

		/**
		 * The current file source path.
		 *
		 * @var string
		 */
		protected $source_path;

		/**
		 * Allowed image extension.
		 *
		 * @var array
		 */
		protected $image_extensions = ['jpg', 'jpeg', 'png', 'gif', "webp"];

		/**
		 * ImagesUtils constructor.
		 *
		 * @param string $source_path the image file source path
		 */
		public function __construct($source_path)
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
		public function destroy()
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
		public function getString($mime = 'image/jpeg', $quality = 100)
		{
			return $this->image->toString($mime, $quality);
		}

		/**
		 * Outputs the image to browser in jpeg format.
		 *
		 * @param string $mime
		 * @param int    $quality Image quality
		 */
		function output($mime = 'image/jpeg', $quality = 100)
		{
			// important because the size will change when response is gzipped
			header_remove('Content-Length');
			$this->image->toScreen($mime, $quality);
		}

		/**
		 * Loads the image file.
		 *
		 * @return bool The returned value is true if successful, false otherwise.
		 * @throws \Exception
		 */
		public function load()
		{
			if (!$this->isValidImage()) return false;

			$this->image = new SimpleImage;

			$this->image->fromFile($this->source_path);

			return true;
		}

		/**
		 * Checks if this image is valid.
		 *
		 * @return bool
		 */
		public function isValidImage()
		{
			$src       = $this->source_path;
			$extension = strtolower(substr($src, (strrpos($src, '.') + 1)));

			if (!in_array($extension, $this->image_extensions)) return false;

			$data = file_get_contents($src);
			$r    = @imagecreatefromstring($data);

			return (is_resource($r) && get_resource_type($r) == 'gd');
		}

		/**
		 * Copy the current modified image to the desired destination path.
		 *
		 * @param string $destination_path
		 *
		 * @return \OZONE\OZ\FS\ImagesUtils
		 * @throws \Exception
		 */
		public function copyImage($destination_path)
		{
			$this->image->toFile($destination_path);

			return $this;
		}

		/**
		 * Advice on the best crop/resize width and height
		 * for the image with a given max output width and height.
		 *
		 * @param int $max_out_width  The max output width in pixel.
		 * @param int $max_out_height The max output height in pixel.
		 *
		 * @return array
		 */
		public function adviceBestSize($max_out_width, $max_out_height)
		{
			$iW    = $this->getWidth();
			$iH    = $this->getHeight();
			$ratio = ($iW > $iH) ? $iH / $iW : $iW / $iH;
			$crop  = ($ratio < 0.6) ? true : false;

			if ($iW <= $max_out_width AND $iH <= $max_out_height) {
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
					$W = min($max_out_width, $max_out_height) * $ratio;
					$H = min($max_out_width, $max_out_height) * $ratio;
				}
			} else {
				$W = min($max_out_width, $iW);
				$H = min($max_out_height, $iH);
			}

			return ['w' => ceil($W), 'h' => ceil($H), 'crop' => $crop];
		}

		/**
		 * Resize the image to the given width and height.
		 *
		 * @param int  $width  The desired width in pixel
		 * @param int  $height The desired height in pixel
		 * @param bool $crop   Should we crop when required? default is true
		 *
		 * @return \OZONE\OZ\FS\ImagesUtils
		 */
		public function resizeImage($width, $height, $crop = true)
		{
			$iWidth  = $this->getWidth();
			$iHeight = $this->getHeight();

			if (!$width) {
				$width = $iWidth;
			} else {
				$width = $width > $iWidth ? $iWidth : $width;
			}

			if (!$height) {
				$height = $iHeight;
			} else {
				$height = $height > $iHeight ? $iHeight : $height;
			}

			if ($crop) {
				$this->image->thumbnail($width, $height, "center");
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
		 * @return \OZONE\OZ\FS\ImagesUtils
		 */
		public function cropImage($left, $top, $width, $height)
		{
			$this->image = $this->image->crop($left, $top, $width, $height);

			return $this;
		}

		/**
		 * Save the current image to a given desired destination path.
		 *
		 * If no destination path, the source file will be overwrite.
		 *
		 * @param string|null $destination_path The destination file path
		 * @param int         $quality          The image quality between 0 and 100, default is 90
		 *
		 * @return \OZONE\OZ\FS\ImagesUtils
		 * @throws \Exception
		 */
		public function saveImage($destination_path = null, $quality = 90)
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
		public function getWidth()
		{
			return $this->image->getWidth();
		}

		/**
		 * Gets current image height.
		 *
		 * @return int
		 */
		public function getHeight()
		{
			return $this->image->getHeight();
		}

		/**
		 * Checks if a given coordinate is safe for cropping.
		 *
		 * @param array $coordinate
		 *
		 * @return bool
		 */

		private function safeCoordinate(array $coordinate)
		{
			$x        = $coordinate['x'];
			$y        = $coordinate['y'];
			$w        = $coordinate['w'];
			$h        = $coordinate['h'];
			$min_size = SettingsManager::get('oz.users', 'OZ_PPIC_MIN_SIZE');

			return $x >= 0 AND $y >= 0 AND $w >= ($min_size + $x) AND $h >= ($min_size + $y) AND ($x + $w) <= $this->getWidth() AND ($y + $h) <= $this->getHeight();
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
		 * @param array|null $coordinate       The crop zone coordinate
		 * @param bool       $resize           Should we resize when required? default is true
		 *
		 * @return \OZONE\OZ\FS\ImagesUtils
		 * @throws \Exception
		 */
		public function cropAndSave($destination_path, $quality, $max_width, $max_height, array $coordinate = null, $resize = true)
		{
			$quality = empty($quality) ? 90 : $quality;

			if (!empty($coordinate)) {
				if ($this->safeCoordinate($coordinate)) {
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
			}

			return $this->resizeImage($max_width, $max_height)
						->saveImage($destination_path, $quality);
		}
	}