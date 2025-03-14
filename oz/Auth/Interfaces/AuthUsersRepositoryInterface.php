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
	 */
	public static function get(): self;

	/**
	 * Get the auth user by auth user identifier.
	 */
	public function getAuthUserByIdentifier(string $identifier): ?AuthUserInterface;

	/**
	 * Get the auth user by login identifier name and value.
	 */
	public function getAuthUserByNamedIdentifier(string $identifier_name, string $identifier_value): ?AuthUserInterface;
}
