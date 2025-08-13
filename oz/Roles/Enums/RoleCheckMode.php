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
	 * Use this when checking user role to grant access to a resource.
	 *
	 * > Important: This mode is the default and make sure that no user role is used
	 * in scoped authentication (api key, token etc... with specific access rights).
	 * In Scoped authentication, the user role should never be used to grant access to a resource.
	 * With this mode when the current request is using scoped authentication,
	 * We return false for any user role check.
	 *
	 * @see Roles::hasOneOfRoles()
	 */
	case GRANT_ACCESS;
}
