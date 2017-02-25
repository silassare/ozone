<?php
	//SILO:: Protect from unauthorized access/include
	defined( 'OZ_SELF_SECURITY_CHECK' ) or die;

	final class OZoneServiceCaptchaCode extends OZoneService {
		private static $REG_CAPTCHA_FILE_URL = "#^([a-z0-9]{32})\.png$#";

		private static $default_cfg = array(
			'code'            => '',
			'min_length'      => 5,
			'max_length'      => 5,
			'backgrounds'     => array(
				'45-degree-fabric.png',
				'cloth-alike.png',
				'grey-sandbag.png',
				'kinda-jean.png',
				'polyester-lite.png',
				'stitched-wool.png',
				'white-carbon.png',
				'white-wave.png'
			),
			'fonts'           => array(
				'times_new_yorker.ttf'
			),
			'characters'      => 'ABCDEFGHJKLMNPRSTUVWXYZabcdefghjkmnprstuvwxyz23456789',
			'min_font_size'   => 28,
			'max_font_size'   => 28,
			'color'           => '#666',
			'angle_min'       => 0,
			'angle_max'       => 10,
			'shadow'          => true,
			'shadow_color'    => '#fff',
			'shadow_offset_x' => -1,
			'shadow_offset_y' => 1
		);

		public function __construct() {
			parent::__construct();
		}

		public function execute( $request = array() ) {
			$extra_ok = OZoneUri::parseServiceUriExtra( self::$REG_CAPTCHA_FILE_URL, array( 'key' ), $request );

			OZoneAssert::assertAuthorizeAction( $extra_ok, new OZoneErrorNotFound() );

			$this->drawImage( $request );
		}

		public static function gen( $config = array() ) {
			// Overwrite defaults with custom config values
			self::mergeConfig( $config );

			// Use milliseconds instead of seconds
			srand( microtime() * 100 );

			// Generate code if not set by user
			if ( empty( self::$default_cfg[ 'code' ] ) ) {
				$code = '';
				$length = rand( self::$default_cfg[ 'min_length' ], self::$default_cfg[ 'max_length' ] );
				while ( strlen( self::$default_cfg[ 'code' ] ) < $length ) {
					$code .= substr( self::$default_cfg[ 'characters' ], rand() % ( strlen( self::$default_cfg[ 'characters' ] ) ), 1 );
				}

				self::$default_cfg[ 'code' ] = $config[ 'code' ] = $code;
			}

			$key = md5( microtime() );
			$img_src = $key . '.png';

			self::saveConfig( $key, $config );

			return $img_src;
		}

		private static function mergeConfig( $config ) {
			// Overwrite defaults with custom config values
			if ( is_array( $config ) ) {
				foreach ( $config as $key => $value )
					self::$default_cfg[ $key ] = $value;
			}

			if ( self::$default_cfg[ 'min_length' ] < 1 )
				self::$default_cfg[ 'min_length' ] = 1;
			if ( self::$default_cfg[ 'angle_min' ] < 0 )
				self::$default_cfg[ 'angle_min' ] = 0;
			if ( self::$default_cfg[ 'angle_max' ] > 10 )
				self::$default_cfg[ 'angle_max' ] = 10;
			if ( self::$default_cfg[ 'angle_max' ] < self::$default_cfg[ 'angle_min' ] )
				self::$default_cfg[ 'angle_max' ] = self::$default_cfg[ 'angle_min' ];
			if ( self::$default_cfg[ 'min_font_size' ] < 10 )
				self::$default_cfg[ 'min_font_size' ] = 10;
			if ( self::$default_cfg[ 'max_font_size' ] < self::$default_cfg[ 'min_font_size' ] )
				self::$default_cfg[ 'max_font_size' ] = self::$default_cfg[ 'min_font_size' ];
		}

		private static function saveConfig( $key, $config ) {
			$key = '_captcha_cfg' . $key;

			$_SESSION[ $key ] = serialize( $config );
		}

		private static function delConfig( $key ) {
			$key = '_captcha_cfg' . $key;

			unset( $_SESSION[ $key ] );
		}

		private static function getConfig( $key ) {
			if ( !is_string( $key ) )
				return false;

			$key = '_captcha_cfg' . $key;

			if ( !isset( $_SESSION[ $key ] ) )
				return false;

			return unserialize( $_SESSION[ $key ] );
		}

		private function drawImage( $request ) {
			OZoneAssert::assertForm( $request, array( 'key' ) );

			$cfg = self::getConfig( $request[ 'key' ] );

			if ( !is_array( $cfg ) )
				throw new OZoneErrorNotFound();

			self::mergeConfig( $cfg );

			self::delConfig( $request[ 'key' ] );

			$CAPTCHA_DIR = OZ_OZONE_ASSETS_DIR . 'captcha' . DS;

			srand( microtime() * 100 );

			$background = $CAPTCHA_DIR . self::$default_cfg[ 'backgrounds' ][ rand( 0, count( self::$default_cfg[ 'backgrounds' ] ) - 1 ) ];

			$captcha = imagecreatefrompng( $background );

			$bg_width = imagesx( $captcha );
			$bg_height = imagesy( $captcha );

			$color = self::hex2rgb( self::$default_cfg[ 'color' ] );
			$color = imagecolorallocate( $captcha, $color[ 'r' ], $color[ 'g' ], $color[ 'b' ] );

			$angle = rand( self::$default_cfg[ 'angle_min' ], self::$default_cfg[ 'angle_max' ] ) * ( rand( 0, 1 ) == 1 ? -1 : 1 );

			$font = $CAPTCHA_DIR . self::$default_cfg[ 'fonts' ][ rand( 0, count( self::$default_cfg[ 'fonts' ] ) - 1 ) ];

			$font_size = rand( self::$default_cfg[ 'min_font_size' ], self::$default_cfg[ 'max_font_size' ] );
			$text_box_size = imagettfbbox( $font_size, $angle, $font, self::$default_cfg[ 'code' ] );

			$box_width = abs( $text_box_size[ 6 ] - $text_box_size[ 2 ] );
			$box_height = abs( $text_box_size[ 5 ] - $text_box_size[ 1 ] );
			$text_pos_x_min = 0;
			$text_pos_x_max = ( $bg_width ) - ( $box_width );
			$text_pos_x = rand( $text_pos_x_min, $text_pos_x_max );
			$text_pos_y_min = $box_height;
			$text_pos_y_max = ( $bg_height ) - ( $box_height / 2 );
			$text_pos_y = rand( $text_pos_y_min, $text_pos_y_max );

			if ( self::$default_cfg[ 'shadow' ] ) {
				$shadow_color = self::hex2rgb( self::$default_cfg[ 'shadow_color' ] );
				$shadow_color = imagecolorallocate( $captcha, $shadow_color[ 'r' ], $shadow_color[ 'g' ], $shadow_color[ 'b' ] );
				imagettftext( $captcha, $font_size, $angle, $text_pos_x + self::$default_cfg[ 'shadow_offset_x' ], $text_pos_y + self::$default_cfg[ 'shadow_offset_y' ], $shadow_color, $font, self::$default_cfg[ 'code' ] );
			}

			imagettftext( $captcha, $font_size, $angle, $text_pos_x, $text_pos_y, $color, $font, self::$default_cfg[ 'code' ] );

			header( 'Content-type: image/png' );
			flush();
			imagepng( $captcha );
		}

		private static function hex2rgb( $hex_str, $return_string = false, $separator = ',' ) {
			$hex_str = preg_replace( "/[^0-9A-Fa-f]/", '', $hex_str ); // Gets a proper hex string
			$rgb_array = array();
			if ( strlen( $hex_str ) == 6 ) {
				$color_val = hexdec( $hex_str );
				$rgb_array[ 'r' ] = 0xFF & ( $color_val >> 0x10 );
				$rgb_array[ 'g' ] = 0xFF & ( $color_val >> 0x8 );
				$rgb_array[ 'b' ] = 0xFF & $color_val;
			} elseif ( strlen( $hex_str ) == 3 ) {
				$rgb_array[ 'r' ] = hexdec( str_repeat( substr( $hex_str, 0, 1 ), 2 ) );
				$rgb_array[ 'g' ] = hexdec( str_repeat( substr( $hex_str, 1, 1 ), 2 ) );
				$rgb_array[ 'b' ] = hexdec( str_repeat( substr( $hex_str, 2, 1 ), 2 ) );
			} else {
				return false;
			}

			return $return_string ? implode( $separator, $rgb_array ) : $rgb_array;
		}
	}