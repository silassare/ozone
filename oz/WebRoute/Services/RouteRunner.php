<?php
	/**
	 * Copyright (c) Emile Silas Sare <emile.silas@gmail.com>
	 *
	 * This file is part of OZone (O'Zone) package.
	 *
	 * For the full copyright and license information, please view the LICENSE
	 * file that was distributed with this source code.
	 */

	namespace OZONE\OZ\WebRoute\Services;

	use OZONE\OZ\WebRoute\WebRoute;
	use OZONE\OZ\Core\URIHelper;
	use OZONE\OZ\Core\BaseService;

	defined('OZ_SELF_SECURITY_CHECK') or die;

	final class RouteRunner extends BaseService
	{
		public function __construct()
		{
			parent::__construct();
		}

		/**
		 * {@inheritdoc}
		 *
		 * @throws \Exception
		 */
		public function execute(array $request = [])
		{
			WebRoute::runRoutePath(URIHelper::getUriExtra(), $request);
			exit;
		}
	}