<?php

/**
 * Copyright (c) 2017-present, Emile Silas Sare
 *
 * This file is part of OZone (O'Zone) package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
	
	\defined('OZ_SELF_SECURITY_CHECK') || die;

	return [
		'OZ_COOKIE_DOMAIN'   => 'self',
		'OZ_COOKIE_PATH'     => 'self',
		'OZ_COOKIE_LIFETIME' => 24 * 60 * 60, // 1 day
		'OZ_COOKIE_SAMESITE' => 'Lax', // None, Lax or Strict
	];
