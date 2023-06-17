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

namespace OZONE\Core\Db\Base;

/**
 * Class OZRolesCrud.
 */
abstract class OZRolesCrud implements \Gobl\CRUD\Handler\Interfaces\CRUDHandlerInterface
{
	/**
	 * {@inheritDoc}
	 */
	public function onEntityEvent(\Gobl\ORM\ORMEntity $entity, \Gobl\CRUD\CRUDEntityEvent $event): void
	{
		/** @var \OZONE\Core\Db\OZRole $entity */
		switch ($event) {
			case \Gobl\CRUD\CRUDEntityEvent::AFTER_CREATE:
				$this->onAfterCreateEntity($entity);

				break;

			case \Gobl\CRUD\CRUDEntityEvent::AFTER_READ:
				$this->onAfterReadEntity($entity);

				break;

			case \Gobl\CRUD\CRUDEntityEvent::BEFORE_UPDATE:
				$this->onBeforeUpdateEntity($entity);

				break;

			case \Gobl\CRUD\CRUDEntityEvent::AFTER_UPDATE:
				$this->onAfterUpdateEntity($entity);

				break;

			case \Gobl\CRUD\CRUDEntityEvent::BEFORE_DELETE:
				$this->onBeforeDeleteEntity($entity);

				break;

			case \Gobl\CRUD\CRUDEntityEvent::AFTER_DELETE:
				$this->onAfterDeleteEntity($entity);

				break;
		}
	}

	/**
	 * Called when an entity is created.
	 *
	 * You can run your own business logic, verify ownership,
	 * or other access right on the entity
	 *
	 * @param \OZONE\Core\Db\OZRole $entity
	 */
	public function onAfterCreateEntity(\OZONE\Core\Db\OZRole $entity): void
	{
	}

	/**
	 * Called when we read an entity.
	 *
	 * You can run your own business logic, verify ownership,
	 * or other access right on the entity
	 *
	 * @param \OZONE\Core\Db\OZRole $entity
	 */
	public function onAfterReadEntity(\OZONE\Core\Db\OZRole $entity): void
	{
	}

	/**
	 * Called before an entity is updated.
	 *
	 * You can run your own business logic, verify ownership,
	 * or other access right on the entity
	 *
	 * @param \OZONE\Core\Db\OZRole $entity
	 */
	public function onBeforeUpdateEntity(\OZONE\Core\Db\OZRole $entity): void
	{
	}

	/**
	 * Called after an entity is updated.
	 *
	 * You can run your own business logic, verify ownership,
	 * or other access right on the entity
	 *
	 * @param \OZONE\Core\Db\OZRole $entity
	 */
	public function onAfterUpdateEntity(\OZONE\Core\Db\OZRole $entity): void
	{
	}

	/**
	 * Called before an entity is deleted.
	 *
	 * You can run your own business logic, verify ownership,
	 * or other access right on the entity
	 *
	 * @param \OZONE\Core\Db\OZRole $entity
	 */
	public function onBeforeDeleteEntity(\OZONE\Core\Db\OZRole $entity): void
	{
	}

	/**
	 * Called after an entity is deleted.
	 *
	 * You can run your own business logic, verify ownership,
	 * or other access right on the entity
	 *
	 * @param \OZONE\Core\Db\OZRole $entity
	 */
	public function onAfterDeleteEntity(\OZONE\Core\Db\OZRole $entity): void
	{
	}
}
