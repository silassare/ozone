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

namespace OZONE\Core\Auth\Enums;

/**
 * Enum AuthMethodType.
 *
 * Authentication method types.
 */
enum AuthenticationMethodType: string
{
	case BASIC = 'Basic';

	case BEARER = 'Bearer';

	case DIGEST = 'Digest'; // RFC 2069 http://tools.ietf.org/html/rfc2069

	case DIGEST_RFC_2617 = 'Digest_RFC_2617'; // RFC 2617 http://tools.ietf.org/html/rfc2617

	case SESSION = 'Session';

	case API_KEY_HEADER = 'ApiKey_Header';
}
