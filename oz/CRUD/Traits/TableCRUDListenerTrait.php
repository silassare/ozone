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
	public function onBeforeCreate(BeforeCreate $action): bool
	{
		return true;
	}

	public function onBeforeCreateFlush(BeforeCreateFlush $action): void {}

	public function onBeforeRead(BeforeRead $action): bool
	{
		return true;
	}

	public function onBeforeReadAll(BeforeReadAll $action): bool
	{
		return true;
	}

	public function onBeforeUpdate(BeforeUpdate $action): bool
	{
		return true;
	}

	public function onBeforeUpdateFlush(BeforeUpdateFlush $action): void {}

	public function onBeforeUpdateAll(BeforeUpdateAll $action): bool
	{
		return false;
	}

	public function onBeforeUpdateAllFlush(BeforeUpdateAllFlush $action): void {}

	public function onBeforeDelete(BeforeDelete $action): bool
	{
		return false;
	}

	public function onBeforeDeleteFlush(BeforeDeleteFlush $action): void {}

	public function onBeforeDeleteAll(BeforeDeleteAll $action): bool
	{
		return false;
	}

	public function onBeforeDeleteAllFlush(BeforeDeleteAllFlush $action): void {}

	public function onBeforeColumnUpdate(BeforeColumnUpdate $action): bool
	{
		return true;
	}

	public function onBeforePKColumnWrite(BeforePKColumnWrite $action): bool
	{
		return false;
	}

	public function onBeforePrivateColumnWrite(BeforePrivateColumnWrite $action): bool
	{
		return false;
	}

	public function onBeforeSensitiveColumnWrite(BeforeSensitiveColumnWrite $action): bool
	{
		return false;
	}

	public function onAfterEntityRead(ORMEntity $entity, AfterEntityRead $event): void {}

	public function onBeforeEntityUpdate(ORMEntity $entity, BeforeEntityUpdate $event): void {}

	public function onAfterEntityUpdate(ORMEntity $entity, AfterEntityUpdate $event): void {}

	public function onBeforeEntityDeletion(ORMEntity $entity, BeforeEntityDeletion $event): void {}

	public function onAfterEntityDeletion(ORMEntity $entity, AfterEntityDeletion $event): void {}

	public function onAfterEntityCreation(ORMEntity $entity, AfterEntityCreation $event): void {}
}
