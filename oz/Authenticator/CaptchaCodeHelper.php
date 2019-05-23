<?php
	/**
	 * Copyright (c) 2017-present, Emile Silas Sare
	 *
	 * This file is part of OZone (O'Zone) package.
	 *
	 * For the full copyright and license information, please view the LICENSE
	 * file that was distributed with this source code.
	 */

	namespace OZONE\OZ\Authenticator;

	use OZONE\OZ\Core\Context;
	use OZONE\OZ\Core\Hasher;
	use OZONE\OZ\Exceptions\NotFoundException;
	use OZONE\OZ\Core\SettingsManager;
	use OZONE\OZ\Http\Body;

	defined('OZ_SELF_SECURITY_CHECK') or die;

	/**
	 * Class CaptchaCodeHelper
	 *
	 * @package OZONE\OZ\Authenticator
	 */
	final class CaptchaCodeHelper
	{

		private static $default_config = [
			'backgrounds'     => [
				'45-degree-fabric.png',
				'cloth-alike.png',
				'grey-sandbag.png',
				'kinda-jean.png',
				'polyester-lite.png',
				'stitched-wool.png',
				'white-carbon.png',
				'white-wave.png'
			],
			'fonts'           => ['times_new_yorker.ttf'],
			'min_font_size'   => 28,
			'max_font_size'   => 28,
			'color'           => '#666',
			'angle_min'       => 0,
			'angle_max'       => 10,
			'shadow'          => true,
			'shadow_color'    => '#fff',
			'shadow_offset_x' => -1,
			'shadow_offset_y' => 1
		];

		/**
		 * Gets captcha image uri for authentication
		 *
		 * @param \OZONE\OZ\Core\Context                $context
		 * @param \OZONE\OZ\Authenticator\Authenticator $auth
		 *
		 * @return array the captcha info
		 * @throws \Exception
		 */
		public static function getCaptcha(Context $context, Authenticator $auth)
		{
			$generated = $auth->generate()
							  ->getGenerated();

			$captcha_key = md5($auth->getLabel() . $auth->getForValue() . microtime());

			$f_name  = SettingsManager::get('oz.files', 'OZ_CAPTCHA_FILE_NAME');
			$img_src = str_replace(['{oz_captcha_key}'], [$captcha_key], $f_name);

			$context->getSession()
					->set('captcha_cfg:' . $captcha_key, $generated['auth_code']);

			return ['captcha_src' => $img_src];
		}

		/**
		 * Creates the captcha image
		 *
		 * @param \OZONE\OZ\Core\Context $context
		 * @param string                 $captcha_key
		 *
		 * @return \OZONE\OZ\Http\Response
		 * @throws \OZONE\OZ\Exceptions\NotFoundException when captcha image key is not valid
		 */
		public static function generateCaptchaImage(Context $context, $captcha_key)
		{
			$code     = null;
			$response = $context->getResponse();
			$session  = $context->getSession();

			if (is_string($captcha_key)) {
				$code = $session->get('captcha_cfg:' . $captcha_key);
			}

			if (empty($code)) {
				throw new NotFoundException();
			}

			$session->remove('captcha_cfg:' . $captcha_key);

			$CAPTCHA_DIR = OZ_OZONE_DIR . 'oz_assets' . DS . 'captcha' . DS;

			srand(Hasher::genSeed());

			$background = $CAPTCHA_DIR . self::$default_config['backgrounds'][rand(0, count(self::$default_config['backgrounds']) - 1)];

			$captcha = imagecreatefrompng($background);

			$bg_width  = imagesx($captcha);
			$bg_height = imagesy($captcha);

			$color = self::hex2rgb(self::$default_config['color']);
			$color = imagecolorallocate($captcha, $color['r'], $color['g'], $color['b']);

			$angle = rand(self::$default_config['angle_min'], self::$default_config['angle_max']) * (rand(0, 1) == 1 ? -1 : 1);

			$font = $CAPTCHA_DIR . self::$default_config['fonts'][rand(0, count(self::$default_config['fonts']) - 1)];

			$font_size     = rand(self::$default_config['min_font_size'], self::$default_config['max_font_size']);
			$text_box_size = imagettfbbox($font_size, $angle, $font, $code);

			$box_width      = abs($text_box_size[6] - $text_box_size[2]);
			$box_height     = abs($text_box_size[5] - $text_box_size[1]);
			$text_pos_x_min = 0;
			$text_pos_x_max = ($bg_width) - ($box_width);
			$text_pos_x     = rand($text_pos_x_min, $text_pos_x_max);
			$text_pos_y_min = $box_height;
			$text_pos_y_max = ($bg_height) - ($box_height / 2);
			$text_pos_y     = rand($text_pos_y_min, $text_pos_y_max);

			if (self::$default_config['shadow']) {
				$shadow_color = self::hex2rgb(self::$default_config['shadow_color']);
				$shadow_color = imagecolorallocate($captcha, $shadow_color['r'], $shadow_color['g'], $shadow_color['b']);
				imagettftext($captcha, $font_size, $angle, $text_pos_x + self::$default_config['shadow_offset_x'], $text_pos_y + self::$default_config['shadow_offset_y'], $shadow_color, $font, $code);
			}

			imagettftext($captcha, $font_size, $angle, $text_pos_x, $text_pos_y, $color, $font, $code);

			ob_start();
			imagepng($captcha);
			$content = ob_get_contents();
			ob_clean();

			imagedestroy($captcha);

			$body = Body::fromString($content);

			return $response->withHeader('Content-type', 'image/png')
							->withBody($body);
		}

		/**
		 * converts hexadecimal color code to rgb
		 *
		 * @param string $hex_str    the hexadecimal code string
		 * @param bool   $get_string get result as string or in array
		 * @param string $separator  the separator to use default is ','
		 *
		 * @return array|bool    array when get_string is false, string otherwise
		 */
		private static function hex2rgb($hex_str, $get_string = false, $separator = ',')
		{
			$hex_str   = preg_replace("/[^0-9A-Fa-f]/", '', $hex_str); // Gets a proper hex string
			$rgb_array = [];
			if (strlen($hex_str) == 6) {
				$color_val      = hexdec($hex_str);
				$rgb_array['r'] = 0xFF & ($color_val >> 0x10);
				$rgb_array['g'] = 0xFF & ($color_val >> 0x8);
				$rgb_array['b'] = 0xFF & $color_val;
			} elseif (strlen($hex_str) == 3) {
				$rgb_array['r'] = hexdec(str_repeat(substr($hex_str, 0, 1), 2));
				$rgb_array['g'] = hexdec(str_repeat(substr($hex_str, 1, 1), 2));
				$rgb_array['b'] = hexdec(str_repeat(substr($hex_str, 2, 1), 2));
			} else {
				return false;
			}

			return $get_string ? implode($separator, $rgb_array) : $rgb_array;
		}
	}