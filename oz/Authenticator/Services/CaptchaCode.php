<?php

/**
 * Copyright (c) 2017-present, Emile Silas Sare
 *
 * This file is part of OZone (O'Zone) package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace OZONE\OZ\Authenticator\Services;

use OZONE\OZ\Authenticator\CaptchaCodeHelper;
use OZONE\OZ\Core\BaseService;
use OZONE\OZ\Core\SettingsManager;
use OZONE\OZ\Router\RouteInfo;
use OZONE\OZ\Router\Router;

\defined('OZ_SELF_SECURITY_CHECK') || die;

/**
 * Class CaptchaCode
 */
final class CaptchaCode extends BaseService
{
	/**
	 * @inheritdoc
	 */
	public static function registerRoutes(Router $router)
	{
		$format = SettingsManager::get('oz.files', 'OZ_CAPTCHA_URI_EXTRA_FORMAT');

		$options = [
			'oz_captcha_key' => '[a-z0-9]{32}',
		];

		$router->get('/captcha/' . $format, function (RouteInfo $r) {
			return CaptchaCodeHelper::generateCaptchaImage($r->getContext(), $r->getArg('oz_captcha_key'));
		}, $options);
	}
}
