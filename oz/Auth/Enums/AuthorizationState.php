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
 * Enum AuthorizationState.
 */
enum AuthorizationState: string
{
	case PENDING = 'PENDING';

	case AUTHORIZED = 'AUTHORIZED';

	case REFUSED = 'REFUSED';
}
