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

// Use in-memory SQLite for the unit test suite - no external DB server required.
return [
	'OZ_DB_RDBMS' => 'sqlite',
	'OZ_DB_HOST'  => ':memory:',
	'OZ_DB_NAME'  => '',
	'OZ_DB_USER'  => '',
	'OZ_DB_PASS'  => '',
];
