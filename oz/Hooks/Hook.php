<?php
	/**
	 * Copyright (c) 2017-present, Emile Silas Sare
	 *
	 * This file is part of OZone (O'Zone) package.
	 *
	 * For the full copyright and license information, please view the LICENSE
	 * file that was distributed with this source code.
	 */

	namespace OZONE\OZ\Hooks;

	use OZONE\OZ\Hooks\Interfaces\HookInterface;
	use OZONE\OZ\Http\Uri;
	use OZONE\OZ\Router\RouteInfo;

	defined('OZ_SELF_SECURITY_CHECK') or die;

	abstract class Hook implements HookInterface
	{
		/**
		 * @inheritdoc
		 */
		public function onInit(HookContext $hc)
		{
		}

		/**
		 * @inheritdoc
		 */
		public function onRequest(HookContext $hc)
		{
		}

		/**
		 * @inheritdoc
		 */
		public function onFound(RouteInfo $r, HookContext $hc)
		{
		}

		/**
		 * @inheritdoc
		 */
		public function onNotFound(HookContext $hc)
		{
		}

		/**
		 * @inheritdoc
		 */
		public function onMethodNotAllowed(HookContext $hc)
		{
		}

		/**
		 * @inheritdoc
		 */
		public function onResponse(HookContext $hc)
		{
		}

		/**
		 * @inheritdoc
		 */
		public function onFinish(HookContext $hc)
		{
		}

		/**
		 * @inheritdoc
		 */
		public function onRedirect(Uri $target, HookContext $hc)
		{
		}
	}