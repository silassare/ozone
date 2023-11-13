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
	'OZ_COOKIE_DOMAIN'   => 'self',

	/**
	 * Cookie path.
	 *
	 * @default self (current path)
	 */
	'OZ_COOKIE_PATH'     => 'self',

	/**
	 * Cookie lifetime in seconds.
	 *
	 * @default 86400 (1 day)
	 */
	'OZ_COOKIE_LIFETIME' => 86400,

	/**
	 * Cookie same site.
	 *
	 * options: None, Lax or Strict
	 *
	 * @default Lax
	 */
	'OZ_COOKIE_SAMESITE' => 'Lax',
];
