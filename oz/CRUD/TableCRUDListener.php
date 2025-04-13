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

use OZONE\Core\App\Context;
use OZONE\Core\CRUD\Interfaces\TableCRUDListenerInterface;
use OZONE\Core\CRUD\Traits\TableCRUDListenerTrait;

/**
 * Class TableCRUDListener.
 */
abstract class TableCRUDListener implements TableCRUDListenerInterface
{
	use TableCRUDListenerTrait;

	/**
	 * TableCRUDListener constructor.
	 *
	 * @param Context $context
	 */
	public function __construct(protected readonly Context $context) {}
}
