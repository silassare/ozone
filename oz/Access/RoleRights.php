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

namespace OZONE\Core\Access;

use Gobl\ORM\ORMOptions;
use OZONE\Core\Auth\Interfaces\AuthUserInterface;
use OZONE\Core\Db\OZRoleRight;
use OZONE\Core\Db\OZRoleRightsQuery;
use OZONE\Core\Exceptions\RuntimeException;
use OZONE\Core\Roles\Interfaces\RoleInterface;
use OZONE\Core\Roles\Roles;
use OZONE\Core\Roles\RolesUtils;
use OZONE\Core\Stores\CacheRegistry;
use Throwable;

/**
 * Class RoleRights.
 *
 * The access rights each role is given or refused (`oz_role_rights`), edited live, and what they make
 * of an auth user's rights: their roles' joined, then their own over them ({@see AccessRights::of()}).
 * A role holds what it was given and nothing of the roles lighter than it.
 */
final class RoleRights
{
	/**
	 * Every role's map, `role => [action => allowed]`: read once per request.
	 *
	 * @return array<string, array<string, bool>>
	 */
	public static function all(): array
	{
		return CacheRegistry::runtime(__CLASS__)->remember('all', static function (): array {
			try {
				$rows = new OZRoleRightsQuery()->find()->fetchAllClass();
			} catch (Throwable $t) {
				throw new RuntimeException('Unable to load the rights of the roles.', null, $t);
			}

			$map = [];

			foreach ($rows as $row) {
				$map[$row->getRole()->value][$row->getAction()] = $row->isAllowed();
			}

			return $map;
		});
	}

	/**
	 * What an auth user's roles give them, joined: an action is allowed where any of their roles
	 * allows it, refused only where each role naming it refuses it.
	 *
	 * @return array<string, int> `action => 1|0`, as {@see AccessRights} holds them
	 */
	public static function of(AuthUserInterface $user): array
	{
		$all    = self::all();
		$joined = [];

		foreach (RolesUtils::roles($user) as $entry) {
			foreach ($all[$entry->getRole()->value] ?? [] as $action => $allowed) {
				$joined[$action] = (int) ($allowed || ($joined[$action] ?? 0));
			}
		}

		return $joined;
	}

	/**
	 * Gives a role the actions of `$allow` and refuses it those of `$deny`; an action named in both
	 * is refused. Every action must be registered ({@see AtomicActionsRegistry}).
	 *
	 * @param list<string> $allow
	 * @param list<string> $deny
	 */
	public static function set(RoleInterface $role, array $allow, array $deny): void
	{
		$name = (string) $role->value;
		$now  = \array_fill_keys($allow, true);

		foreach ($deny as $action) {
			$now[$action] = false;
		}

		foreach ($now as $action => $allowed) {
			$action = (string) $action;
			$row    = new OZRoleRightsQuery()
				->whereRoleIs($name)
				->whereActionIs($action)
				->find(ORMOptions::makePaginated(1))
				->fetchClass();

			if ($row instanceof OZRoleRight) {
				$row->setAllowed($allowed)->save();
			} else {
				new OZRoleRight()
					->setRole($name)
					->setAction($action)
					->setAllowed($allowed)
					->save();
			}
		}

		CacheRegistry::runtime(__CLASS__)->clear();
	}

	/**
	 * Whether an auth user may change the rights of a role: super admins only.
	 */
	public static function canEdit(AuthUserInterface $user): bool
	{
		return Roles::isSuperAdmin($user);
	}
}
