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

namespace OZONE\OZ\Core;

use Gobl\CRUD\CRUDColumnUpdate;
use Gobl\CRUD\CRUDCreate;
use Gobl\CRUD\CRUDDelete;
use Gobl\CRUD\CRUDDeleteAll;
use Gobl\CRUD\CRUDRead;
use Gobl\CRUD\CRUDReadAll;
use Gobl\CRUD\CRUDUpdate;
use Gobl\CRUD\CRUDUpdateAll;
use Gobl\CRUD\Handler\Interfaces\CRUDHandlerInterface;
use Gobl\DBAL\Column;
use Gobl\ORM\ORMEntity;

/**
 * Class CRUDHandler.
 */
class CRUDHandler implements CRUDHandlerInterface
{
	/**
	 * @var \OZONE\OZ\Core\Context
	 */
	private Context $context;

	/**
	 * CRUDHandler constructor.
	 *
	 * @param \OZONE\OZ\Core\Context $context
	 */
	public function __construct(Context $context)
	{
		$this->context = $context;
	}

	/**
	 * CRUDHandler destructor.
	 */
	public function __destruct()
	{
		unset($this->context);
	}

	/**
	 * {@inheritDoc}
	 */
	public function onBeforeCreate(CRUDCreate $action): bool
	{
		return false;
	}

	/**
	 * {@inheritDoc}
	 */
	public function onBeforeRead(CRUDRead $action): bool
	{
		return false;
	}

	/**
	 * {@inheritDoc}
	 */
	public function onBeforeUpdate(CRUDUpdate $action): bool
	{
		return false;
	}

	/**
	 * {@inheritDoc}
	 */
	public function onBeforeDelete(CRUDDelete $action): bool
	{
		return false;
	}

	/**
	 * {@inheritDoc}
	 */
	public function onBeforeReadAll(CRUDReadAll $action): bool
	{
		return false;
	}

	/**
	 * {@inheritDoc}
	 */
	public function onBeforeUpdateAll(CRUDUpdateAll $action): bool
	{
		return false;
	}

	/**
	 * {@inheritDoc}
	 */
	public function onBeforeDeleteAll(CRUDDeleteAll $action): bool
	{
		return false;
	}

	/**
	 * {@inheritDoc}
	 */
	public function onBeforeColumnUpdate(CRUDColumnUpdate $action): bool
	{
		return false;
	}

	/**
	 * {@inheritDoc}
	 */
	public function onAfterCreateEntity(ORMEntity $entity): void
	{
	}

	/**
	 * {@inheritDoc}
	 */
	public function onAfterReadEntity(ORMEntity $entity): void
	{
	}

	/**
	 * {@inheritDoc}
	 */
	public function onBeforeUpdateEntity(ORMEntity $entity): void
	{
	}

	/**
	 * {@inheritDoc}
	 */
	public function onAfterUpdateEntity(ORMEntity $entity): void
	{
	}

	/**
	 * {@inheritDoc}
	 */
	public function onBeforeDeleteEntity(ORMEntity $entity): void
	{
	}

	/**
	 * {@inheritDoc}
	 */
	public function onAfterDeleteEntity(ORMEntity $entity): void
	{
	}

	/**
	 * {@inheritDoc}
	 */
	public function shouldWritePkColumn(Column $column): bool
	{
		return false;
	}

	/**
	 * {@inheritDoc}
	 */
	public function shouldWritePrivateColumn(Column $column): bool
	{
		return false;
	}

	/**
	 * {@inheritDoc}
	 */
	public function autoFillCreateForm(CRUDCreate $action): void
	{
	}

	/**
	 * {@inheritDoc}
	 */
	public function autoFillUpdateFormAndFilters(CRUDUpdate|CRUDUpdateAll $action): void
	{
	}

	/**
	 * Gets the context.
	 *
	 * @return \OZONE\OZ\Core\Context
	 */
	protected function getContext(): Context
	{
		return $this->context;
	}

	/**
	 * @throws \OZONE\OZ\Exceptions\ForbiddenException
	 * @throws \OZONE\OZ\Exceptions\UnverifiedUserException
	 */
	protected function assertIsAdmin(): void
	{
		$this->getContext()
			->getUsersManager()
			->assertIsAdmin();
	}

	/**
	 * @throws \OZONE\OZ\Exceptions\UnverifiedUserException
	 */
	protected function assertUserVerified(): void
	{
		$this->getContext()
			->getUsersManager()
			->assertUserVerified();
	}
}
