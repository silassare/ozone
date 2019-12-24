<?php
	/**
	 * Copyright (c) 2017-present, Emile Silas Sare
	 *
	 * This file is part of OZone (O'Zone) package.
	 *
	 * For the full copyright and license information, please view the LICENSE
	 * file that was distributed with this source code.
	 */

	namespace OZONE\OZ\FS\Services;

	use OZONE\OZ\Core\BaseService;
	use OZONE\OZ\Core\SettingsManager;
	use OZONE\OZ\Db\Columns\Types\TypeFile;
	use OZONE\OZ\Router\RouteInfo;
	use OZONE\OZ\Router\Router;

	defined('OZ_SELF_SECURITY_CHECK') or die;

	class UploadFiles extends BaseService
	{
		/**
		 * @param \OZONE\OZ\Router\RouteInfo $r
		 *
		 * @throws \Gobl\DBAL\Types\Exceptions\TypesException
		 * @throws \Gobl\DBAL\Types\Exceptions\TypesInvalidValueException
		 */
		public function upload(RouteInfo $r)
		{
			$field = "files";
			$files = $r->getFormField($field);

			if (!is_array($files)) {
				$files = [$files];
			}

			$max_file_count = SettingsManager::get("oz.files", "OZ_UPLOAD_FILE_MAX_COUNT");
			$max_file_size  = SettingsManager::get("oz.files", "OZ_UPLOAD_FILE_MAX_SIZE");
			$max_total_size = SettingsManager::get("oz.files", "OZ_UPLOAD_FILE_MAX_TOTAL_SIZE");
			$type           = new TypeFile();

			$type->multiple()
				 ->fileCountRange(1, $max_file_count)
				 ->fileSizeRange(1, $max_file_size)
				 ->fileUploadTotalSize($max_total_size);

			$data_json = $type->validate($files, "", "");
			$data      = json_decode($data_json, true);

			$this->getResponseHolder()
				 ->setDone()
				 ->setDataKey($field, $data);
		}

		/**
		 * @inheritdoc
		 */
		public static function registerRoutes(Router $router)
		{
			$options = [
				'route:name' => 'oz:upload',
			];

			$router->post('/upload[/]', function (RouteInfo $r) {
				$ctx = $r->getContext();
				$s   = new static($ctx);

				$s->upload($r);

				return $s->respond();
			}, $options);
		}
	}