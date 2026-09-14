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

use Gobl\ORM\ORMEntity;
use OZONE\Core\App\Context;
use OZONE\Core\CRUD\Interfaces\TableCRUDListenerInterface;
use OZONE\Core\CRUD\Traits\TableCRUDListenerTrait;

/**
 * Class TableCRUDListener.
 *
 * @template  TEntity of ORMEntity
 *
 * @implements TableCRUDListenerInterface<TEntity>
 */
abstract class TableCRUDListener implements TableCRUDListenerInterface
{
	use TableCRUDListenerTrait;

	/**
	 * The context of the request being handled, resolved when an event fires.
	 *
	 * Listeners are registered once per process, and a worker serves many requests: a context kept
	 * at registration would be the boot one -- no request, no user -- for every later request.
	 */
	protected function context(): Context
	{
		return Context::current();
	}
}
