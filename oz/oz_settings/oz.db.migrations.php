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

use OZONE\Core\Migrations\Migrations;

return [
	/**
	 * This is the desired migration version for which the code is written.
	 *
	 * The migrator will check for the database version and compare it with this value
	 * to decide what to do with the database.
	 */
	'OZ_MIGRATION_VERSION' => Migrations::DB_NOT_INSTALLED_VERSION,
];
