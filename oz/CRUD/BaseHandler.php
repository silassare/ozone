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
use Gobl\CRUD\CRUDEventProducer;
use Gobl\CRUD\Enums\ActionType;
use Gobl\CRUD\Events\BeforeCreate;
use Gobl\CRUD\Events\BeforeCreateBulk;
use Gobl\CRUD\Events\BeforeDelete;
use Gobl\CRUD\Events\BeforeDeleteAll;
use Gobl\CRUD\Events\BeforeRead;
use Gobl\CRUD\Events\BeforeReadAll;
use Gobl\CRUD\Events\BeforeUpdate;
use Gobl\CRUD\Events\BeforeUpdateAll;
use Gobl\DBAL\Table;
use Gobl\ORM\ORMEntity;
use InvalidArgumentException;
use OZONE\Core\Lang\I18n;
use OZONE\Core\Roles\Enums\Role;

/**
 * Class BaseHandler.
 *
 * @template TEntity of ORMEntity
 *
 * @extends TableCRUDListener<TEntity>
 */
abstract class BaseHandler extends TableCRUDListener
{
	/**
	 * By table action name ({@see TableActions}).
	 *
	 * @var array<string, AllowRuleBuilder>
	 */
	private array $allow_rules                         = [];
	private ?AllowCheckResult $last_allow_check_result = null;

	public function __construct(protected Table $table)
	{
		// Several rows created at once is not what a table opens by default: administrators only,
		// unless the handler says otherwise, before this or after it (or a reader holds
		// `{morph type}.create_bulk`).
		$bulk = TableActions::nameOf(ActionType::CREATE_BULK);

		if (!isset($this->allow_rules[$bulk])) {
			$this->allow($bulk)->ifRole(Role::ADMIN);
		}

		$this->listen();
	}

	/**
	 * Gets the last allow check result.
	 */
	public function getLastAllowCheckResult(): ?AllowCheckResult
	{
		return $this->last_allow_check_result;
	}

	/**
	 * The rule of a table action.
	 *
	 * @param string $action a table action's name ({@see TableActions::names()})
	 */
	public function allow(string $action): AllowRuleBuilder
	{
		if (!TableActions::isName($action)) {
			throw new InvalidArgumentException('Invalid action: ' . $action);
		}

		return $this->allow_rules[$action] = new AllowRuleBuilder();
	}

	protected function adminOnlyRules(): void
	{
		// we make strict default rules
		// only admins/super-admins or user with explicit access rights can access the table
		$this->allow('create')->ifRole(Role::ADMIN);
		$this->allow('read')->ifRole(Role::ADMIN);
		$this->allow('read_all')->ifRole(Role::ADMIN);
		$this->allow('delete')->ifRole(Role::ADMIN);
		$this->allow('delete_all')->ifRole(Role::ADMIN);
		$this->allow('update')->ifRole(Role::ADMIN);
		$this->allow('update_all')->ifRole(Role::ADMIN);
	}

	/**
	 * @param string $action a table action's name ({@see TableActions::names()})
	 */
	protected function can(string $action, CRUDAction $event): bool
	{
		// The request being handled, whoever it is for: never the context the handler was built in.
		$context = $this->context();

		if ($context->hasAuthenticatedUser()) {
			$full_action = \sprintf('%s.%s', $this->table->getMorphType(), $action);

			// if the access right on this action was explicitly set
			// allow it
			if (auth($context)->getAccessRights()->can($full_action)) {
				$this->last_allow_check_result = AllowCheckResult::allow(
					I18n::m('HAS_REQUIRED_ACCESS_RIGHT', [
						'_action' => $full_action,
					])
				);

				return true;
			}
		}

		$rule = $this->allow_rules[$action] ?? null;

		// otherwise, if there is a rule defined we check if the action is allowed
		if ($rule) {
			$result = $rule->allowed($context, $event);

			$this->last_allow_check_result = $result;

			return $result->isAllowed();
		}

		$this->last_allow_check_result = AllowCheckResult::reject(
			I18n::m('NO_RULE_DEFINED_FOR_ACTION', ['_action' => $action])
		);

		// we are strict, we want that any action to be explicitly allowed
		// or granted through the access rights
		return false;
	}

	protected function listen(): void
	{
		$this->withCrud(function (CRUDEventProducer $crud): void {
			$crud->onBeforeCreate(fn (BeforeCreate $ev) => $this->can(TableActions::nameOf(ActionType::CREATE), $ev));
			$crud->onBeforeCreateBulk(fn (BeforeCreateBulk $ev) => $this->can(TableActions::nameOf(ActionType::CREATE_BULK), $ev));
			$crud->onBeforeUpdate(fn (BeforeUpdate $ev) => $this->can(TableActions::nameOf(ActionType::UPDATE), $ev));
			$crud->onBeforeUpdateAll(fn (BeforeUpdateAll $ev) => $this->can(TableActions::nameOf(ActionType::UPDATE_ALL), $ev));
			$crud->onBeforeRead(fn (BeforeRead $ev) => $this->can(TableActions::nameOf(ActionType::READ), $ev));
			$crud->onBeforeReadAll(fn (BeforeReadAll $ev) => $this->can(TableActions::nameOf(ActionType::READ_ALL), $ev));
			$crud->onBeforeDelete(fn (BeforeDelete $ev) => $this->can(TableActions::nameOf(ActionType::DELETE), $ev));
			$crud->onBeforeDeleteAll(fn (BeforeDeleteAll $ev) => $this->can(TableActions::nameOf(ActionType::DELETE_ALL), $ev));
		});
	}

	/**
	 * Call the factory with the CRUD events of the table.
	 *
	 * @param callable(CRUDEventProducer):void $factory
	 */
	protected function withCrud(callable $factory): void
	{
		$factory($this->crud());
	}

	/**
	 * The CRUD events of the table, by its name: no generated class is loaded.
	 *
	 * Listeners attach while the database initializes, which may happen while a generated class of
	 * this very table is loading: going through the entity then found it missing (`class_exists()` is
	 * false for a class still loading), and the table was left without its rules for the process.
	 */
	protected function crud(): CRUDEventProducer
	{
		return TableCRUD::events($this->table->getName());
	}
}
