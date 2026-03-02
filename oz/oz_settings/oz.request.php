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
	 * Allowed CORS headers.
	 */
	'OZ_CORS_ALLOWED_HEADERS'     => ['accept', 'content-type'],

	/**
	 * Allowed CORS origin.
	 *
	 * - 'self' for the request url host
	 * - 'http://example.com' for a specific host
	 * - '*' for any
	 *
	 * @default '*'
	 */
	'OZ_CORS_ALLOWED_ORIGIN'      => '*',

	/**
	 * Access-Control-Max-Age header value.
	 *
	 * @default 86400 (24 hours)
	 */
	'OZ_CORS_ALLOWED_MAX_AGE'      => 86400,

	/**
	 * Default origin to use when the request does not have an origin header.
	 *
	 * @default 'http://localhost'
	 */
	'OZ_DEFAULT_ORIGIN'            => 'http://localhost',

	/**
	 * Allow to use X-OZONE-Real-Method (like X-HTTP-Method-Override) header to simulate other HTTP methods.
	 *
	 * For server that does not support HEAD, PATCH, PUT, DELETE...,
	 *
	 * @example: use X-OZONE-Real-Method: DELETE to simulate a DELETE request while sending a POST request.
	 */
	'OZ_ALLOW_REAL_METHOD_HEADER' => true,

	/**
	 * Name of the header to use to override HTTP method.
	 *
	 * @default 'X-OZONE-Real-Method'
	 */
	'OZ_REAL_METHOD_HEADER_NAME'  => 'X-OZONE-Real-Method',
];
