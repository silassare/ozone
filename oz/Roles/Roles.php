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

namespace OZONE\Core\Roles;

use Gobl\CRUD\Exceptions\CRUDException;
use Gobl\Exceptions\GoblException;
use Gobl\ORM\Exceptions\ORMException;
use OZONE\Core\Auth\Interfaces\AuthUserInterface;
use OZONE\Core\Db\OZRole;
use OZONE\Core\Roles\Enums\RoleCheckMode;
use OZONE\Core\Roles\Interfaces\RoleInterface;

/**
 * Class Roles.
 */
class Roles
{
	/**
	 * Checks if the given id belongs to an user with super-admin role.
	 *
	 * @param AuthUserInterface $user The user
	 *
	 * @return bool
	 */
	public static function isSuperAdmin(AuthUserInterface $user): bool
	{
		return self::hasRole($user, RolesUtils::superAdmin());
	}

	/**
	 * Checks if the given id belongs to an user with admin or super-admin role.
	 *
	 * @param AuthUserInterface $user       The user
	 * @param bool              $strict     In strict mode user should be an admin,
	 *                                      in non-strict mode user may have a role with
	 *                                      a higher or equal weight
	 * @param RoleCheckMode     $check_mode The check mode
	 *
	 * @return bool
	 */
	public static function isAdmin(
		AuthUserInterface $user,
		bool $strict = true,
		RoleCheckMode $check_mode = RoleCheckMode::GRANT_ACCESS
	): bool {
		return self::hasRole($user, RolesUtils::admin(), $strict, $check_mode);
	}

	/**
	 * Checks if the given id belongs to an user with editor role.
	 *
	 * @param AuthUserInterface $user       The user
	 * @param bool              $strict     In strict mode user should be an editor,
	 *                                      in non-strict mode user may have a role with
	 *                                      a higher or equal weight
	 * @param RoleCheckMode     $check_mode The check mode
	 *
	 * @return bool
	 */
	public static function isEditor(
		AuthUserInterface $user,
		bool $strict = true,
		RoleCheckMode $check_mode = RoleCheckMode::GRANT_ACCESS
	): bool {
		return self::hasRole($user, RolesUtils::editor(), $strict, $check_mode);
	}

	/**
	 * Checks if the user with the given id has a given role.
	 *
	 * @param AuthUserInterface $user       The user
	 * @param RoleInterface     $role       The role
	 * @param bool              $strict     In strict mode user should have the exact role,
	 *                                      in non-strict mode user may have a role with
	 *                                      a higher or equal weight
	 * @param RoleCheckMode     $check_mode The check mode
	 *
	 * @return bool
	 */
	public static function hasRole(
		AuthUserInterface $user,
		RoleInterface $role,
		bool $strict = true,
		RoleCheckMode $check_mode = RoleCheckMode::GRANT_ACCESS
	): bool {
		return self::hasOneOfRoles($user, [$role], $strict ? null : $role, $check_mode);
	}

	/**
	 * Checks if the user with the given id has at least one role in a given roles list.
	 *
	 * @param AuthUserInterface           $user         The user
	 * @param array<RoleInterface|string> $one_of_roles The roles list
	 * @param null|RoleInterface          $at_least     If not null, and the user has no role in the allowed list,
	 *                                                  it will check if the user has a role with a higher
	 *                                                  or equal weight
	 * @param RoleCheckMode               $check_mode   The check mode
	 *
	 * @return bool
	 */
	public static function hasOneOfRoles(
		AuthUserInterface $user,
		array $one_of_roles,
		?RoleInterface $at_least = null,
		RoleCheckMode $check_mode = RoleCheckMode::GRANT_ACCESS
	): bool {
		$can_use_user_role = match ($check_mode) {
			// we do not allow user role usage in scoped auth
			// this is to make sure when in scoped auth (api key with specific access rights),
			// the user role is not used to grant access in this API call
			RoleCheckMode::GRANT_ACCESS => !auth()->isScoped(),
			RoleCheckMode::READ         => true,
		};

		if (!$can_use_user_role) {
			return false;
		}

		$user_roles   = RolesUtils::roles($user);
		$one_of_roles = \array_fill_keys(RolesUtils::ensureRolesString($one_of_roles), 1);

		foreach ($user_roles as $entry) {
			$r = $entry->getRole();
			if (isset($one_of_roles[$r->value])) {
				return true;
			}

			if ($at_least && RolesUtils::gte($r, $at_least)) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Assigns a role to a given user.
	 *
	 * @param AuthUserInterface    $user    the user
	 * @param RoleInterface|string $role    the role
	 * @param bool                 $restore if true and the role is invalid, it will be restored
	 *
	 * @return OZRole
	 *
	 * @throws CRUDException
	 * @throws ORMException
	 * @throws GoblException
	 */
	public static function assign(AuthUserInterface $user, RoleInterface|string $role, bool $restore = false): OZRole
	{
		$role  = RolesUtils::normalize($role)->value;
		$entry = RolesUtils::role($user, $role, false);

		if ($entry) {
			if ($restore && !$entry->isValid()) {
				$entry->setIsValid(true)
					->save();
			}
		} else {
			$entry = new OZRole();

			$entry->setOwnerID($user->getAuthIdentifier())
				->setOwnerType($user->getAuthUserType())
				->setRole($role)
				->setIsValid(true)
				->save();
		}

		return $entry;
	}

	/**
	 * Revokes a role from a given user.
	 *
	 * @param AuthUserInterface    $user the user
	 * @param RoleInterface|string $role the role
	 *
	 * @return bool
	 *
	 * @throws CRUDException
	 * @throws GoblException
	 * @throws ORMException
	 */
	public static function revoke(AuthUserInterface $user, RoleInterface|string $role): bool
	{
		$role  = RolesUtils::normalize($role)->value;
		if ($entry = RolesUtils::role($user, $role, false)) {
			$entry->setIsValid(false)
				->save();
		}

		return true;
	}
}
