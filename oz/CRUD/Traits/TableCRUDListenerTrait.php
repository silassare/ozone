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

namespace OZONE\Core\CRUD\Traits;

use Gobl\CRUD\Events\AfterEntityCreation;
use Gobl\CRUD\Events\AfterEntityDeletion;
use Gobl\CRUD\Events\AfterEntityRead;
use Gobl\CRUD\Events\AfterEntityUpdate;
use Gobl\CRUD\Events\BeforeColumnUpdate;
use Gobl\CRUD\Events\BeforeCreate;
use Gobl\CRUD\Events\BeforeCreateFlush;
use Gobl\CRUD\Events\BeforeDelete;
use Gobl\CRUD\Events\BeforeDeleteAll;
use Gobl\CRUD\Events\BeforeDeleteAllFlush;
use Gobl\CRUD\Events\BeforeDeleteFlush;
use Gobl\CRUD\Events\BeforeEntityDeletion;
use Gobl\CRUD\Events\BeforeEntityUpdate;
use Gobl\CRUD\Events\BeforePKColumnWrite;
use Gobl\CRUD\Events\BeforePrivateColumnWrite;
use Gobl\CRUD\Events\BeforeRead;
use Gobl\CRUD\Events\BeforeReadAll;
use Gobl\CRUD\Events\BeforeSensitiveColumnWrite;
use Gobl\CRUD\Events\BeforeUpdate;
use Gobl\CRUD\Events\BeforeUpdateAll;
use Gobl\CRUD\Events\BeforeUpdateAllFlush;
use Gobl\CRUD\Events\BeforeUpdateFlush;
use Gobl\ORM\ORMEntity;

/**
 * Trait TableCRUDListenerTrait.
 */
trait TableCRUDListenerTrait
{
	/**
	 * {@inheritDoc}
	 */
	public function onBeforeCreate(BeforeCreate $action): bool
	{
		return true;
	}

	/**
	 * {@inheritDoc}
	 */
	public function onBeforeCreateFlush(BeforeCreateFlush $action): void {}

	/**
	 * {@inheritDoc}
	 */
	public function onBeforeRead(BeforeRead $action): bool
	{
		return true;
	}

	/**
	 * {@inheritDoc}
	 */
	public function onBeforeReadAll(BeforeReadAll $action): bool
	{
		return true;
	}

	/**
	 * {@inheritDoc}
	 */
	public function onBeforeUpdate(BeforeUpdate $action): bool
	{
		return true;
	}

	/**
	 * {@inheritDoc}
	 */
	public function onBeforeUpdateFlush(BeforeUpdateFlush $action): void {}

	/**
	 * {@inheritDoc}
	 */
	public function onBeforeUpdateAll(BeforeUpdateAll $action): bool
	{
		return false;
	}

	/**
	 * {@inheritDoc}
	 */
	public function onBeforeUpdateAllFlush(BeforeUpdateAllFlush $action): void {}

	/**
	 * {@inheritDoc}
	 */
	public function onBeforeDelete(BeforeDelete $action): bool
	{
		return false;
	}

	/**
	 * {@inheritDoc}
	 */
	public function onBeforeDeleteFlush(BeforeDeleteFlush $action): void {}

	/**
	 * {@inheritDoc}
	 */
	public function onBeforeDeleteAll(BeforeDeleteAll $action): bool
	{
		return false;
	}

	/**
	 * {@inheritDoc}
	 */
	public function onBeforeDeleteAllFlush(BeforeDeleteAllFlush $action): void {}

	/**
	 * {@inheritDoc}
	 */
	public function onBeforeColumnUpdate(BeforeColumnUpdate $action): bool
	{
		return true;
	}

	/**
	 * {@inheritDoc}
	 */
	public function onBeforePKColumnWrite(BeforePKColumnWrite $action): bool
	{
		return false;
	}

	/**
	 * {@inheritDoc}
	 */
	public function onBeforePrivateColumnWrite(BeforePrivateColumnWrite $action): bool
	{
		return false;
	}

	/**
	 * {@inheritDoc}
	 */
	public function onBeforeSensitiveColumnWrite(BeforeSensitiveColumnWrite $action): bool
	{
		return false;
	}

	/**
	 * {@inheritDoc}
	 */
	public function onAfterEntityRead(ORMEntity $entity, AfterEntityRead $event): void {}

	/**
	 * {@inheritDoc}
	 */
	public function onBeforeEntityUpdate(ORMEntity $entity, BeforeEntityUpdate $event): void {}

	/**
	 * {@inheritDoc}
	 */
	public function onAfterEntityUpdate(ORMEntity $entity, AfterEntityUpdate $event): void {}

	/**
	 * {@inheritDoc}
	 */
	public function onBeforeEntityDeletion(ORMEntity $entity, BeforeEntityDeletion $event): void {}

	/**
	 * {@inheritDoc}
	 */
	public function onAfterEntityDeletion(ORMEntity $entity, AfterEntityDeletion $event): void {}

	/**
	 * {@inheritDoc}
	 */
	public function onAfterEntityCreation(ORMEntity $entity, AfterEntityCreation $event): void {}
}
