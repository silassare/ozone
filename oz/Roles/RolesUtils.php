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

use OZONE\Core\App\Settings;
use OZONE\Core\Auth\AuthUsers;
use OZONE\Core\Auth\Interfaces\AuthUserInterface;
use OZONE\Core\Cache\CacheManager;
use OZONE\Core\Db\OZRole;
use OZONE\Core\Db\OZRolesQuery;
use OZONE\Core\Exceptions\RuntimeException;
use OZONE\Core\Roles\Interfaces\RoleInterface;
use Throwable;
use TypeError;
use ValueError;

/**
 * Class RolesUtils.
 */
class RolesUtils
{
	/**
	 * Checks if two roles are identical.
	 *
	 * @param RoleInterface|string $a
	 * @param RoleInterface|string $b
	 *
	 * @return bool
	 */
	public static function is(RoleInterface|string $a, RoleInterface|string $b): bool
	{
		return self::normalize($a) === self::normalize($b);
	}

	/**
	 * Checks if the first role is strictly greater than the second role.
	 */
	public static function gt(RoleInterface|string $a, RoleInterface|string $b): bool
	{
		return self::normalize($a)->weight() > self::normalize($b)->weight();
	}

	/**
	 * Checks if the first role is greater than or equal to the second role.
	 */
	public static function gte(RoleInterface|string $a, RoleInterface|string $b): bool
	{
		return self::normalize($a)->weight() >= self::normalize($b)->weight();
	}

	/**
	 * Normalizes a role to an instance of configured role enum class of type {@see RoleInterface}.
	 *
	 * @param RoleInterface|string $role
	 *
	 * @return RoleInterface
	 */
	public static function normalize(RoleInterface|string $role): RoleInterface
	{
		if ($role instanceof RoleInterface) {
			$role = $role->value;
		}

		$role_enum_class = self::getRoleEnumClass();

		try {
			return $role_enum_class::from($role);
		} catch (TypeError|ValueError $e) {
			throw (
				new RuntimeException(\sprintf(
					'Role "%s" is not a valid enum value for "%s"',
					$role,
					$role_enum_class
				), null, $e)
			)->suspectConfig('oz.roles', 'OZ_ROLE_ENUM_CLASS');
		}
	}

	/**
	 * Gets role enum class.
	 *
	 * @return class-string<RoleInterface>
	 */
	public static function getRoleEnumClass(): string
	{
		/** @var class-string<RoleInterface> $result */
		static $result = null;

		if (null === $result) {
			$class = Settings::get('oz.roles', 'OZ_ROLE_ENUM_CLASS');

			if (!\class_exists($class)) {
				(throw new RuntimeException(\sprintf(
					'Class "%s" does not exist',
					$class
				)))->suspectConfig('oz.roles', 'OZ_ROLE_ENUM_CLASS');
			}

			if (!\is_subclass_of($class, RoleInterface::class)) {
				(throw new RuntimeException(\sprintf(
					'Class "%s" is not a subclass of "%s"',
					$class,
					RoleInterface::class
				)))->suspectConfig('oz.roles', 'OZ_ROLE_ENUM_CLASS');
			}

			$result = $class;
		}

		return $result;
	}

	/**
	 * Returns the roles list as strings.
	 *
	 * @param array<RoleInterface|string> $roles The roles list
	 *
	 * @return array<string> The roles list as strings
	 */
	public static function ensureRolesString(array $roles): array
	{
		/** @var array<string,1> $out */
		$out = [];

		foreach ($roles as $role) {
			$role       = (string) self::normalize($role)->value;
			$out[$role] = 1;
		}

		return \array_keys($out);
	}

	/**
	 * Returns the roles list as {@see RoleInterface}.
	 *
	 * @param array<RoleInterface|string> $roles The roles list
	 *
	 * @return array<RoleInterface> The roles list as RoleInterface
	 */
	public static function ensureRolesEnum(array $roles): array
	{
		$out = [];

		foreach ($roles as $role) {
			$role = self::normalize($role);

			$out[(string) $role->value] = $role;
		}

		return \array_values($out);
	}

	/**
	 * Gets instance for admin role.
	 *
	 * Alias of {@see RoleInterface::editor()}
	 *
	 * @return RoleInterface
	 */
	public static function admin(): RoleInterface
	{
		return self::getRoleEnumClass()::admin();
	}

	/**
	 * Gets instance for super admin role.
	 *
	 * Alias of {@see RoleInterface::editor()}
	 *
	 * @return RoleInterface
	 */
	public static function superAdmin(): RoleInterface
	{
		return self::getRoleEnumClass()::superAdmin();
	}

	/**
	 * Gets instance for editor role.
	 *
	 * Alias of {@see RoleInterface::editor()}
	 *
	 * @return RoleInterface
	 */
	public static function editor(): RoleInterface
	{
		return self::getRoleEnumClass()::editor();
	}

	/**
	 * Gets role entry for a given user id and role.
	 *
	 * @param AuthUserInterface $user       the user
	 * @param string            $role       the role
	 * @param bool              $valid_only if true, only valid role will be returned
	 *
	 * @return null|OZRole
	 */
	public static function role(AuthUserInterface $user, string $role, bool $valid_only): ?OZRole
	{
		$qb = new OZRolesQuery();

		$qb->whereOwnerIdIs($user->getAuthIdentifier())
			->whereOwnerTypeIs($user->getAuthUserType())
			->whereRoleIs($role);

		if ($valid_only) {
			$qb->whereIsValid();
		}

		return $qb->find(1)
			->fetchClass();
	}

	/**
	 * Gets a given user roles.
	 *
	 * @param AuthUserInterface $user  the user
	 * @param bool              $fresh disable cache
	 *
	 * @return OZRole[]
	 */
	public static function roles(AuthUserInterface $user, bool $fresh = false): array
	{
		$cache     = CacheManager::runtime(__METHOD__);
		$cache_key = AuthUsers::ref($user);

		$fresh && $cache->clear();

		$factory = static function () use ($user) {
			try {
				$qb = new OZRolesQuery();

				return $qb->whereOwnerIdIs($user->getAuthIdentifier())
					->whereOwnerTypeIs($user->getAuthUserType())
					->whereIsValid()
					->find()
					->fetchAllClass();
			} catch (Throwable $t) {
				throw new RuntimeException('Unable to load user roles.', [
					'_user' => AuthUsers::selector($user),
				], $t);
			}
		};

		return $cache
			->factory($cache_key, $factory)
			->get();
	}
}
