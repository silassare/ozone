<?php
	/**
	 * Copyright (c) 2017-present, Emile Silas Sare
	 *
	 * This file is part of OZone (O'Zone) package.
	 *
	 * For the full copyright and license information, please view the LICENSE
	 * file that was distributed with this source code.
	 */

	defined('OZ_SELF_SECURITY_CHECK') or die;

	return [
		// default picid if there is none
		'OZ_DEFAULT_PICID'        => '0_0',
		// maximum crop size of a profile image: in pixels
		'OZ_PPIC_MIN_SIZE'        => 150,
		// maximum size of a thumbnail: in pixels
		'OZ_THUMB_MAX_SIZE'       => 640,
		// allowed gender list
		'OZ_USER_ALLOWED_GENDERS' => ['Male', 'Female', 'None', 'Other'],
		// does email are required to register
		'OZ_USERS_EMAIL_REQUIRED' => true,
		// does phone number are required to register
		'OZ_USERS_PHONE_REQUIRED' => false
	];