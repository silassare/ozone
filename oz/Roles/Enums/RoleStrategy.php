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

namespace OZONE\Core\Roles\Enums;

/**
 * Enum RoleStrategy.
 *
 * How a list of roles is compared with a user's: any of them, all of them, or at least the weight of
 * the lightest of them -- "an editor or above", which is what role weights exist for.
 */
enum RoleStrategy: string
{
	case ANY = 'any';

	case ALL = 'all';

	case GTE = 'gte';
}
