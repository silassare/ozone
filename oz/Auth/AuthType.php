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

namespace OZONE\Core\Auth;

/**
 * Enum AuthType.
 */
enum AuthType: string
{
	// Two Factor Authentication
	case TWO_FA = '2FA';

	// Email or phone verification
	case VERIFICATION = 'VERIFICATION';

	// User Access Token
	case ACCESS_TOKEN = 'ACCESS_TOKEN';
}
