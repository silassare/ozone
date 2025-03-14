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
use OZONE\Core\Auth\Interfaces\AuthUserInterface;
use PHPUtils\Events\Event;

/**
 * Class AuthUserLogInFailed.
 *
 * This event is triggered when a user is logged out.
 */
final class AuthUserLoggedOut extends Event
{
	/**
	 * AuthUserLogInFailed constructor.
	 *
	 * @param Context           $context the context
	 * @param AuthUserInterface $user    the user that was logged out
	 */
	public function __construct(
		public readonly Context $context,
		public readonly AuthUserInterface $user
	) {}
}
