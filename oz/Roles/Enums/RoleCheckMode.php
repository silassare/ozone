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
 * Enum RoleCheckMode.
 */
enum RoleCheckMode
{
	/**
	 * Just reading user roles.
	 */
	case READ;

	/**
	 * When checking user role to grant access to a resource.
	 *
	 * This mode is the default and make no user role is used in scoped authentication.
	 * In Scoped authentication, the user role should never be used to grant access to a resource.
	 */
	case GRANT_ACCESS;
}
