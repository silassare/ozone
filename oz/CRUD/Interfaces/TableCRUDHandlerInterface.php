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

namespace OZONE\Core\CRUD\Interfaces;

use Gobl\CRUD\Handler\Interfaces\CRUDHandlerInterface;
use OZONE\Core\App\Context;

/**
 * Class TableCRUDHandlerInterface.
 */
interface TableCRUDHandlerInterface extends CRUDHandlerInterface
{
	/**
	 * Gets the CRUD handler instance.
	 *
	 * @param \OZONE\Core\App\Context $context
	 *
	 * @return self
	 */
	public static function get(Context $context): self;
}
