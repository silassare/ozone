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

namespace OZONE\Core\Auth\Events;

use OZONE\Core\App\Context;
use PHPUtils\Events\Event;

/**
 * Class AuthUserUnknown.
 *
 * This event is triggered when a user try to log in but the user is unknown.
 */
final class AuthUserUnknown extends Event
{
	/**
	 * AuthUserUnknown constructor.
	 *
	 * @param Context $context the context
	 */
	public function __construct(public readonly Context $context) {}
}
