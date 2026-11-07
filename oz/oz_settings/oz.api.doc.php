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

	/**
	 * The policy the API doc view sends, in place of the project's own.
	 *
	 * This page is the one OZone serves that this project's files do not render: ReDoc comes from
	 * a CDN, boots from an inline script, draws through a blob worker and takes its fonts from
	 * Google. So it is exempted and says so, rather than given a policy guessed at for code that
	 * is not ours and that no test here renders. A project serving ReDoc from its own files
	 * tightens this to `"default-src 'self'"`; null lets `OZ_CONTENT_SECURITY_POLICY` apply.
	 *
	 * Sent whether or not the project has a policy of its own, and it restricts nothing
	 * `X-Frame-Options` does not already: the point of it is that switching a policy on cannot
	 * break the one page here that third-party code renders.
	 */
	'OZ_API_DOC_CSP'           => "default-src * data: blob: 'unsafe-inline' 'unsafe-eval';"
		. " frame-ancestors 'self'",
];
