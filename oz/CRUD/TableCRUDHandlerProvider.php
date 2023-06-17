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

use Gobl\CRUD\Handler\Interfaces\CRUDHandlerProviderInterface;
use Gobl\DBAL\Table;
use OZONE\Core\App\Context;
use OZONE\Core\App\Settings;
use OZONE\Core\CRUD\Interfaces\TableCRUDHandlerInterface;
use OZONE\Core\Exceptions\RuntimeException;

/**
 * Class TableCRUDHandlerProvider.
 */
class TableCRUDHandlerProvider implements CRUDHandlerProviderInterface
{
	/**
	 * @var \OZONE\Core\App\Context
	 */
	private Context $context;

	/**
	 * CRUDHandlerProvider constructor.
	 *
	 * @param \OZONE\Core\App\Context $context
	 */
	public function __construct(Context $context)
	{
		$this->context = $context;
	}

	/**
	 * {@inheritDoc}
	 */
	public function getCRUDHandler(Table $table): ?TableCRUDHandlerInterface
	{
		$table_name   = $table->getName();
		$crud_handler = Settings::get('oz.gobl.crud', $table_name);

		if ($crud_handler) {
			if (!\is_subclass_of($crud_handler, TableCRUDHandlerInterface::class)) {
				throw new RuntimeException(\sprintf(
					'CRUD handler "%s" should extends "%s".',
					$table_name,
					TableCRUDHandlerInterface::class
				));
			}

			/** @var class-string<TableCRUDHandlerInterface> $crud_handler */
			return $crud_handler::get($this->context);
		}

		return null;
	}
}
