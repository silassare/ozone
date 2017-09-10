<?php
	/**
	 * Copyright (c) Emile Silas Sare <emile.silas@gmail.com>
	 *
	 * This file is part of the OZone package.
	 *
	 * For the full copyright and license information, please view the LICENSE
	 * file that was distributed with this source code.
	 */

	defined('OZ_SELF_SECURITY_CHECK') or die;

	define('OZ_OZONE_VERSION', '2.0.0');
	define('OZ_OZONE_VERSION_NAME', 'OZone v' . OZ_OZONE_VERSION);
	define('OZ_OZONE_IS_CLI', 'cli' === php_sapi_name());