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
use LogicException;
use OZONE\Core\App\Context;
use OZONE\Core\Lang\I18nMessage;
use OZONE\Core\Roles\Interfaces\RoleInterface;
use OZONE\Core\Roles\Roles;
use OZONE\Core\Roles\RolesUtils;

/**
 * Class AllowRuleBuilder.
 */
class AllowRuleBuilder
{
	/**
	 * @var null|array{roles:RoleInterface[], at_least:null|RoleInterface, and_when:null|callable(CRUDAction):bool}
	 */
	protected ?array $has_roles = null;

	/**
	 * @var null|array{types:string[], and_when:null|callable(CRUDAction):bool}
	 */
	protected ?array $only_if_user_is = null;

	/**
	 * @var null|callable(CRUDAction):bool
	 */
	protected $condition;

	/**
	 * @param string[]                       $types
	 * @param null|callable(CRUDAction):bool $and_when
	 */
	public function onlyIfIs(array $types, ?callable $and_when = null): void
	{
		$this->only_if_user_is = [
			'types'    => $types,
			'and_when' => $and_when,
		];
	}

	/**
	 * @param RoleInterface[]                $roles
	 * @param null|RoleInterface             $at_least
	 * @param null|callable(CRUDAction):bool $and_when
	 */
	public function ifRoles(array $roles, ?RoleInterface $at_least, ?callable $and_when = null): void
	{
		$this->has_roles = [
			'roles'    => $roles,
			'at_least' => $at_least,
			'and_when' => $and_when,
		];
	}

	/**
	 * @param RoleInterface                  $at_least
	 * @param null|callable(CRUDAction):bool $and_when
	 */
	public function ifRole(RoleInterface $at_least, ?callable $and_when = null): void
	{
		$this->ifRoles([$at_least], $at_least, $and_when);
	}

	/**
	 * @param callable(CRUDAction):bool $when
	 */
	public function when(callable $when): void
	{
		$this->condition = $when;
	}

	/**
	 * Checks if the action is allowed for the current context.
	 *
	 * @param Context    $context
	 * @param CRUDAction $action
	 *
	 * @return AllowCheckResult
	 */
	public function allowed(Context $context, CRUDAction $action): AllowCheckResult
	{
		if (isset($this->has_roles)) {
			$roles    = $this->has_roles['roles'];
			$at_least = $this->has_roles['at_least'];
			$and_when = $this->has_roles['and_when'] ?? null;

			if (!$context->hasAuthenticatedUser()) {
				return AllowCheckResult::reject(new I18nMessage('OZ_ERROR_UNAUTHENTICATED'));
			}

			$has_role = Roles::hasOneOfRoles(user($context), $roles, $at_least);

			if (!$has_role) {
				return AllowCheckResult::reject(new I18nMessage(
					'OZ_ERROR_USER_IS_MISSING_REQUIRED_ROLE',
					[
						'_allowed_roles' => RolesUtils::ensureRolesString($roles),
						'_at_least_role' => $at_least?->value,
					]
				));
			}
			if (null !== $and_when && !\call_user_func($and_when, $action)) {
				return AllowCheckResult::reject(new I18nMessage(
					'CONDITION_NOT_MET'
				));
			}

			return AllowCheckResult::allow(new I18nMessage('ROLES_AND_CONDITION_MET'));
		}

		if (isset($this->condition)) {
			if (!\call_user_func($this->condition, $action)) {
				return AllowCheckResult::reject(new I18nMessage(
					'CONDITION_NOT_MET'
				));
			}

			return AllowCheckResult::allow(new I18nMessage('CONDITION_MET'));
		}

		if (isset($this->only_if_user_is)) {
			$types     = $this->only_if_user_is['types'];
			$and_when  = $this->only_if_user_is['and_when'] ?? null;

			if (!$context->hasAuthenticatedUser()) {
				return AllowCheckResult::reject(new I18nMessage('OZ_ERROR_UNAUTHENTICATED'));
			}

			$user      = user($context);
			$user_type = $user->getAuthUserType();

			if (!\in_array($user_type, $types, true)) {
				return AllowCheckResult::reject(new I18nMessage(
					'OZ_ERROR_USER_TYPE_NOT_ALLOWED',
					[
						'_allowed_types' => \implode(', ', $types),
						'_user_type'     => $user_type,
					]
				));
			}

			if (null !== $and_when && !\call_user_func($and_when, $action)) {
				return AllowCheckResult::reject(new I18nMessage(
					'CONDITION_NOT_MET'
				));
			}

			return AllowCheckResult::allow(new I18nMessage('AUTH_USER_TYPE_AND_CONDITION_MET'));
		}

		throw new LogicException(
			\sprintf('No rule was set for the action: %s', \get_debug_type($action))
		);
	}
}
