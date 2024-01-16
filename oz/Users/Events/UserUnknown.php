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
use PHPUtils\Events\Event;

/**
 * Class UserUnknown.
 *
 * This event is triggered when a user try to log in but the user is unknown.
 */
final class UserUnknown extends Event
{
	/**
	 * UserUnknown constructor.
	 *
	 * @param Context $context the context
	 */
	public function __construct(public readonly Context $context) {}
}
