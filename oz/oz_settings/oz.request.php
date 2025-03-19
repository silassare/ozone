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
	'OZ_CORS_ALLOWED_HEADERS'     => ['accept', 'content-type'],

	// allowed origin:
	// - '*' for any
	// - 'http://example.com' for a specific host
	// - 'self' for the request url host
	'OZ_CORS_ALLOWED_ORIGIN'      => '*',
	// this is a integer value in seconds used for
	// Access-Control-Max-Age header
	// default is 86400 seconds (24 hours)
	'OZ_CORS_ALLOWED_MAX_AGE'      => 86400,
	'OZ_DEFAULT_ORIGIN'            => 'http://localhost',
	// = For server that does not support HEAD, PATCH, PUT, DELETE...,
	'OZ_ALLOW_REAL_METHOD_HEADER' => true,
	'OZ_REAL_METHOD_HEADER_NAME'  => 'x-ozone-real-method',
];
