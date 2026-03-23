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

namespace OZONE\Core\Auth\Interfaces;

/**
 * Interface AuthUsersRepositoryInterface.
 */
interface AuthUsersRepositoryInterface
{
	/**
	 * Get the auth users repository instance.
	 *
	 * @param string $user_type the user type as defined in the configuration
	 */
	public static function get(string $user_type): self;

	/**
	 * Get the auth user by auth user default identifier (The auth user id).
	 */
	public function getAuthUserByIdentifier(string $identifier): ?AuthUserInterface;

	/**
	 * Get the auth user by login identifier type and value.
	 */
	public function getAuthUserByIdentifierType(string $identifier_type, string $identifier_value): ?AuthUserInterface;
}
