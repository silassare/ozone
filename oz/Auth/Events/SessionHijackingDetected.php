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
use OZONE\Core\Sessions\Session;
use PHPUtils\Events\Event;

/**
 * Class SessionHijackingDetected.
 *
 * Dispatched when a session source-key mismatch is detected for an authenticated user.
 * Listeners can use this to send security alerts to the user.
 */
final class SessionHijackingDetected extends Event
{
	/**
	 * SessionHijackingDetected constructor.
	 *
	 * @param Context $context the current request context
	 * @param Session $session the session with the mismatched source key
	 */
	public function __construct(
		public readonly Context $context,
		public readonly Session $session
	) {}
}
