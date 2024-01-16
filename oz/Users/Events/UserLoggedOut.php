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

use OZONE\Core\App\Context;
use OZONE\Core\Db\OZUser;
use PHPUtils\Events\Event;

/**
 * Class UserLoggedOut.
 *
 * This event is triggered when a user is logged out.
 */
final class UserLoggedOut extends Event
{
	/**
	 * UserLoggedOut constructor.
	 *
	 * @param Context $context the context
	 * @param OZUser  $user    the user that was logged out
	 */
	public function __construct(
		public readonly Context $context,
		public readonly OZUser $user
	) {}
}
