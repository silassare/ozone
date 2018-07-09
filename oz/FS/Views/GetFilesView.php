<?php
	/**
	 * Copyright (c) Emile Silas Sare <emile.silas@gmail.com>
	 *
	 * This file is part of the OZone package.
	 *
	 * For the full copyright and license information, please view the LICENSE
	 * file that was distributed with this source code.
	 */

	namespace OZONE\OZ\FS\Views;

	use OZONE\OZ\Core\BaseView;
	use OZONE\OZ\Core\URIHelper;
	use OZONE\OZ\Exceptions\NotFoundException;
	use OZONE\OZ\FS\FilesUtils;
	use OZONE\OZ\FS\GetFilesHelper;
	use OZONE\OZ\Utils\StringUtils;

	defined('OZ_SELF_SECURITY_CHECK') or die;

	class GetFilesView extends BaseView
	{
		private $compileData = [];

		/**
		 * {@inheritdoc}
		 */
		public function __construct(array $request = [])
		{
			$this->compileData = $request;
		}

		/**
		 * {@inheritdoc}
		 *
		 * @throws \OZONE\OZ\Exceptions\ForbiddenException
		 * @throws \OZONE\OZ\Exceptions\InternalErrorException
		 * @throws \OZONE\OZ\Exceptions\InvalidFormException
		 * @throws \OZONE\OZ\Exceptions\NotFoundException
		 */
		public function serve()
		{
			$request       = $this->compileData;
			$params_orders = [];

			// remove /oz-static
			$extra        = StringUtils::removePrefix(URIHelper::getUriExtra(), "/oz-static/");
			$file_uri_reg = FilesUtils::genFileURIRegExp($params_orders);
			$extra_ok     = URIHelper::parseUriExtra($file_uri_reg, $params_orders, $request, $extra);

			if (!$extra_ok) {
				oz_logger([$extra, $file_uri_reg, URIHelper::getUriExtra()]);
				throw new NotFoundException();
			}

			GetFilesHelper::serveFile($request);
		}

		/**
		 * {@inheritdoc}
		 */
		public function getCompileData()
		{
			return $this->compileData;
		}

		/**
		 * {@inheritdoc}
		 */
		public function getTemplate()
		{
			return '';
		}
	}