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

namespace OZONE\Core\Migrations;

/**
 * Class MigrationsState.
 */
enum MigrationsState: int
{
	/**
	 * No migration table found.
	 *
	 * This is a fresh install.
	 */
	case NOT_INSTALLED = 0;

	/**
	 * The migration is installed.
	 *
	 * Same version in the database and in the source code.
	 */
	case INSTALLED = 1;

	/**
	 * There are pending migrations.
	 *
	 * The database version is lower than the source code version.
	 */
	case PENDING = 2;

	/**
	 * There are migrations to rollback.
	 *
	 * The database version is higher than the source code version.
	 * A downgrade is required.
	 */
	case ROLLBACK = 3;
}
