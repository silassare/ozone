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

return [
	/**
	 * Session cookie name.
	 */
	'OZ_SESSION_COOKIE_NAME'                 => 'OZONE_SID',

	/**
	 * Max session life time in seconds.
	 *
	 * Default: 30 days
	 */
	'OZ_SESSION_LIFE_TIME'                   => 3600 * 24 * 30, // 30 days

	/**
	 * What identifies the source a session was opened from.
	 *
	 * This can be 'user_agent' (a hash of the User-Agent header) or 'user_ip' (the client IP, as
	 * Context::getUserIP() resolves it). The key is stored with the session and compared on every
	 * later request when OZ_SESSION_HIJACKING_FORCE_SAME_SOURCE is enabled.
	 *
	 * 'user_agent' is the default because it survives a network change; 'user_ip' is stricter, as an
	 * attacker on another network cannot reuse a stolen cookie, at the cost below.
	 */
	'OZ_SESSION_SOURCE_KEY'                  => 'user_agent',

	/**
	 * Enable/Disable same source session hijacking protection.
	 *
	 * A session reaching the server from another source than the one it was opened from is refused
	 * (OZ_SESSION_HIJACKING_DETECTED) when a user is attached to it, and restarted when none is.
	 *
	 * With OZ_SESSION_SOURCE_KEY set to 'user_ip', this forces the session to be used from the same IP
	 * address. This will be annoying for users who use VPNs or change their IP address frequently,
	 * which is usual under a mobile network.
	 */
	'OZ_SESSION_HIJACKING_FORCE_SAME_SOURCE' => 1,
];
