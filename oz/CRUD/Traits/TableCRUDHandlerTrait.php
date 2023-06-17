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

use Gobl\CRUD\CRUDColumnUpdate;
use Gobl\CRUD\CRUDCreate;
use Gobl\CRUD\CRUDDelete;
use Gobl\CRUD\CRUDDeleteAll;
use Gobl\CRUD\CRUDRead;
use Gobl\CRUD\CRUDReadAll;
use Gobl\CRUD\CRUDUpdate;
use Gobl\CRUD\CRUDUpdateAll;
use Gobl\DBAL\Column;
use OZONE\Core\App\Context;

/**
 * Trait TableCRUDHandlerTrait.
 */
trait TableCRUDHandlerTrait
{
	/**
	 * TableCRUDHandlerTrait constructor.
	 *
	 * @param \OZONE\Core\App\Context $context
	 */
	protected function __construct(protected readonly Context $context)
	{
	}

	/**
	 * TableCRUDHandlerTrait destructor.
	 */
	public function __destruct()
	{
		unset($this->context);
	}

	/**
	 * {@inheritDoc}
	 */
	public static function get(Context $context): self
	{
		return new static($context);
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
	public function shouldWritePkColumn(Column $column, mixed $value): bool
	{
		return false;
	}

	/**
	 * {@inheritDoc}
	 */
	public function shouldWritePrivateColumn(Column $column, mixed $value): bool
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
	 * @throws \OZONE\Core\Exceptions\ForbiddenException
	 * @throws \OZONE\Core\Exceptions\UnverifiedUserException
	 */
	protected function assertIsAdmin(): void
	{
		$this->context
			->getUsers()
			->assertIsAdmin();
	}

	/**
	 * {@inheritDoc}
	 */
	protected function assertUserVerified(): void
	{
		$this->context->user();
	}
}
