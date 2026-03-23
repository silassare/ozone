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
	 * Minimum age at december 31 of the current year.
	 * This is used to compute the minimum birth date allowed for a user.
	 */
	'OZ_USER_MIN_AGE' => 12,

	/**
	 * Maximum age at january 1 of the current year.
	 * This is used to compute the maximum birth date allowed for a user.
	 */
	'OZ_USER_MAX_AGE' => 100,

	/**
	 * Minimum length for user password.
	 */
	'OZ_USER_PASS_MIN_LENGTH' => 6,
	/**
	 * Maximum length for user password.
	 */
	'OZ_USER_PASS_MAX_LENGTH' => 60,

	/**
	 * Minimum length for user name.
	 */
	'OZ_USER_NAME_MIN_LENGTH' => 3,
	/**
	 * Maximum length for user name.
	 */
	'OZ_USER_NAME_MAX_LENGTH' => 60,

	/**
	 * Regex pattern for validating user name.
	 *
	 * Technically we could accept any character in the username
	 * but it is better to restrict it to a reasonable set of characters
	 * to avoid issues with encoding and to prevent users
	 * from using invisible characters in their usernames.
	 *
	 * If you prefer to allow more characters, you can change this pattern in your configuration.
	 * The default pattern allows letters, numbers, dots, and underscores.
	 */
	'OZ_USER_NAME_PATTERN' => '~^[a-zA-Z_][a-zA-Z0-9._]+$~',

	/**
	 * Minimum size (width and height) for user profile pictures.
	 * The size is in pixels.
	 */
	'OZ_USER_PIC_MIN_SIZE'    => 150,

	/**
	 * Allowed gender list.
	 *
	 * 'None' means the user does not have a gender or is neutral
	 * 'Other' means the user has a gender but not in the list
	 */
	'OZ_USER_ALLOWED_GENDERS' => [
		'Male',
		'Female',
		'None',
		'Other',
	],

	/**
	 * Should we require email for new auth user registration?
	 *
	 * @default true
	 */
	'OZ_USER_EMAIL_REQUIRED'  => true,

	/**
	 * Should we require phone number for new auth user registration?
	 *
	 * @default false
	 */
	'OZ_USER_PHONE_REQUIRED'  => false,

	/**
	 * Should we require username for new auth user registration?
	 *
	 * @default false
	 */
	'OZ_USER_USERNAME_REQUIRED'  => false,
];
