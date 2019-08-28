<?php
	/**
	 * Copyright (c) 2017-present, Emile Silas Sare
	 *
	 * This file is part of OZone (O'Zone) package.
	 *
	 * For the full copyright and license information, please view the LICENSE
	 * file that was distributed with this source code.
	 */

	namespace OZONE\OZ\Hooks\Interfaces;

	use OZONE\OZ\Hooks\HookContext;
	use OZONE\OZ\Http\Uri;
	use OZONE\OZ\Router\RouteInfo;

	defined('OZ_SELF_SECURITY_CHECK') or die;

	interface HookInterface
	{
		const RUN_FIRST   = 1;
		const RUN_DEFAULT = 2;
		const RUN_LAST    = 3;

		/**
		 * @param \OZONE\OZ\Hooks\HookContext $hc
		 *
		 * @return void
		 */
		public function onInit(HookContext $hc);

		/**
		 * @param \OZONE\OZ\Hooks\HookContext $hc
		 *
		 * @return void
		 */
		public function onRequest(HookContext $hc);

		/**
		 * @param \OZONE\OZ\Router\RouteInfo  $r
		 * @param \OZONE\OZ\Hooks\HookContext $hc
		 *
		 * @return void
		 */
		public function onFound(RouteInfo $r, HookContext $hc);

		/**
		 * Called when no route founds for the requested resource.
		 *
		 * @param \OZONE\OZ\Hooks\HookContext $hc
		 *
		 * @return void
		 */
		public function onNotFound(HookContext $hc);

		/**
		 * Called when the route was founds but the request method is not allowed.
		 *
		 * @param \OZONE\OZ\Hooks\HookContext $hc
		 *
		 * @return void
		 */
		public function onMethodNotAllowed(HookContext $hc);

		/**
		 * @param \OZONE\OZ\Hooks\HookContext $hc
		 *
		 * @return void
		 */
		public function onResponse(HookContext $hc);

		/**
		 * @param \OZONE\OZ\Hooks\HookContext $hc
		 *
		 * @return void
		 */
		public function onFinish(HookContext $hc);

		/**
		 * @param \OZONE\OZ\Http\Uri          $target
		 * @param \OZONE\OZ\Hooks\HookContext $hc
		 *
		 * @return void
		 */
		public function onRedirect(Uri $target, HookContext $hc);

	}