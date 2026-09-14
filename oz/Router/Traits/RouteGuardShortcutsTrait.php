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

namespace OZONE\Core\Router\Traits;

use OZONE\Core\Roles\Enums\Role;
use OZONE\Core\Roles\Interfaces\RoleInterface;
use OZONE\Core\Router\Guards\AuthenticatedUserRouteGuard;
use OZONE\Core\Router\Guards\AuthorizationProviderRouteGuard;
use OZONE\Core\Router\Guards\UserAccessRightsRouteGuard;
use OZONE\Core\Router\Guards\UserRoleRouteGuard;
use OZONE\Core\Router\RouteSharedOptions;

/**
 * Trait RouteGuardShortcutsTrait.
 *
 * The `with*()` shortcuts of {@see RouteSharedOptions} for the built-in guards. Each adds the
 * guard and a descriptor of it, which the API docs expose (`x-oz-security`).
 *
 * @internal only for {@see RouteSharedOptions}
 */
trait RouteGuardShortcutsTrait
{
	/**
	 * Adds a guard that checks if at least one of the authorization providers authorized this request.
	 *
	 * @return $this
	 */
	public function withAuthorization(string ...$allowed_provider_names): static
	{
		$allowed_provider_names = self::atLeastOne($allowed_provider_names, 'authorization provider');

		$this->guard_descriptors[] = [
			'type'               => 'authorization',
			'allowed_providers'  => $allowed_provider_names,
		];

		return $this->guard(static fn () => new AuthorizationProviderRouteGuard($allowed_provider_names));
	}

	/**
	 * Adds a guard that checks if we have an authenticated user.
	 *
	 * > Allowed user type may be empty.
	 *
	 * @return $this
	 */
	public function withAuthenticatedUser(string ...$allowed_auth_user_types): static
	{
		$this->guard_descriptors[] = [
			'type'          => 'authenticated_user',
			'allowed_types' => $allowed_auth_user_types,
		];

		return $this->guard(static fn () => new AuthenticatedUserRouteGuard($allowed_auth_user_types));
	}

	/**
	 * Adds a guard that checks that the user has the given access rights.
	 *
	 * @return $this
	 */
	public function withAccessRights(string ...$rights): static
	{
		$rights = self::atLeastOne($rights, 'access right');

		$this->guard_descriptors[] = [
			'type'   => 'access_rights',
			'rights' => $rights,
		];

		return $this->guard(static fn () => new UserAccessRightsRouteGuard($rights));
	}

	/**
	 * Adds a guard that checks that the user has the given access rights or roles.
	 *
	 * @param string[] $rights
	 * @param string[] $roles
	 *
	 * @return $this
	 */
	public function withAccessRightsOrRoles(array $rights, array $roles): static
	{
		$rights = self::atLeastOne($rights, 'access right');

		$this->guard_descriptors[] = [
			'type'   => 'access_rights',
			'rights' => $rights,
			'roles'  => $roles,
		];

		return $this->guard(static fn () => new UserAccessRightsRouteGuard($rights, $roles));
	}

	/**
	 * Adds a guard that checks that the user has one of the given roles.
	 *
	 * @return $this
	 */
	public function withRole(RoleInterface ...$roles): static
	{
		$roles = self::atLeastOne($roles, 'role');

		$this->guard_descriptors[] = [
			'type'   => 'role',
			'roles'  => \array_map(static fn ($r) => $r->value, $roles),
			'strict' => true,
		];

		return $this->guard(static fn () => new UserRoleRouteGuard($roles));
	}

	/**
	 * Adds a guard that checks that the user has one of the given roles or is admin.
	 *
	 * @return $this
	 */
	public function withRoleOrAdmin(RoleInterface ...$roles): static
	{
		$roles = self::atLeastOne($roles, 'role');

		$this->guard_descriptors[] = [
			'type'   => 'role',
			'roles'  => \array_map(static fn ($r) => $r->value, $roles),
			'strict' => false,
		];

		return $this->guard(static fn () => new UserRoleRouteGuard($roles, false));
	}

	/**
	 * Adds a guard that checks if the user has admin or super admin role.
	 *
	 * @return $this
	 */
	public function withAdminRole(): static
	{
		$this->guard_descriptors[] = [
			'type'   => 'role',
			'roles'  => [Role::ADMIN->value, Role::SUPER_ADMIN->value],
			'strict' => true,
		];

		return $this->guard(static fn () => new UserRoleRouteGuard([Role::ADMIN, Role::SUPER_ADMIN]));
	}

	/**
	 * Adds a guard that checks if the user has super admin role.
	 *
	 * @return $this
	 */
	public function withSuperAdminRole(): static
	{
		$this->guard_descriptors[] = [
			'type'   => 'role',
			'roles'  => [Role::SUPER_ADMIN->value],
			'strict' => true,
		];

		return $this->guard(static fn () => new UserRoleRouteGuard([Role::SUPER_ADMIN]));
	}
}
