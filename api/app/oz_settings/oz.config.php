<?php

/**
 * Copyright (c) 2017-present, Emile Silas Sare
 *
 * This file is part of OZone package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

return [
	'OZ_OZONE_VERSION'                => '3.0.0',
	'OZ_PROJECT_NAME'                 => 'Sample',
	'OZ_PROJECT_NAMESPACE'            => 'SAMPLE\\App',
	'OZ_PROJECT_CLASS'                => 'SampleApp',
	'OZ_PROJECT_PREFIX'               => 'SA',
	'OZ_DEBUG_MODE'                   => 0,
	'OZ_API_MAIN_URL'                 => 'http://localhost',
	'OZ_API_SESSION_ID_NAME'          => 'OZONE_SID',
	'OZ_API_KEY_HEADER_NAME'          => 'x-ozone-api-key',
	// = For server that does not support HEAD, PATCH, PUT, DELETE...,
	'OZ_API_ALLOW_REAL_METHOD_HEADER' => true,
	'OZ_API_REAL_METHOD_HEADER_NAME'  => 'x-ozone-real-method',
];
