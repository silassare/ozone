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
use OZONE\Core\Roles\Enums\RoleStrategy;
use OZONE\Core\Roles\Interfaces\RoleInterface;
use OZONE\Core\Router\Guards\AuthenticatedAuthUserRouteGuard;
use OZONE\Core\Router\Guards\AuthorizationProviderRouteGuard;
use OZONE\Core\Router\Guards\AuthUserAccessRightsRouteGuard;
use OZONE\Core\Router\Guards\AuthUserRoleRouteGuard;
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

		return $this->guard(static fn () => new AuthenticatedAuthUserRouteGuard($allowed_auth_user_types));
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

		return $this->guard(static fn () => new AuthUserAccessRightsRouteGuard($rights));
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

		return $this->guard(static fn () => new AuthUserAccessRightsRouteGuard($rights, $roles));
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

		return $this->guard(static fn () => new AuthUserRoleRouteGuard($roles));
	}

	/**
	 * Adds a guard comparing the user's roles with these by a strategy: any of them, all of them,
	 * or at least the weight of the lightest ({@see RoleStrategy}).
	 *
	 * ```php
	 * ->withRoles(RoleStrategy::GTE, [RolesUtils::admin()]);                       // admin or heavier
	 * ->withRoles(RoleStrategy::ALL, [$editor, $reviewer], or_at_least: $super);    // both, unless super
	 * ```
	 *
	 * @param list<RoleInterface> $roles       the roles compared by the strategy, at least one
	 * @param null|RoleInterface  $or_at_least a user whose role weighs at least this passes whatever
	 *                                         the list and the strategy say (`super-admin`: the role
	 *                                         that may do everything); null for a rule about a role
	 *                                         rather than about power
	 *
	 * @return $this
	 */
	public function withRoles(RoleStrategy $strategy, array $roles, ?RoleInterface $or_at_least = null): static
	{
		$roles = self::atLeastOne($roles, 'role');

		$this->guard_descriptors[] = [
			'type'        => 'role',
			'roles'       => \array_map(static fn (RoleInterface $r) => $r->value, $roles),
			'strict'      => null === $or_at_least,
			'strategy'    => $strategy->value,
			'or_at_least' => $or_at_least?->value,
		];

		return $this->guard(static fn () => new AuthUserRoleRouteGuard(
			\array_values($roles),
			true,
			$strategy,
			null === $or_at_least ? null : (string) $or_at_least->value,
		));
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

		return $this->guard(static fn () => new AuthUserRoleRouteGuard($roles, false));
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

		return $this->guard(static fn () => new AuthUserRoleRouteGuard([Role::ADMIN, Role::SUPER_ADMIN]));
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

		return $this->guard(static fn () => new AuthUserRoleRouteGuard([Role::SUPER_ADMIN]));
	}
}
