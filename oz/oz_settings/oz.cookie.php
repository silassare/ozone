<?php
	/**
	 * Copyright (c) Emile Silas Sare <emile.silas@gmail.com>
	 *
	 * This file is part of OZone (O'Zone) package.
	 *
	 * For the full copyright and license information, please view the LICENSE
	 * file that was distributed with this source code.
	 */

	defined('OZ_SELF_SECURITY_CHECK') or die;

	return [
		// if cookie domain equal to 'self' ozone will use SERVER_NAME
		"OZ_COOKIE_DOMAIN"   => "self",
		"OZ_COOKIE_LIFETIME" => 24 * 60 * 60,// 1 day
	];