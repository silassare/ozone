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

namespace OZONE\OZ\FS\Services;

use OZONE\OZ\Columns\Types\TypeFile;
use OZONE\OZ\Core\Configs;
use OZONE\OZ\Core\Service;
use OZONE\OZ\Router\RouteInfo;
use OZONE\OZ\Router\Router;

/**
 * Class UploadFiles.
 */
class UploadFiles extends Service
{
	public const MAIN_ROUTE = 'oz:upload';

	/**
	 * @param \OZONE\OZ\Router\RouteInfo $r
	 *
	 * @throws \Gobl\DBAL\Types\Exceptions\TypesException
	 * @throws \Gobl\DBAL\Types\Exceptions\TypesInvalidValueException
	 */
	public function upload(RouteInfo $r): void
	{
		$field = 'files';
		$files = $r->getFormField($field);

		if (!\is_array($files)) {
			$files = [$files];
		}

		$max_file_count = Configs::get('oz.files', 'OZ_UPLOAD_FILE_MAX_COUNT');
		$max_file_size  = Configs::get('oz.files', 'OZ_UPLOAD_FILE_MAX_SIZE');
		$max_total_size = Configs::get('oz.files', 'OZ_UPLOAD_FILE_MAX_TOTAL_SIZE');
		$type           = new TypeFile();

		$type->multiple()
			->fileMinCount(1)
			->fileMinCount($max_file_count)
			->fileMinSize(1)
			->fileMaxSize($max_file_size)
			->fileUploadTotalSize($max_total_size);

		$data_json = $type->validate($files);
		$data      = \json_decode($data_json, true);

		$this->getJSONResponse()
			->setDone()
			->setDataKey($field, $data);
	}

	/**
	 * {@inheritDoc}
	 */
	public static function registerRoutes(Router $router): void
	{
		$router->post('/upload[/]', function (RouteInfo $r) {
			$ctx = $r->getContext();
			$s   = new static($ctx);

			$s->upload($r);

			return $s->respond();
		})->name(self::MAIN_ROUTE);
	}
}
