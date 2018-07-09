<?php
	/**
	 * Copyright (c) Emile Silas Sare <emile.silas@gmail.com>
	 *
	 * This file is part of the OZone package.
	 *
	 * For the full copyright and license information, please view the LICENSE
	 * file that was distributed with this source code.
	 */

	namespace OZONE\OZ\Authenticator;

	use OZONE\OZ\Core\Hasher;
	use OZONE\OZ\Core\SessionsData;
	use OZONE\OZ\Exceptions\NotFoundException;
	use OZONE\OZ\Core\SettingsManager;
	use OZONE\OZ\Utils\StringUtils;

	defined('OZ_SELF_SECURITY_CHECK') or die;

	/**
	 * Class CaptchaCodeHelper
	 *
	 * @package OZONE\OZ\Authenticator
	 */
	final class CaptchaCodeHelper implements AuthenticatorHelper
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

		private $auth = null;

		/**
		 * CaptchaCodeHelper constructor.
		 *
		 * @param \OZONE\OZ\Authenticator\Authenticator $auth
		 */
		public function __construct(Authenticator $auth)
		{
			$this->auth = $auth;
		}

		/**
		 * Gets captcha image uri for authentication
		 *
		 * @return array the captcha info
		 * @throws \OZONE\OZ\Exceptions\InternalErrorException
		 * @throws \Exception
		 */
		public function getCaptcha()
		{
			$auth = $this->auth;

			$generated = $auth->generate()
							  ->getGenerated();

			$captcha_key = md5($auth->getLabel() . $auth->getForValue() . microtime());

			$f_name  = SettingsManager::get('oz.files', 'OZ_CAPTCHA_FILE_NAME');
			$img_src = str_replace(['{oz_captcha_key}'], [$captcha_key], $f_name);

			SessionsData::set('_captcha_cfg_:' . $captcha_key, $generated['authCode']);

			return ['captchaSrc' => $img_src];
		}

		/**
		 * draw the captcha image
		 *
		 * @param string $captcha_key the captcha image key
		 *
		 * @throws \OZONE\OZ\Exceptions\NotFoundException when captcha image key is not valid
		 * @throws \Exception
		 */
		public static function serveCaptchaImage($captcha_key)
		{
			$code = null;

			if (is_string($captcha_key)) {
				$code = SessionsData::get('_captcha_cfg_:' . $captcha_key);
			}

			if (empty($code)) {
				throw new NotFoundException();
			}

			SessionsData::remove('_captcha_cfg_:' . $captcha_key);

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

			header('Content-type: image/png');
			flush();
			imagepng($captcha);
		}

		/**
		 * convert hexadecimal color code to rgb
		 *
		 * @param string $hex_str    the hexadecimal code string
		 * @param bool   $get_string get result as string or in array
		 * @param string $separator  the separator to use default is ','
		 *
		 * @return array|bool    array when get_string is false, string otherwise
		 */
		private static function hex2rgb($hex_str, $get_string = false, $separator = ',')
		{
			$hex_str   = preg_replace("/[^0-9A-Fa-f]/", '', $hex_str); // Get  a proper hex string
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

		/**
		 * Generate regexp used to match Captcha file URI.
		 *
		 * @param array &$fields
		 *
		 * @return string
		 * @throws \OZONE\OZ\Exceptions\InternalErrorException
		 */
		public static function genCaptchaURIRegExp(array &$fields)
		{
			$format = SettingsManager::get("oz.files", "OZ_CAPTCHA_URI_EXTRA_FORMAT");

			$parts = [
				"oz_captcha_key" => "([a-z0-9]{32})"
			];

			return StringUtils::stringFormatToRegExp($format, $parts, $fields);
		}

	}