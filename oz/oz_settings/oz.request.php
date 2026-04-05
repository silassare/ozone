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
	'OZ_REAL_METHOD_HEADER_ALLOWED' => true,

	/**
	 * Name of the header to use to override HTTP method.
	 *
	 * @default 'X-OZONE-Real-Method'
	 */
	'OZ_REAL_METHOD_HEADER_NAME'  => 'X-OZONE-Real-Method',

	/**
	 * Allow to use X-OZONE-Form-Discovery header to indicate that the request is a form discovery request.
	 *
	 * This is useful for clients that want to discover the form structure before submitting it.
	 */
	'OZ_FORM_DISCOVERY_HEADER_ALLOWED' => true,

	/**
	 * Name of the header to use for form discovery.
	 *
	 * This header is used by the client to indicate that the request is a form discovery request.
	 */
	'OZ_FORM_DISCOVERY_HEADER_NAME' => 'X-OZONE-Form-Discovery',

	/**
	 * Name of the header to use for form resumable form reference.
	 *
	 * This header is used by the client to indicate that the request should use payload from a resumable form reference.
	 */
	'OZ_FORM_RESUMABLE_REF_HEADER_NAME' => 'X-OZONE-Form-Resumable-Ref',
];
