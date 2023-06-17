<?php

/**
 * Copyright (c) 2017-present, Emile Silas Sare
 *
 * This file is part of OZone package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace OZONE\Core\Users\Events;

use OZONE\Core\Db\OZUser;
use PHPUtils\Events\Event;

/**
 * Class UserCreated.
 */
final class UserCreated extends Event
{
	public function __construct(private OZUser $user)
	{
	}

	/**
	 * @return \OZONE\Core\Db\OZUser
	 */
	public function getUser(): OZUser
	{
		return $this->user;
	}
}
