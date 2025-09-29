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
	'OZ_USER_MIN_AGE' => 12,
	'OZ_USER_MAX_AGE' => 100,

	'OZ_USER_PASS_MIN_LENGTH' => 6,
	'OZ_USER_PASS_MAX_LENGTH' => 60,

	'OZ_USER_NAME_MIN_LENGTH' => 3,
	'OZ_USER_NAME_MAX_LENGTH' => 60,

	// minimum crop size of a user pic: in pixels
	'OZ_USER_PIC_MIN_SIZE'    => 150,
	// allowed gender list
	'OZ_USER_ALLOWED_GENDERS' => [
		'Male',
		'Female',
		'None', // does not have a gender or is neutral
		'Other', // have a gender but not in the list
	],
	// does email are required to register
	'OZ_USER_EMAIL_REQUIRED'  => true,
	// does phone number are required to register
	'OZ_USER_PHONE_REQUIRED'  => false,
];
