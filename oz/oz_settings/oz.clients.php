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
	'OZ_CORS_ALLOWED_HEADERS' => ['accept', 'content-type'],

	// allowed origin:
	// - '*' for any
	// - 'http://example.com' for a specific host
	// - 'self' for the request url host
	'OZ_CORS_ALLOWED_ORIGIN'  => '*',
];
