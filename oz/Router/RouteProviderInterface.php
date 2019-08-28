<?php
	/**
	 * Copyright (c) 2017-present, Emile Silas Sare
	 *
	 * This file is part of OZone (O'Zone) package.
	 *
	 * For the full copyright and license information, please view the LICENSE
	 * file that was distributed with this source code.
	 */

	namespace OZONE\OZ\Router;

	defined('OZ_SELF_SECURITY_CHECK') or die;

	interface RouteProviderInterface
	{
		/**
		 * Called to register the service routes.
		 *
		 * @param \OZONE\OZ\Router\Router      $router
		 *
		 * @return void
		 */
		public static function registerRoutes(Router $router);
	}