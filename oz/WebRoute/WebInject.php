<?php
	/**
	 * Copyright (c) Emile Silas Sare <emile.silas@gmail.com>
	 *
	 * This file is part of the OZone package.
	 *
	 * For the full copyright and license information, please view the LICENSE
	 * file that was distributed with this source code.
	 */

	namespace OZONE\OZ\WebRoute;

	use OZONE\OZ\Core\URIHelper;
	use OZONE\OZ\Lang\Polyglot;

	defined('OZ_SELF_SECURITY_CHECK') or die;

	class WebInject
	{
		public function __construct() { }

		public function getRequestURL($append_query = true)
		{
			return URIHelper::getRequestURL($append_query);
		}

		public function getBaseURL()
		{
			return URIHelper::getBaseURL();
		}

		public function getLanguage()
		{
			return Polyglot::getLanguage();
		}

		public function getRequestViewId()
		{
			return WebRoute::findRoute(URIHelper::getUriExtra());
		}
	}