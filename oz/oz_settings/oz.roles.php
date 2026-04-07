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

use OZONE\Core\Roles\Enums\Role;
use OZONE\Core\Roles\Interfaces\RoleInterface;

return [
	/**
	 * FQCN of the roles enum class, must implement {@see RoleInterface}.
	 */
	'OZ_ROLE_ENUM_CLASS' => Role::class,
];
