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

namespace OZONE\Core\Users\Events;

use OZONE\Core\App\Context;
use OZONE\Core\Db\OZUser;
use PHPUtils\Events\Event;

/**
 * Class UserLogInFailed.
 *
 * This event is triggered when a user failed to log in.
 */
final class UserLogInFailed extends Event
{
	/**
	 * UserLogInFailed constructor.
	 *
	 * @param Context $context the context
	 * @param OZUser  $user    the user who failed to log in
	 */
	public function __construct(
		public readonly Context $context,
		public readonly OZUser $user
	) {}
}
