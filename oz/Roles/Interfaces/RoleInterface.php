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
	 *
	 * @return self
	 */
	public static function admin(): self;

	/**
	 * Gets instance for super admin role.
	 *
	 * @return self
	 */
	public static function superAdmin(): self;

	/**
	 * Gets instance for editor role.
	 *
	 * @return self
	 */
	public static function editor(): self;
}
