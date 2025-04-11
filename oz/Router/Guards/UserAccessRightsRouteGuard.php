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
use OZONE\Core\Roles\Roles;
use OZONE\Core\Router\RouteInfo;

/**
 * Class UserAccessRightsRouteGuard.
 */
class UserAccessRightsRouteGuard extends AbstractRouteGuard
{
	/**
	 * UserAccessRightsRouteGuard constructor.
	 *
	 * @param string[] $access_rights
	 * @param string[] $roles
	 */
	public function __construct(
		protected array $access_rights,
		protected array $roles = []
	) {}

	/**
	 * {@inheritDoc}
	 */
	public function toRules(): array
	{
		return [
			'access_rights' => $this->access_rights,
			'roles'         => $this->roles,
		];
	}

	/**
	 * {@inheritDoc}
	 */
	public static function fromRules(array $rules): self
	{
		$rights = $rules['access_rights'] ?? [];
		$roles  = $rules['roles'] ?? [];

		return new self($rights, $roles);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @throws ForbiddenException
	 */
	public function check(RouteInfo $ri): bool
	{
		$auth   = $ri->getContext()->auth();
		$rights = $auth->getAccessRights();

		if ($rights->can(...$this->access_rights)) {
			return true;
		}

		$user = $auth->user();

		if (!empty($this->roles) && Roles::hasOneOfRoles($user, $this->roles)) {
			return true;
		}

		throw new ForbiddenException(null, [
			'_reason'         => 'Missing required access rights.',
			'_access_rights'  => $this->access_rights,
			'_roles'          => $this->roles,
			'_user'           => AuthUsers::selector($user),
		]);
	}
}
