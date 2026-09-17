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

// SQLite in a sandbox project (Sandbox::settingsSources()): no DB server required, and the schema
// that sandbox_build.php applies is there for every test, whatever the order.
return [
	'OZ_DB_RDBMS' => 'sqlite',
	'OZ_DB_HOST'  => app()->getProjectDir()->resolve('db.sqlite'),
	'OZ_DB_NAME'  => '',
	'OZ_DB_USER'  => '',
	'OZ_DB_PASS'  => '',
];
