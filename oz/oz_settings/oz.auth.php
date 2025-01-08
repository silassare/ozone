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

use OZONE\Core\Auth\AuthMethodType;

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
	 * Auth by api key header name.
	 */
	'OZ_AUTH_API_KEY_HEADER_NAME' => 'x-ozone-api-key',

	/**
	 * Default auth methods for API.
	 */
	'OZ_AUTH_API_AUTH_METHODS' => [
		AuthMethodType::BEARER,
		AuthMethodType::API_KEY_HEADER,
		AuthMethodType::SESSION,
	],
];
