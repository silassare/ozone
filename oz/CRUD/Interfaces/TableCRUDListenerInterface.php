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
use OZONE\Core\Hooks\Events\InitHook;
use OZONE\Core\OZone;

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
	 * Called when O'Zone is fully booted but before {@see InitHook}.
	 * This is the right place to register CRUD listeners.
	 * This will be called only if the project is fully installed {@see OZone::isInstalled()}.
	 *
	 * @param Context $context
	 */
	public static function register(Context $context): void;
}
