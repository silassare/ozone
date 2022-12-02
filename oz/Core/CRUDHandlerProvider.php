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

use Gobl\CRUD\Handler\Interfaces\CRUDHandlerProviderInterface;
use Gobl\DBAL\Table;
use OZONE\OZ\Exceptions\RuntimeException;

/**
 * Class CRUDHandlerProvider.
 */
class CRUDHandlerProvider implements CRUDHandlerProviderInterface
{
	/**
	 * @var \OZONE\OZ\Core\Context
	 */
	private Context $context;

	/**
	 * CRUDHandlerProvider constructor.
	 *
	 * @param \OZONE\OZ\Core\Context $context
	 */
	public function __construct(Context $context)
	{
		$this->context = $context;
	}

	/**
	 * {@inheritDoc}
	 */
	public function getCRUDHandler(Table $table): ?CRUDHandler
	{
		$table_name   = $table->getName();
		$crud_handler = Configs::get('oz.gobl.crud', $table_name);

		if ($crud_handler) {
			if (!\is_subclass_of($crud_handler, CRUDHandler::class)) {
				throw new RuntimeException(\sprintf(
					'CRUD handler "%s" should extends "%s".',
					$table_name,
					CRUDHandler::class
				));
			}

			/** @var class-string<\OZONE\OZ\Core\CRUDHandler> $crud_handler*/
			return new $crud_handler($this->context);
		}

		return null;
	}
}
