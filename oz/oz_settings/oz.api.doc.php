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
	 * Enable API documentation generation and endpoints.
	 */
	'OZ_API_DOC_ENABLED'       => true,

	/**
	 * Redirect the root API URL to the API doc view when OZ_API_DOC_ENABLED is true.
	 */
	'OZ_API_DOC_SHOW_ON_INDEX' => true,

	/**
	 * Make the API documentation endpoints publicly accessible.
	 *
	 * When false (default), the spec and view endpoints require admin role,
	 * which prevents exposing auth schemes and security metadata to anonymous users.
	 * Set to true only for fully public APIs or development environments.
	 */
	'OZ_API_DOC_PUBLIC'        => false,
];
