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

namespace OZONE\Core\CRUD;

use Gobl\CRUD\CRUDAction;
use Gobl\CRUD\Events\BeforeCreate;
use Gobl\CRUD\Events\BeforeDelete;
use Gobl\CRUD\Events\BeforeDeleteAll;
use Gobl\CRUD\Events\BeforeRead;
use Gobl\CRUD\Events\BeforeReadAll;
use Gobl\CRUD\Events\BeforeUpdate;
use Gobl\CRUD\Events\BeforeUpdateAll;
use LogicException;
use OZONE\Core\App\Context;
use OZONE\Core\Roles\Interfaces\RoleInterface;
use OZONE\Core\Roles\Roles;

/**
 * Class AllowRuleBuilder.
 */
class AllowRuleBuilder
{
	/**
	 * @var null|array{roles:RoleInterface[], at_least:null|RoleInterface, and_when:null|callable(CRUDAction):bool}
	 */
	protected ?array $role_based = null;

	/**
	 * @var null|callable(CRUDAction):bool
	 */
	protected $condition_based;

	/**
	 * @param RoleInterface[]                                                                                                     $roles
	 * @param null|RoleInterface                                                                                                  $at_least
	 * @param null|callable(BeforeCreate|BeforeDelete|BeforeDeleteAll|BeforeRead|BeforeReadAll|BeforeUpdate|BeforeUpdateAll):bool $and_when
	 */
	public function ifRoles(array $roles, ?RoleInterface $at_least, ?callable $and_when = null): void
	{
		$this->role_based = [
			'roles'    => $roles,
			'at_least' => $at_least,
			'and_when' => $and_when,
		];
	}

	/**
	 * @param RoleInterface                                                                                                       $at_least
	 * @param null|callable(BeforeCreate|BeforeDelete|BeforeDeleteAll|BeforeRead|BeforeReadAll|BeforeUpdate|BeforeUpdateAll):bool $and_when
	 */
	public function ifRole(RoleInterface $at_least, ?callable $and_when = null): void
	{
		$this->ifRoles([$at_least], $at_least, $and_when);
	}

	public function when(callable $when): void
	{
		$this->condition_based = $when;
	}

	public function allowed(Context $context, CRUDAction $action): bool
	{
		if (null !== $this->role_based) {
			$roles    = $this->role_based['roles'];
			$at_least = $this->role_based['at_least'];
			$and_when = $this->role_based['and_when'] ?? null;
			$has_role = Roles::hasOneOfRoles(user($context), $roles, $at_least);

			if (!$has_role) {
				return false;
			}
			if (null !== $and_when) {
				return $and_when($action);
			}

			return true;
		}

		if (null !== $this->condition_based) {
			return \call_user_func($this->condition_based, $action);
		}

		throw new LogicException(
			\sprintf('No condition was set for the action: %s', \get_debug_type($action))
		);
	}
}
