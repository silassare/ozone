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
use OZONE\Core\Exceptions\ForbiddenException;
use OZONE\Core\Router\RouteInfo;

/**
 * Class UserRoleRouteGuard.
 */
class UserRoleRouteGuard extends AbstractRouteGuard
{
	/**
	 * UserRoleRouteGuard constructor.
	 */
	public function __construct(
		private readonly array $roles,
		private readonly bool $strict = true
	) {}

	/**
	 * {@inheritDoc}
	 */
	public function toRules(): array
	{
		return [
			'roles'  => $this->roles,
			'strict' => $this->strict,
		];
	}

	/**
	 * {@inheritDoc}
	 */
	public static function fromRules(array $rules): self
	{
		$roles  = $rules['roles'] ?? [];
		$strict = $rules['strict'] ?? true;

		return new self($roles, $strict);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @throws ForbiddenException
	 */
	public function check(RouteInfo $ri): bool
	{
		$context  = $ri->getContext();
		$user     = $context->auth()->user();

		if (!AuthUsers::hasOneRoleAtLeast($user, $this->roles, $this->strict)) {
			throw new ForbiddenException(null, [
				'_reason'  => 'User role is not in allowed list.',
				'_roles'   => $this->roles,
				'_strict'  => $this->strict,
				'_user'    => AuthUsers::selector($user),
			]);
		}

		return true;
	}
}
