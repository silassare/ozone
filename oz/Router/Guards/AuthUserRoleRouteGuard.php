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

use Override;
use OZONE\Core\Auth\AuthUsers;
use OZONE\Core\Exceptions\ForbiddenException;
use OZONE\Core\Roles\Enums\Role;
use OZONE\Core\Roles\Enums\RoleStrategy;
use OZONE\Core\Roles\Interfaces\RoleInterface;
use OZONE\Core\Roles\Roles;
use OZONE\Core\Roles\RolesUtils;
use OZONE\Core\Router\RouteInfo;

/**
 * Class AuthUserRoleRouteGuard.
 *
 * The user's roles compared with a list by a strategy ({@see RoleStrategy}): any of them, all of
 * them, or at least the weight of the lightest. A user whose role weighs at least `$at_least`
 * passes whatever the list; without one, `$strict` false lets an administrator or above pass, as
 * it always has.
 */
final class AuthUserRoleRouteGuard extends AbstractRouteGuard
{
	/**
	 * AuthUserRoleRouteGuard constructor.
	 *
	 * @param list<RoleInterface|string> $roles
	 * @param null|string                $at_least the role a user weighing at least passes regardless
	 */
	public function __construct(
		private readonly array $roles,
		private readonly bool $strict = true,
		private readonly RoleStrategy $strategy = RoleStrategy::ANY,
		private readonly ?string $at_least = null,
	) {}

	#[Override]
	public function toRules(): array
	{
		return [
			'roles'    => $this->roles,
			'strict'   => $this->strict,
			'strategy' => $this->strategy->value,
			'at_least' => $this->at_least,
		];
	}

	#[Override]
	public static function fromRules(array $rules): static
	{
		$roles    = $rules['roles'] ?? [];
		$strict   = $rules['strict'] ?? true;
		$strategy = RoleStrategy::tryFrom((string) ($rules['strategy'] ?? '')) ?? RoleStrategy::ANY;
		$at_least = $rules['at_least'] ?? null;

		return new self($roles, (bool) $strict, $strategy, \is_string($at_least) ? $at_least : null);
	}

	/**
	 * @throws ForbiddenException
	 */
	#[Override]
	public function check(RouteInfo $ri): bool
	{
		$context  = $ri->getContext();
		$user     = $context->auth()->user();

		$at_least = null !== $this->at_least
			? RolesUtils::normalize($this->at_least)
			: ($this->strict ? null : Role::ADMIN);

		if (!Roles::satisfies($user, $this->roles, $this->strategy, $at_least)) {
			throw new ForbiddenException('OZ_ERROR_USER_IS_MISSING_REQUIRED_ROLE', [
				'_reason'   => 'User role is not in allowed list.',
				'_roles'    => $this->roles,
				'_strict'   => $this->strict,
				'_strategy' => $this->strategy->value,
				'_user'     => AuthUsers::selector($user),
			]);
		}

		return true;
	}
}
