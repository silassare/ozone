<?php

/**
 * Copyright (c) 2017-present, Emile Silas Sare
 *
 * This file is part of OZone package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

return [
	// REQUIRED: DATABASE INFO =========================================,
	'OZ_DB_TABLE_PREFIX' => '',
	// we use and support MySQL RDBMS by default,
	'OZ_DB_RDBMS'        => 'mysql',
	'OZ_DB_HOST'         => '',
	'OZ_DB_NAME'         => '',
	'OZ_DB_USER'         => '',
	'OZ_DB_PASS'         => '',
	// changing charset may lead to data corruption and many more nightmares,
	'OZ_DB_CHARSET'      => 'utf8mb4',
	'OZ_DB_COLLATE'      => 'utf8mb4_unicode_ci',
];
