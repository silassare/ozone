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
	/**
	 * Prefix applied to all table names (e.g. 'myapp_' results in 'myapp_oz_users').
	 */
	'OZ_DB_TABLE_PREFIX' => '',

	/**
	 * RDBMS type (e.g. 'mysql', 'sqlite', 'postgresql').
	 */
	'OZ_DB_RDBMS'        => 'mysql',

	/**
	 * Database server hostname or IP address.
	 */
	'OZ_DB_HOST'         => 'localhost',

	/**
	 * Database server port.
	 */
	'OZ_DB_PORT'         => 3306,

	/**
	 * Database name.
	 *
	 * For SQLite, this is the file path to the database file (e.g. 'data/database.sqlite').
	 * For MySQL/PostgreSQL, this is the name of the database to connect to.
	 */
	'OZ_DB_NAME'         => '',

	/**
	 * Database username.
	 */
	'OZ_DB_USER'         => '',

	/**
	 * Database password.
	 */
	'OZ_DB_PASS'         => '',

	/**
	 * Database charset. Changing this after install may corrupt your data.
	 *
	 * Specially for MySQL/MariaDB, use 'utf8mb4' to support full Unicode including emojis.
	 */
	'OZ_DB_CHARSET'      => 'utf8mb4',

	/**
	 * Database collation. Changing this after install may corrupt your data.
	 *
	 * For MySQL/MariaDB with 'utf8mb4' charset, use 'utf8mb4_unicode_ci' for proper Unicode sorting and comparison.
	 */
	'OZ_DB_COLLATE'      => 'utf8mb4_unicode_ci',
];
