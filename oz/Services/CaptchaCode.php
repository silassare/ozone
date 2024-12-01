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

namespace OZONE\Core\Services;

use OZONE\Core\App\Context;
use OZONE\Core\App\Service;
use OZONE\Core\App\Settings;
use OZONE\Core\Exceptions\NotFoundException;
use OZONE\Core\Http\Body;
use OZONE\Core\Http\Response;
use OZONE\Core\Http\Uri;
use OZONE\Core\Router\RouteInfo;
use OZONE\Core\Router\Router;
use OZONE\Core\Utils\Hasher;
use OZONE\Core\Utils\Random;
use PHPUtils\Str;

/**
 * Class CaptchaCode.
 */
final class CaptchaCode extends Service
{
	public const CAPTCHA_KEY = 'oz_captcha_key';

	public const CAPTCHA_ROUTE = 'oz:captcha';

	private static array $default_config = [
		'backgrounds'     => [
			'45-degree-fabric.png',
			'cloth-alike.png',
			'grey-sandbag.png',
			'kinda-jean.png',
			'polyester-lite.png',
			'stitched-wool.png',
			'white-carbon.png',
			'white-wave.png',
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
		'shadow_offset_y' => 1,
	];

	/**
	 * Gets captcha image uri.
	 *
	 * @param Context $context
	 * @param string  $code
	 * @param int     $expire_at
	 *
	 * @return Uri the captcha uri
	 */
	public static function buildCaptchaUri(Context $context, string $code, int $expire_at = 0): Uri
	{
		$captcha_key = Hasher::hash32();

		$context->requireAuthStore()
			->set('oz.captcha_cfg.' . $captcha_key, [
				'expire_at' => $expire_at,
				'code'      => $code,
			]);

		return $context->buildRouteUri(self::CAPTCHA_ROUTE, [
			self::CAPTCHA_KEY => $captcha_key,
		]);
	}

	/**
	 * Returns a response with the captcha image.
	 *
	 * @param Context $context
	 * @param string  $captcha_key
	 *
	 * @return Response
	 *
	 * @throws NotFoundException
	 */
	public static function generateCaptchaImage(Context $context, string $captcha_key): Response
	{
		$response = $context->getResponse();
		$key      = 'oz.captcha_cfg.' . $captcha_key;
		$data     = $context->requireAuthStore()
			->get($key);

		if (empty($data)) {
			throw new NotFoundException();
		}

		$expire_at = $data['expire_at'];
		$code      = $data['code'];

		if ($expire_at && $expire_at < \time()) {
			throw new NotFoundException('OZ_CAPTCHA_CODE_EXPIRED');
		}

		$CAPTCHA_DIR = OZ_OZONE_DIR . 'oz_assets' . DS . 'captcha' . DS;

		$rnd        = Random::int(0, \count(self::$default_config['backgrounds']) - 1);
		$background = $CAPTCHA_DIR . self::$default_config['backgrounds'][$rnd];

		$captcha = \imagecreatefrompng($background);

		$bg_width  = \imagesx($captcha);
		$bg_height = \imagesy($captcha);

		$color = Str::hex2rgb(self::$default_config['color']);
		$color = \imagecolorallocate($captcha, $color['r'], $color['g'], $color['b']);

		$angle = Random::int(
			self::$default_config['angle_min'],
			self::$default_config['angle_max']
		) * (1 === Random::int(0, 1) ? -1 : 1);

		$font = $CAPTCHA_DIR .
			self::$default_config['fonts'][Random::int(0, \count(self::$default_config['fonts']) - 1)];

		$font_size     = Random::int(
			self::$default_config['min_font_size'],
			self::$default_config['max_font_size']
		);
		$text_box_size = \imagettfbbox($font_size, $angle, $font, $code);

		$box_width      = \abs($text_box_size[6] - $text_box_size[2]);
		$box_height     = \abs($text_box_size[5] - $text_box_size[1]);
		$text_pos_x_min = 0;
		$text_pos_x_max = $bg_width - $box_width;
		$text_pos_x     = Random::int($text_pos_x_min, $text_pos_x_max);
		$text_pos_y_min = $box_height;
		$text_pos_y_max = $bg_height - ($box_height / 2);
		$text_pos_y     = Random::int($text_pos_y_min, $text_pos_y_max);

		if (self::$default_config['shadow']) {
			$shadow_color = Str::hex2rgb(self::$default_config['shadow_color']);
			$shadow_color = \imagecolorallocate($captcha, $shadow_color['r'], $shadow_color['g'], $shadow_color['b']);
			\imagettftext(
				$captcha,
				$font_size,
				$angle,
				$text_pos_x + self::$default_config['shadow_offset_x'],
				$text_pos_y + self::$default_config['shadow_offset_y'],
				$shadow_color,
				$font,
				$code
			);
		}

		\imagettftext($captcha, $font_size, $angle, $text_pos_x, $text_pos_y, $color, $font, $code);

		\ob_start();
		\imagepng($captcha);
		$content = \ob_get_contents();
		\ob_clean();

		\imagedestroy($captcha);

		$body = Body::fromString($content);

		return $response->withHeader('Content-type', 'image/png')
			->withBody($body);
	}

	/**
	 * {@inheritDoc}
	 */
	public static function registerRoutes(Router $router): void
	{
		$route_path = Settings::get('oz.paths', 'OZ_CAPTCHA_ROUTE_PATH');

		$router
			->get($route_path, static function (RouteInfo $ri) {
				return self::generateCaptchaImage($ri->getContext(), $ri->param(self::CAPTCHA_KEY));
			})
			->name(self::CAPTCHA_ROUTE)
			->param(self::CAPTCHA_KEY, '[a-z0-9]{32}');
	}
}
