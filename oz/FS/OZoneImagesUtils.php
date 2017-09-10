<?php
	/**
	 * Copyright (c) Emile Silas Sare <emile.silas@gmail.com>
	 *
	 * This file is part of the OZone package.
	 *
	 * For the full copyright and license information, please view the LICENSE
	 * file that was distributed with this source code.
	 */

	namespace OZONE\OZ\FS;

	use OZONE\OZ\Core\OZoneSettings;

	defined('OZ_SELF_SECURITY_CHECK') or die;

	require_once OZ_OZONE_DIR . 'oz_vendors' . DS . 'wideimage' . DS . 'WideImage.php';

	class OZoneImagesUtils
	{

		/**
		 * @var \WideImage_Image
		 */
		protected $image;

		/**
		 * the current file source path
		 *
		 * @var string
		 */
		protected $source_path;

		/**
		 * @var bool
		 */

		protected $image_resized = false;

		/**
		 * allowed image extension
		 *
		 * @var array
		 */
		protected $image_extensions = ['jpg', 'jpeg', 'png', 'gif'];

		/**
		 * OZoneImagesUtils constructor.
		 *
		 * @param string $source_path the image file source path
		 */
		public function __construct($source_path)
		{
			$this->source_path = $source_path;
		}

		/**
		 * OZoneImagesUtils destructor.
		 */
		public function __destruct()
		{
			$this->destroy();
		}

		/**
		 * destroy WideImage_Image object
		 */
		public function destroy()
		{
			if ($this->image) {
				$this->image->destroy();
			}
		}

		/**
		 * Outputs the image to browser in jpeg format
		 *
		 * Sets headers Content-length and Content-type, and echoes the image in jpeg.
		 * All other headers (such as Content-disposition) must be added manually.
		 *
		 * @param string $quality Image quality
		 */
		function outputJpeg($quality)
		{
			$data  = $this->image->asString('jpeg');
			$img_r = imagecreatefromstring($data);

			header('Content-type: image/jpeg');
			// important car la taille changera a cause de la compresion
			header_remove('Content-Length');

			imagejpeg($img_r, null, $quality);

			// free memory
			imagedestroy($img_r);
		}

		/**
		 * load the image file
		 *
		 * @return bool true if successful, false if fail
		 */
		public function load()
		{
			if (!$this->isValidImage()) return false;
			$this->image = \WideImage::load($this->source_path);

			return true;
		}

		/**
		 * check if this image is valid
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
		 * copy the current modified image to the desired destination path
		 *
		 * @param string $destination_path
		 *
		 * @return \OZONE\OZ\FS\OZoneImagesUtils
		 */
		public function copyImage($destination_path)
		{
			$this->image->saveToFile($destination_path);

			return $this;
		}

		/**
		 * advice on the best crop/resize width and height for the image with a given max output width and height
		 *
		 * @param int $max_out_width  the max output width in pixel
		 * @param int $max_out_height the max output height in pixel
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
		 * @param int  $width  the desired width in pixel
		 * @param int  $height the desired height in pixel
		 * @param bool $crop   should we crop when required? default is true
		 *
		 * @return \OZONE\OZ\FS\OZoneImagesUtils
		 */
		public function resizeImage($width, $height, $crop = true)
		{
			$iWidth  = $this->getWidth();
			$iHeight = $this->getHeight();

			$this->image_resized = ($width <= $iWidth) || (isset($height) && $height <= $iHeight) ? true : false;

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
				$wHalf = ceil($width / 2);
				$hHalf = ceil($height / 2);

				$this->image = $this->image->resize($width, $height, 'outside')
										   ->crop('50%-' . $wHalf, '50%-' . $hHalf, $width, $height);
			} else {
				$this->image = $this->image->resize($width, $height);
			}

			return $this;
		}

		/**
		 * crop the current image
		 *
		 * @param int $left   the left start pos in pixel
		 * @param int $top    the top start pos in pixel
		 * @param int $width  the output width in pixel
		 * @param int $height the output height in pixel
		 *
		 * @return \OZONE\OZ\FS\OZoneImagesUtils
		 */
		public function cropImage($left, $top, $width, $height)
		{
			$this->image = $this->image->crop($left, $top, $width, $height);

			return $this;
		}

		/**
		 * save the current image to a given desired destination path
		 *
		 * if no destination path, the source file will be overwrite
		 *
		 * @param string|null $destination_path the destination file path
		 * @param int         $quality          the image quality between 0 and 100, default is 90
		 *
		 * @return \OZONE\OZ\FS\OZoneImagesUtils
		 */
		public function saveImage($destination_path = null, $quality = 90)
		{
			if (!isset($destination_path)) {
				$this->image->saveToFile($this->source_path, $quality);
			} else {
				$this->image->saveToFile($destination_path, $quality);
			}

			return $this;
		}

		/**
		 * get current image width
		 *
		 * @return int
		 */
		public function getWidth()
		{
			return $this->image->getWidth();
		}

		/**
		 * get current image height
		 *
		 * @return int
		 */
		public function getHeight()
		{
			return $this->image->getHeight();
		}

		/**
		 * check if the image is resized
		 *
		 * @return bool
		 */
		public function imageResized()
		{
			return $this->image_resized;
		}

		/**
		 * check if a given coordinate is safe for cropping
		 *
		 * @param array $coordinate
		 *
		 * @return bool
		 * @throws \OZONE\OZ\Exceptions\OZoneInternalError when can't load 'oz.user' setting
		 */

		private function safeCoordinate(array $coordinate)
		{
			$x        = $coordinate['x'];
			$y        = $coordinate['y'];
			$w        = $coordinate['w'];
			$h        = $coordinate['h'];
			$min_size = OZoneSettings::get('oz.user', 'OZ_PPIC_MIN_SIZE');

			return $x >= 0 AND $y >= 0 AND $w >= ($min_size + $x) AND $h >= ($min_size + $y) AND ($x + $w) <= $this->getWidth() AND ($y + $h) <= $this->getHeight();
		}

		/**
		 * crop the current image and save it to a given destination path
		 *
		 * @param string     $destination_path the destination file path
		 * @param int        $quality          the image quality between 0 and 100, default is 90
		 * @param int        $max_width        the max output width in pixel
		 * @param int        $max_height       the max output height in pixel
		 * @param array|null $coordinate       the crop zone coordinate
		 * @param bool       $resize           should we resize when required? default is true
		 *
		 * @return \OZONE\OZ\FS\OZoneImagesUtils
		 */
		public function cropAndSave($destination_path, $quality = 90, $max_width, $max_height, array $coordinate = null, $resize = true)
		{
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

					// permet de garder l'image tel qu'il a ete cropper par user
					return $this->cropImage($x, $y, $w, $h)
								->saveImage($destination_path, $quality);
				}
			}

			// alors on fait un resize
			return $this->resizeImage($max_width, $max_height)
						->saveImage($destination_path, $quality);
		}
	}