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
use Gobl\DBAL\Table;
use Gobl\ORM\ORMEntity;
use Gobl\ORM\ORMEntityCRUD;
use Gobl\ORM\Utils\ORMClassKind;
use InvalidArgumentException;
use OZONE\Core\App\Context;
use OZONE\Core\Roles\Enums\Role;

/**
 * Class BaseHandler.
 */
abstract class BaseHandler extends TableCRUDListener
{
	/**
	 * @var array<'create'|'create_all'|'delete'|'delete_all'|'read'|'read_all'|'update'|'update_all',AllowRuleBuilder>
	 */
	private array $allow_rules = [];

	public function __construct(
		Context $context,
		protected Table $table,
	) {
		parent::__construct($context);

		$this->listen();
	}

	/**
	 * @param string $action
	 *
	 * @return AllowRuleBuilder
	 */
	public function allow(string $action): AllowRuleBuilder
	{
		if (
			!\in_array($action, [
				'create', 'read', 'update', 'delete', 'create_all', 'read_all', 'update_all', 'delete_all'], true)
		) {
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
	 * @param 'create'|'create_all'|'delete'|'delete_all'|'read'|'read_all'|'update'|'update_all' $action
	 * @param CRUDAction                                                                          $event
	 *
	 * @return bool
	 */
	protected function can(string $action, CRUDAction $event): bool
	{
		if ($this->context->hasAuthenticatedUser()) {
			$full_action = \sprintf('%s.%s', $this->table->getMorphType(), $action);

			// if the access right on this action was explicitly set
			// allow it
			if (auth()->getAccessRights()->can($full_action)) {
				return true;
			}
		}

		$rule = $this->allow_rules[$action] ?? null;

		// otherwise, we check if the action is allowed by
		if ($rule) {
			return $rule->allowed($this->context, $event);
		}

		// we are strict, we want that any action to be explicitly allowed
		// or granted through the access rights
		return false;
	}

	protected function listen(): void
	{
		$this->withCrud(function (ORMEntityCRUD $crud) {
			$crud->onBeforeCreate(fn (BeforeCreate $ev) => $this->can('create', $ev));
			$crud->onBeforeUpdate(fn (BeforeUpdate $ev) => $this->can('update', $ev));
			$crud->onBeforeUpdateAll(fn (BeforeUpdateAll $ev) => $this->can('update_all', $ev));
			$crud->onBeforeRead(fn (BeforeRead $ev) => $this->can('read', $ev));
			$crud->onBeforeReadAll(fn (BeforeReadAll $ev) => $this->can('read_all', $ev));
			$crud->onBeforeDelete(fn (BeforeDelete $ev) => $this->can('delete', $ev));
			$crud->onBeforeDeleteAll(fn (BeforeDeleteAll $ev) => $this->can('delete_all', $ev));
		});
	}

	/**
	 * Call the factory with the CRUD instance.
	 *
	 * @param callable(ORMEntityCRUD):void $factory
	 */
	protected function withCrud(callable $factory): void
	{
		$crud = $this->crud();

		$crud && \call_user_func($factory, $crud);
	}

	/**
	 * Get the CRUD instance for the table.
	 *
	 * @return null|ORMEntityCRUD
	 */
	protected function crud(): ?ORMEntityCRUD
	{
		/** @var class-string<ORMEntity> $entity_class */
		$entity_class = ORMClassKind::ENTITY->getClassFQN($this->table);

		if (!\class_exists($entity_class)) {
			return null;
		}

		return $entity_class::crud();
	}
}
