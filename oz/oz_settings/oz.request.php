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

use OZONE\Core\App\Settings;

return [
	/**
	 * How each key of this group takes what a later source says (`Settings::MERGE_KEY`).
	 */
	Settings::MERGE_KEY => [
		// Every source adds the headers its own routes need, rather than restating the others'.
		'OZ_CORS_ALLOWED_HEADERS' => Settings::MERGE_APPEND,
	],

	/**
	 * Allowed CORS headers.
	 *
	 * Appended across sources: a plugin adds what it needs without dropping what another declared. A
	 * project that must take one away restates the whole list with the `replace` strategy, or locks it.
	 */
	'OZ_CORS_ALLOWED_HEADERS'     => ['accept', 'content-type'],

	/**
	 * Origins allowed to read responses cross-origin (with cookies).
	 *
	 * - 'self': the app origin (`OZ_DEFAULT_ORIGIN`);
	 * - 'https://app.example.com': one origin;
	 * - ['self', 'https://app.example.com', 'http://localhost:3000']: a list;
	 * - '*': any origin, but WITHOUT credentials (no cookies), for public APIs.
	 *
	 * Origins are compared on scheme, host and port. The request's own origin is
	 * always allowed. A request from a disallowed origin is rejected with
	 * `OZ_CROSS_SITE_REQUEST_NOT_ALLOWED`.
	 *
	 * @default 'self'
	 */
	'OZ_CORS_ALLOWED_ORIGIN'      => 'self',

	/**
	 * Access-Control-Max-Age header value.
	 *
	 * @default 86400 (24 hours)
	 */
	'OZ_CORS_ALLOWED_MAX_AGE'      => 86400,

	/**
	 * Security headers added to every response, unless the handler already set them.
	 *
	 * Set a header to null to not send it. `Strict-Transport-Security` is only sent over https.
	 * Override per scope (`scopes/{name}/settings/oz.request.php`), e.g. a CSP for a web scope.
	 */
	'OZ_SECURITY_HEADERS'          => [
		'X-Frame-Options'           => 'SAMEORIGIN',
		'X-Content-Type-Options'    => 'nosniff',
		'Referrer-Policy'           => 'strict-origin-when-cross-origin',
		// e.g. 'max-age=31536000; includeSubDomains'
		'Strict-Transport-Security' => null,
		// e.g. "default-src 'self'"
		'Content-Security-Policy'   => null,
	],

	/**
	 * Default origin to use when the request does not have an origin header.
	 *
	 * @default 'http://localhost'
	 */
	'OZ_DEFAULT_ORIGIN'            => 'http://localhost',

	/**
	 * Hosts, besides the current one, that user-supplied redirect targets (e.g. the
	 * `next` parameter of `/logout`) may point to. Anything else is rejected, so these
	 * parameters cannot be used as open redirects.
	 *
	 * @default []
	 */
	'OZ_REDIRECT_ALLOWED_HOSTS'    => [],

	/**
	 * How long, in seconds, a CSRF token is accepted after it was issued.
	 *
	 * @default 86400 (24 hours)
	 */
	'OZ_CSRF_TOKEN_LIFETIME'       => 86400,

	/**
	 * Require a CSRF token (`_csrf` field or `X-XSRF-TOKEN` header) on unsafe requests
	 * (POST, PUT, PATCH, DELETE) authenticated by the session cookie. Routes and groups opt out
	 * with `->withoutCSRF()`; bearer and API-key requests, and requests without the session
	 * cookie, are never checked.
	 *
	 * @default true
	 */
	'OZ_CSRF_SESSION_DEFAULT'      => true,

	/**
	 * Cookie handing the session CSRF token to scripts (readable by JavaScript): axios and
	 * Angular read `XSRF-TOKEN` and send it back as `X-XSRF-TOKEN` on their own. Set to null to
	 * disable it; pages can always use the `csrf_token` template global.
	 *
	 * @default 'XSRF-TOKEN'
	 */
	'OZ_CSRF_COOKIE_NAME'          => 'XSRF-TOKEN',

	/**
	 * Header in which a trusted proxy gives the client IP directly: 'CF-Connecting-IP'
	 * behind Cloudflare, 'True-Client-IP' (Akamai, Cloudflare Enterprise), 'X-Real-IP' (nginx).
	 *
	 * Only read when the TCP peer is listed in `oz.proxies`, so list the provider's IP ranges
	 * there too. When the header is missing or invalid, the client IP is resolved from
	 * `Forwarded` / `X-Forwarded-For`. The value is taken as-is: only set this when every
	 * request reaching a trusted proxy went through the provider that sets the header,
	 * otherwise a client can choose its own IP.
	 *
	 * @default null (disabled)
	 */
	'OZ_CLIENT_IP_HEADER'          => null,

	/**
	 * Allow to use X-OZONE-Real-Method (like X-HTTP-Method-Override) header to simulate other HTTP methods.
	 *
	 * For server that does not support HEAD, PATCH, PUT, DELETE...,
	 *
	 * @example: use X-OZONE-Real-Method: DELETE to simulate a DELETE request while sending a POST request.
	 */
	'OZ_REAL_METHOD_HEADER_ALLOWED' => true,

	/**
	 * Name of the header to use to override HTTP method.
	 *
	 * @default 'X-OZONE-Real-Method'
	 */
	'OZ_REAL_METHOD_HEADER_NAME'  => 'X-OZONE-Real-Method',

	/**
	 * Allow to use form discovery header to indicate that the request is a form discovery request.
	 *
	 * This is useful for clients that want to discover the form structure before submitting it.
	 */
	'OZ_FORM_DISCOVERY_HEADER_ALLOWED' => true,

	/**
	 * Name of the header to use for form discovery.
	 *
	 * This header is used by the client to indicate that the request is a form discovery request.
	 *
	 * Require RFC 8941 representation of boolean values. ?1 is true, ?0 is false.
	 */
	'OZ_FORM_DISCOVERY_HEADER_NAME' => 'X-OZONE-Form-Discovery',

	/**
	 * Name of the header to indicate that we want to bypass route handling and be in resume mode for a form.
	 *
	 * This header is not necessary at the end of the form resume flow, but it is necessary for the intermediate steps.
	 *
	 * Require RFC 8941 representation of boolean values. ?1 is true, ?0 is false.
	 */
	'OZ_FORM_RESUME_HEADER_NAME' => 'X-OZONE-Form-Resume',

	/**
	 * Name of the header to use for resumed form session reference.
	 *
	 * This header is used by the client to indicate that the request should use payload from a resumed form reference.
	 * The value of the header should be the resume reference provided by the server in a previous response.
	 */
	'OZ_FORM_RESUME_REF_HEADER_NAME' => 'X-OZONE-Form-Resume-Ref',

	/**
	 * Name of the header that carries the form resume action.
	 *
	 * Valid values: init, state, next, back, cancel, evaluate.
	 * When absent the action defaults to "init".
	 */
	'OZ_FORM_RESUME_ACTION_HEADER_NAME' => 'X-OZONE-Form-Resume-Action',
];
