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
	 * Cookie domain.
	 *
	 * @default self (current domain)
	 */
	'OZ_COOKIE_DOMAIN'      => 'self',

	/**
	 * Cookie path.
	 *
	 * @default self (current path)
	 */
	'OZ_COOKIE_PATH'        => 'self',

	/**
	 * Cookie lifetime in seconds.
	 *
	 * @default 86400 (1 day)
	 */
	'OZ_COOKIE_LIFETIME'    => 86400,

	/**
	 * Cookie same site.
	 *
	 * options: None, Lax or Strict
	 *
	 * @default Lax
	 */
	'OZ_COOKIE_SAMESITE'    => 'Lax',

	/**
	 * Cookie partitioned.
	 *
	 * @default false
	 */
	'OZ_COOKIE_PARTITIONED' => false,

	/**
	 * Cookie Secure flag.
	 *
	 * - 'auto': set when the request is https, as seen through trusted proxies (`oz.proxies`);
	 * - true: always (e.g. behind a TLS proxy that does not report the scheme);
	 * - false: never.
	 *
	 * @default 'auto'
	 */
	'OZ_COOKIE_SECURE'      => 'auto',
];
