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

namespace OZONE\Core\Db;

/**
 * Class OZFilesCrud.
 */
abstract class OZFilesCrud extends \OZONE\Core\Db\Base\OZFilesCrud implements \OZONE\Core\CRUD\Interfaces\TableCRUDHandlerInterface
{
	use \OZONE\Core\CRUD\Traits\TableCRUDHandlerTrait;

	// ====================================================
	// =	Your custom implementation goes here
	// ====================================================
}
