<?php
	/**
	 * Copyright (c) Emile Silas Sare <emile.silas@gmail.com>
	 *
	 * This file is part of the OZone package.
	 *
	 * For the full copyright and license information, please view the LICENSE
	 * file that was distributed with this source code.
	 */

	namespace OZONE\OZ\FS\Services;

	use OZONE\OZ\Core\BaseService;
	use OZONE\OZ\Core\URIHelper;
	use OZONE\OZ\Exceptions\NotFoundException;
	use OZONE\OZ\FS\FilesUtils;
	use OZONE\OZ\FS\GetFilesHelper;

	defined('OZ_SELF_SECURITY_CHECK') or die;

	class GetFiles extends BaseService
	{
		/**
		 * GetFiles constructor.
		 */
		public function __construct()
		{
			parent::__construct();
		}

		/**
		 * {@inheritdoc}
		 *
		 * @param array $request
		 *
		 * @throws \OZONE\OZ\Exceptions\ForbiddenException
		 * @throws \OZONE\OZ\Exceptions\InternalErrorException
		 * @throws \OZONE\OZ\Exceptions\InvalidFormException
		 * @throws \OZONE\OZ\Exceptions\NotFoundException
		 */
		public function execute(array $request = [])
		{
			$params_orders = [];

			$file_uri_reg = FilesUtils::genFileURIRegExp($params_orders);
			$extra_ok     = URIHelper::parseUriExtra($file_uri_reg, $params_orders, $request);

			if (!$extra_ok) {
				throw new NotFoundException();
			}

			GetFilesHelper::serveFile($request);
		}
	}