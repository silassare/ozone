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
	'OZ_AUTH_CODE_TRY_MAX'        => 3,
	'OZ_AUTH_CODE_LIFE_TIME'      => 60 * 60,
	'OZ_AUTH_CODE_LENGTH'         => 6,
	'OZ_AUTH_CODE_USE_ALPHA_NUM'  => false,
	'OZ_AUTH_API_KEY_HEADER_NAME' => 'x-ozone-api-key',

	/**
	 * Default auth methods for API.
	 */
	'OZ_AUTH_API_AUTH_METHODS'    => [
		AuthMethodType::BEARER,
		AuthMethodType::SESSION,
		AuthMethodType::API_KEY_HEADER,
	],
];
