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

use OZONE\Core\Auth\Enums\AuthenticationMethodScheme;
use OZONE\Core\Auth\Interfaces\AuthenticationMethodInterface;

return [
	/**
	 * Max number of auth code try.
	 *
	 * @default 3
	 */
	'OZ_AUTH_CODE_TRY_MAX'        => 3,

	/**
	 * Auth code life time in seconds.
	 *
	 * @default 3600 (1 hour)
	 */
	'OZ_AUTH_CODE_LIFE_TIME'      => 3600,

	/**
	 * Lifetime of an email or phone ownership verification, in seconds, per purpose
	 * (`VerificationPurpose`): how long the owner has to answer the code or open the link.
	 *
	 * A purpose with no entry here lives `OZ_AUTH_CODE_LIFE_TIME`. A sign-up is the one the owner may
	 * come back to much later, and nothing exists until it is answered; a password reset stays short,
	 * since it opens an account that already exists.
	 *
	 * @default sign-up a day, the rest an hour
	 */
	'OZ_AUTH_VERIFICATION_LIFETIMES' => [
		'check'            => 3600,
		'sign-up'          => 86400,
		'account-recovery' => 3600,
	],

	/**
	 * Name of the project's sign-in route, linked by the pages that end an authorization link: the
	 * one that confirms a link, and the one that confirms a new password after an account recovery.
	 * Left empty, those pages say what happened and stop there, leaving the person to find their own
	 * way back.
	 *
	 * A route name, never a URL: the link is built with the project's own router
	 * (`Context::buildRouteUri()`), so nothing a request carries can send these pages elsewhere. The
	 * route takes no parameter, and must be registered where the page is served (`oz.routes`, or the
	 * `oz.routes.web` of the scope that serves it). A name the router of that scope does not know, or
	 * one that takes parameters, is logged and shows no link, rather than failing a page that is
	 * reached once the password is already changed.
	 *
	 * @default '' (no link onward)
	 */
	'OZ_AUTH_SIGN_IN_ROUTE' => '',

	/**
	 * Auth code length.
	 */
	'OZ_AUTH_CODE_LENGTH'         => 6,

	/**
	 * Enable alpha numeric auth code.
	 *
	 * @default false
	 */
	'OZ_AUTH_CODE_USE_ALPHA_NUM'  => false,

	/**
	 * Authentication by api key header name.
	 */
	'OZ_AUTH_API_KEY_HEADER_NAME' => 'x-ozone-api-key',

	/**
	 * Failed password attempts allowed per account (or per unknown identifier) before
	 * it is locked for the rest of the window. 0 disables the lock.
	 *
	 * @default 5
	 */
	'OZ_AUTH_LOGIN_MAX_FAILURES' => 5,

	/**
	 * Length in seconds of the failure window, counted from the first failure.
	 *
	 * @default 900 (15 minutes)
	 */
	'OZ_AUTH_LOGIN_FAILURES_WINDOW' => 900,

	/**
	 * Login requests allowed per client IP within `OZ_AUTH_LOGIN_IP_INTERVAL` seconds.
	 *
	 * @default 20
	 */
	'OZ_AUTH_LOGIN_IP_RATE' => 20,

	/**
	 * Length in seconds of the per-IP login rate window.
	 *
	 * @default 60
	 */
	'OZ_AUTH_LOGIN_IP_INTERVAL' => 60,

	/**
	 * How long, in seconds, a digest auth nonce is accepted after it was issued. An expired
	 * nonce gets a new challenge flagged `stale=true`; each nonce is accepted only once.
	 *
	 * @default 300 (5 minutes)
	 */
	'OZ_AUTH_DIGEST_NONCE_LIFETIME' => 300,

	/**
	 * Default allowed authentication methods or schemes to be defined for API routes.
	 *
	 * This is an array of {@see AuthenticationMethodScheme}
	 * and FQCN of classes implementing {@see AuthenticationMethodInterface}.
	 */
	'OZ_AUTH_API_AUTH_METHODS' => [
		AuthenticationMethodScheme::BEARER,
		AuthenticationMethodScheme::API_KEY_HEADER,
		AuthenticationMethodScheme::SESSION,
	],

	/**
	 * Default allowed authentication methods or schemes to be defined for WEB routes.
	 *
	 * This is an array of {@see AuthenticationMethodScheme}
	 * and FQCN of classes implementing {@see AuthenticationMethodInterface}.
	 */
	'OZ_AUTH_WEB_AUTH_METHODS' => [
		AuthenticationMethodScheme::SESSION,
	],

	/**
	 * Add Clear-Site-Data header on logout response.
	 *
	 * @see https://developer.mozilla.org/en-US/docs/Web/HTTP/Headers/Clear-Site-Data
	 *
	 * @default true
	 */
	'OZ_CLEAR_SITE_DATA_HEADER_ON_LOGOUT' => true,

	/**
	 * Value of Clear-Site-Data header on logout response.
	 *
	 * Do NOT send this header directly on a 3xx redirect response -- Chrome will freeze
	 * when "cache" or "storage" directives appear on a 302/303/307 response.
	 * OZone avoids this by responding with a 200 intermediate page that carries the header
	 * and redirects the client via <meta refresh> + JS.
	 *
	 * @see https://developer.mozilla.org/en-US/docs/Web/HTTP/Headers/Clear-Site-Data
	 * @see https://bugs.chromium.org/p/chromium/issues/detail?id=898503
	 */
	'OZ_CLEAR_SITE_DATA_HEADER_VALUE' => '"cache", "cookies", "storage"',
];
