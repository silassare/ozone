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
 * Class SessionHijackingDetected.
 *
 * Dispatched when a request carries the session of an authenticated user but comes from another
 * source than the one the session was opened from. Listeners can use this to send security alerts
 * to the user, or to record the attempt.
 *
 * The session object is not handed over: it is the state of one authentication method, so what a
 * listener needs of it is here instead.
 *
 * `$session_id` says which session was targeted, for an audit trail or to revoke it. It is also the
 * credential the request is being refused the use of, and the session is left alive for its owner:
 * logging it writes a working session cookie to disk, and it is never shown to the user.
 */
final class SessionHijackingDetected extends Event
{
	/**
	 * SessionHijackingDetected constructor.
	 *
	 * @param Context           $context            the current request context
	 * @param AuthUserInterface $auth_user          the user the session is attached to
	 * @param string            $session_id         the targeted session's ID, a secret
	 * @param string            $opened_source_key  the source key the session was opened from
	 * @param string            $request_source_key this request's source key
	 */
	public function __construct(
		public readonly Context $context,
		public readonly AuthUserInterface $auth_user,
		public readonly string $session_id,
		public readonly string $opened_source_key,
		public readonly string $request_source_key
	) {}
}
