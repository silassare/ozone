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

namespace OZONE\Core\Migrations\Enums;

/**
 * Class MigrationsRunMode.
 */
enum MigrationsRunMode: int
{
	/**
	 * The migration up queries mode.
	 */
	case UP = 1;

	/**
	 * The migration down queries mode.
	 */
	case DOWN = 2;

	/**
	 * The migration full queries mode.
	 */
	case FULL = 3;
}
