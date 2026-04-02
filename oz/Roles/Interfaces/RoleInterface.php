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

namespace OZONE\Core\Roles\Interfaces;

use BackedEnum;

/**
 * Interface RoleInterface.
 */
interface RoleInterface extends BackedEnum
{
	/**
	 * Gets the role weight.
	 *
	 * The weight is used to determine the privilege level of the role.
	 *
	 * @return int
	 */
	public function weight(): int;

	/**
	 * Gets instance for admin role.
	 */
	public static function admin(): static;

	/**
	 * Gets instance for super admin role.
	 */
	public static function superAdmin(): static;

	/**
	 * Gets instance for editor role.
	 */
	public static function editor(): static;
}
