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

namespace OZONE\Core\Router\Guards;

use OZONE\Core\Auth\AuthUsers;
use OZONE\Core\Exceptions\UnauthenticatedException;
use OZONE\Core\Exceptions\UnauthorizedException;
use OZONE\Core\Router\RouteInfo;

/**
 * Class AuthenticatedUserRouteGuard.
 */
class AuthenticatedUserRouteGuard extends AbstractRouteGuard
{
	/**
	 * AuthenticatedUserRouteGuard constructor.
	 *
	 * > Allowed user type may be empty.
	 */
	public function __construct(
		private readonly array $allowed_user_types,
	) {}

	/**
	 * {@inheritDoc}
	 */
	public function toRules(): array
	{
		return [
			'types'  => $this->allowed_user_types,
		];
	}

	/**
	 * {@inheritDoc}
	 */
	public static function fromRules(array $rules): self
	{
		$types  = $rules['types'] ?? [];

		return new self($types);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @throws UnauthenticatedException|UnauthorizedException
	 */
	public function check(RouteInfo $ri): bool
	{
		$context  = $ri->getContext();

		authUsers($context)->assertUserIsAuthenticated();

		$u = user();

		if (!empty($this->allowed_user_types)) {
			if (!\in_array($u->getAuthUserType(), $this->allowed_user_types, true)) {
				throw new UnauthorizedException(null, [
					'_reason'     => 'User type not allowed',
					'_user'       => AuthUsers::selector($u),
					'_allowed'    => $this->allowed_user_types,
				]);
			}
		}

		return true;
	}
}
