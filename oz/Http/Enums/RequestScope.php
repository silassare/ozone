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

namespace OZONE\Core\Http\Enums;

use OZONE\Core\App\Context;
use OZONE\Core\Auth\AuthUsers;
use OZONE\Core\Auth\Interfaces\AuthenticationMethodStatefulInterface;
use OZONE\Core\CSRF\CSRF;
use OZONE\Core\Forms\Form;
use RuntimeException;

/**
 * Identifies which aspect of an HTTP request anchors a security or caching scope.
 *
 * Used wherever a token or cached value needs to be tied to a specific principal:
 * - CSRF token generation and validation ({@see CSRF})
 * - Form resume cache for incremental multi-request submissions ({@see Form::resume()})
 *
 * STATE   - tied to a stateful auth context (any auth method implementing
 *           {@see AuthenticationMethodStatefulInterface}).
 *           Not exclusively session-based; also covers bearer-token states etc.
 *
 * USER    - tied to the authenticated user identity across all user types
 *           (uses {@see AuthUsers::ref()} which returns "{type}.{id}", unique
 *           across the entire multi-user-type system).
 *
 * HOST    - tied to the request host:port (e.g. rate-limit per domain).
 *
 * USER_IP - tied to the client IP address.
 */
enum RequestScope: string
{
	/**
	 * Scope = stateful auth stateID().
	 *
	 * Requires a stateful authentication method.
	 * Use with {@see Context::requireStatefulAuth()}.
	 */
	case STATE = 'state';

	/**
	 * Scope = authenticated user ref.
	 *
	 * Uses {@see AuthUsers::ref()} to produce a cross-type-unique user reference
	 * ("{userType}.{identifier}"). Works with any registered user type (oz_user,
	 * client, lead, etc.). Useful for multi-device flows.
	 */
	case USER = 'user';

	/**
	 * Scope = request host:port.
	 */
	case HOST = 'host';

	/**
	 * Scope = client IP address.
	 */
	case USER_IP = 'user_ip';

	/**
	 * Resolves this scope to a concrete identifier string for use as a cache key fragment.
	 *
	 * @param Context $context The current request context
	 *
	 * @return string Non-empty identifier for this scope
	 */
	public function resolveId(Context $context): string
	{
		return match ($this) {
			self::STATE   => $context->requireStatefulAuth()->stateID(),
			self::USER    => AuthUsers::ref($context->auth()->user()),
			self::HOST    => $context->getHost(true),
			self::USER_IP => $context->getUserIP(true, true)
				?? throw new RuntimeException('Cannot resolve USER_IP scope: client IP address is unavailable.'),
		};
	}
}
