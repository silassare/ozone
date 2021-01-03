<?php

/**
 * Copyright (c) 2017-present, Emile Silas Sare
 *
 * This file is part of OZone (O'Zone) package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace OZONE\OZ\FS\Views;

use OZONE\OZ\Core\SettingsManager;
use OZONE\OZ\FS\GetFilesHelper;
use OZONE\OZ\Router\RouteInfo;
use OZONE\OZ\Router\Router;
use OZONE\OZ\Web\WebViewBase;

class GetFilesView extends WebViewBase
{
	/**
	 * @inheritdoc
	 */
	public function getCompileData()
	{
		return [];
	}

	/**
	 * @inheritdoc
	 */
	public function getTemplate()
	{
		return '';
	}

	/**
	 * @inheritdoc
	 */
	public static function registerRoutes(Router $router)
	{
		$format = SettingsManager::get('oz.files', 'OZ_GET_FILE_URI_EXTRA_FORMAT');

		$options = [
			'route:name'        => 'oz:files-static',
			'oz_file_id'        => '[0-9]+',
			'oz_file_key'       => '[a-z0-9]+',
			'oz_file_quality'   => '0|1|2|3',
			'oz_file_extension' => '[a-z0-9]{1,10}',
		];

		$router->get('/oz-static/' . $format, function (RouteInfo $r) {
			return GetFilesHelper::process($r);
		}, $options);
	}
}
