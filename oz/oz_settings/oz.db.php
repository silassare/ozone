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
		// REQUIRED: DATABASE INFO =========================================,
		'OZ_DB_TABLE_PREFIX' => '',
		// we use and support MySQL RDBMS by default,
		'OZ_DB_RDBMS'        => 'mysql',
		'OZ_DB_HOST'         => '',
		'OZ_DB_NAME'         => '',
		'OZ_DB_USER'         => '',
		'OZ_DB_PASS'         => '',
		// you could change the charset,
		// but it is at your own risk,
		'OZ_DB_CHARSET'      => 'utf8',
	];
