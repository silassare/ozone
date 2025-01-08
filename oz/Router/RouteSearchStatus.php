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

namespace OZONE\Core\Router;

/**
 * Enum RouteSearchStatus.
 */
enum RouteSearchStatus: int
{
	case NOT_FOUND = 0;

	case FOUND = 1;

	case METHOD_NOT_ALLOWED = 2;
}
