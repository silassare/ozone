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

use OZONE\Core\Router\RouteInfo;

/**
 * Class AuthenticationMethodInterface.
 */
interface AuthenticationMethodInterface
{
	/**
	 * This should return a new instance.
	 *
	 * @param RouteInfo $ri
	 * @param string    $realm
	 *
	 * @return self
	 */
	public static function get(RouteInfo $ri, string $realm): self;

	/**
	 * Parse the authentication header and return true if required data are present.
	 *
	 * This is not supposed to check the validity
	 * of the data provided by the client.
	 *
	 * @return bool
	 */
	public function satisfied(): bool;

	/**
	 * Authenticate the client by checking the information provided.
	 *
	 * This should throw an exception if the authentication fails.
	 */
	public function authenticate(): void;

	/**
	 * Should return the authenticated user.
	 *
	 * Will be called after {@link AuthenticationMethodInterface::authenticate()}
	 * and should return an instance of {@link AuthUserInterface}
	 * or throw an exception if no user was authenticated.
	 *
	 * @return AuthUserInterface
	 */
	public function user(): AuthUserInterface;

	/**
	 * Should return the access rights of the authenticated user depending on the authentication method used.
	 *
	 * As a user may generate multiple tokens with different access rights.
	 * This is useful to override the default access rights of the user depending on the token used.
	 *
	 * @return AuthAccessRightsInterface
	 */
	public function getAccessRights(): AuthAccessRightsInterface;

	/**
	 * Check if the authentication method is scoped.
	 *
	 * - We want this to make sure if this authentication method use user full access rights or not.
	 *
	 * Examples:
	 *      - Session based authentication is not scoped, the user has full access rights.
	 *      - But token based authentication may be scoped, the access rights may depend on the token used.
	 *
	 * @return bool
	 */
	public function isScopedAuth(): bool;

	/**
	 * Ask the client for authentication.
	 *
	 * This may send a response to the client or throw an exception.
	 * For session based authentication, this will just start a new session if none is provided.
	 */
	public function ask(): void;
}
