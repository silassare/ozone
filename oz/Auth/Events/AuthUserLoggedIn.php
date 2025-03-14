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
 * Class AuthUserLoggedIn.
 */
final class AuthUserLoggedIn extends Event
{
	/**
	 * AuthUserLoggedIn constructor.
	 *
	 * @param Context           $context the context
	 * @param AuthUserInterface $user    the user who logged in
	 */
	public function __construct(
		public readonly Context $context,
		public readonly AuthUserInterface $user
	) {}
}
