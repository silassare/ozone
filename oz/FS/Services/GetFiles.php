<?php

/**
 * Copyright (c) 2017-present, Emile Silas Sare
 *
 * This file is part of OZone package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace OZONE\OZ\FS\Services;

use OZONE\OZ\Core\BaseService;
use OZONE\OZ\Core\SettingsManager;
use OZONE\OZ\FS\GetFilesHelper;
use OZONE\OZ\Router\Route;
use OZONE\OZ\Router\RouteInfo;
use OZONE\OZ\Router\Router;

class GetFiles extends BaseService
{
	/**
	 * @inheritdoc
	 */
	public static function registerRoutes(Router $router)
	{
		$format = SettingsManager::get('oz.files', 'OZ_GET_FILE_URI_EXTRA_FORMAT');

		$options = [
			Route::OPTION_NAME  => 'oz:files',
			'oz_file_id'        => '[0-9]+',
			'oz_file_key'       => '[a-z0-9]+',
			'oz_file_quality'   => '0|1|2|3',
			'oz_file_extension' => '[a-z0-9]{1,10}',
		];

		$router->get('/files/' . $format, function (RouteInfo $r) {
			return GetFilesHelper::process($r);
		}, $options);
	}
}
