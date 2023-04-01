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

namespace OZONE\OZ\CSRF;

/**
 * Enum CSRFScope.
 */
enum CSRFScope: string
{
	case SESSION = 'SESSION'; // user session

	case HOST = 'HOST'; // host and port

	case USER_IP = 'USER_IP'; // IP and port

	case ACTIVE_USER = 'ACTIVE_USER'; // Active User ID
}
