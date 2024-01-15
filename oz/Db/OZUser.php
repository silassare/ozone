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

use OZONE\Core\Db\Base\OZUser as OZUserBase;
use OZONE\Core\Users\Traits\UserEntityTrait;

/**
 * Class OZUser.
 */
class OZUser extends OZUserBase
{
	use UserEntityTrait;

	// ====================================================
	// =	Your custom implementation goes here
	// ====================================================
}
