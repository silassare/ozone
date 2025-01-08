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

use Gobl\CRUD\Interfaces\CRUDEventListenerInterface;
use Gobl\ORM\ORMEntity;
use OZONE\Core\App\Context;

/**
 * Class TableCRUDListenerInterface.
 *
 * @template TEntity of ORMEntity
 *
 * @extends CRUDEventListenerInterface<TEntity>
 */
interface TableCRUDListenerInterface extends CRUDEventListenerInterface
{
	/**
	 * Gets the CRUD listener should be able to register itself.
	 *
	 * @param Context $context
	 */
	public static function register(Context $context): void;
}
