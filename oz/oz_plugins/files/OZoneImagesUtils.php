<?php
	//SILO:: Protect from unauthorized access/include
	defined( 'OZ_SELF_SECURITY_CHECK' ) or die;

	require_once OZ_OZONE_DIR . 'oz_plugins' . DS . 'files' . DS . 'wideimage' . DS . 'WideImage.php';

	class OZoneImagesUtils {

		protected $image;

		protected $sourcePath;

		protected $imageResized = false;
		protected $imageExtensions = array( 'jpg', 'jpeg', 'png', 'gif' );

		public function __construct( $sourcePath, $format = 'JPEG' ) {
			$this->sourcePath = $sourcePath;
			$this->format = $format;
		}

		public function __destruct() {
			$this->destroy();
		}

		public function destroy() {
			if ( $this->image ) {
				$this->image->destroy();
			}
		}

		public function load() {
			if ( !$this->isValidImage() )
				return false;
			$this->image = WideImage::load( $this->sourcePath );

			return true;
		}

		public function isValidImage() {
			$src = $this->sourcePath;
			$extension = strtolower( substr( $src, ( strrpos( $src, '.' ) + 1 ) ) );

			if ( !in_array( $extension, $this->imageExtensions ) )
				return false;

			$data = file_get_contents( $src );
			$r = @imagecreatefromstring( $data );

			return ( is_resource( $r ) && get_resource_type( $r ) == 'gd' );
		}

		public function copyImage( $destPath ) {
			$this->image->saveToFile( $destPath );

			return $this;
		}

		public function adviceBestSize( $maxRW, $maxRH ) {
			$W;
			$H;
			$iW = $this->getWidth();
			$iH = $this->getHeight();
			$ratio = ( $iW > $iH ) ? $iH / $iW : $iW / $iH;
			$crop = ( $ratio < 0.6 ) ? true : false;

			if ( $iW <= $maxRW AND $iH <= $maxRH ) {

				$W = $iW;
				$H = $iH;
				$crop = false;

			} elseif ( !$crop ) {

				if ( $iW > $iH ) {
					$W = $maxRW * $ratio;
					$H = $W * $ratio;
				} elseif ( $iW < $iH ) {
					$H = $maxRH * $ratio;
					$W = $H * $ratio;
				} else {
					$W = min( $maxRW, $maxRH ) * $ratio;
					$H = min( $maxRW, $maxRH ) * $ratio;
				}

			} else {
				$W = min( $maxRW, $iW );
				$H = min( $maxRH, $iH );
			}

			return array(
				'w'    => ceil( $W ),
				'h'    => ceil( $H ),
				'crop' => $crop
			);
		}

		public function resizeImage( $width, $height, $crop = true ) {
			$iWidth = $this->getWidth();
			$iHeight = $this->getHeight();

			$this->imageResized = ( $width <= $iWidth ) || ( isset( $height ) && $height <= $iHeight ) ? true : false;

			if ( !$width ) {
				$width = $iWidth;
			} else {
				$width = $width > $iWidth ? $iWidth : $width;
			}

			if ( !$height ) {
				$height = $iHeight;
			} else {
				$height = $height > $iHeight ? $iHeight : $height;
			}

			if ( $crop ) {
				$wHalf = ceil( $width / 2 );
				$hHalf = ceil( $height / 2 );

				$this->image = $this->image
					->resize( $width, $height, 'outside' )
					->crop( '50%-' . $wHalf, '50%-' . $hHalf, $width, $height );
			} else {
				$this->image = $this->image->resize( $width, $height );
			}

			return $this;
		}

		public function cropImage( $left, $top, $width, $height ) {
			$this->image = $this->image->crop( $left, $top, $width, $height );

			return $this;
		}

		public function saveImage( $destPath = null, $quality = 90 ) {
			if ( !isset( $destPath ) ) {
				$this->image->saveToFile( $this->sourcePath, $quality );
			} else {
				$this->image->saveToFile( $destPath, $quality );
			}

			return $this;
		}

		public function getWidth() {
			return $this->image->getWidth();
		}

		public function getHeight() {
			return $this->image->getHeight();
		}

		public function imageResized() {
			return $this->imageResized;
		}

		public function rotate( $angle, $bgColor = null, $ignoreTransparent = true ) {
			if ( intval( $angle ) !== 0 ) {
				$this->image = $this->image->rotate( $angle, $bgColor, $ignoreTransparent );
			}

			return $this;
		}

		private function safeCoords( $coords ) {
			$x = $coords[ 'x' ];
			$y = $coords[ 'y' ];
			$w = $coords[ 'w' ];
			$h = $coords[ 'h' ];
			$min_size = OZoneSettings::get( 'oz.user', 'OZ_PPIC_MIN_SIZE' );

			return $x >= 0
			AND $y >= 0
			AND $w >= ( $min_size + $x )
			AND $h >= ( $min_size + $y )
			AND ( $x + $w ) <= $this->getWidth()
			AND ( $y + $h ) <= $this->getHeight();
		}

		public function cropAndSave( $path, $quality = 90, $maxW, $maxH, $coords = null, $resize = true ) {

			if ( !empty( $coords ) ) {

				if ( $this->safeCoords( $coords ) ) {
					$x = $coords[ 'x' ];
					$y = $coords[ 'y' ];
					$w = $coords[ 'w' ];
					$h = $coords[ 'h' ];

					if ( $resize ) {
						return $this->cropImage( $x, $y, $w, $h )->resizeImage( $maxW, $maxH )->saveImage( $path, $quality );
					}

					//permet de garder l'image tel qu'il a ete cropper par user
					return $this->cropImage( $x, $y, $w, $h )->saveImage( $path, $quality );
				}
			}

			//alors on fait un resize
			return $this->resizeImage( $maxW, $maxH )->saveImage( $path, $quality );
		}
	}