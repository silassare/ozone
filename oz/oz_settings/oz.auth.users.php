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

use OZONE\Core\Auth\AuthUsersRepository;
use OZONE\Core\Auth\Picture\Interfaces\AuthUserPictureFallbackInterface;
use OZONE\Core\Auth\Picture\SelfOrAdminPictureAccess;
use OZONE\Core\Auth\Views\AuthUserPictureView;

/*
 * The rules of every auth user table made by `AuthUsersRepository::makeAuthUserTable()`, whatever
 * its type: `oz_users` (type `user`) is the default one, and a project's own tables follow the same.
 */
return [
	/**
	 * Whether OZone's default auth user table, `oz_users` (type `user`), exists. `false` leaves it out
	 * of the schema and its type out of `oz.auth.users.repositories`, for a project whose auth users
	 * are all of its own tables; `OZ_AUTH_USER_DEFAULT_TYPE` then names one of them. Turned off on a
	 * database holding the table, the next migration drops it, as it shows.
	 */
	'OZ_AUTH_USER_DEFAULT_TABLE' => true,

	/**
	 * The auth user type used where none is named: sign-up, `oz auth-users`, the email and phone
	 * columns' "already registered" check, a verification that names no type. A type a repository of
	 * `oz.auth.users.repositories` serves, with a table made by `makeAuthUserTable()`; anything else
	 * is refused at boot.
	 *
	 * @client the type a sign-in or recovery form asks for when its definition names none
	 */
	'OZ_AUTH_USER_DEFAULT_TYPE' => AuthUsersRepository::DEFAULT_AUTH_USER_TYPE,

	/**
	 * Minimum age at december 31 of the current year.
	 * This is used to compute the minimum birth date allowed for an auth user.
	 */
	'OZ_AUTH_USER_MIN_AGE' => 12,

	/**
	 * Maximum age at january 1 of the current year.
	 * This is used to compute the maximum birth date allowed for an auth user.
	 */
	'OZ_AUTH_USER_MAX_AGE' => 100,

	/**
	 * Minimum length for an auth user's password.
	 */
	'OZ_AUTH_USER_PASS_MIN_LENGTH' => 6,
	/**
	 * Maximum length for an auth user's password.
	 */
	'OZ_AUTH_USER_PASS_MAX_LENGTH' => 60,

	/**
	 * Minimum length for an auth user's username.
	 */
	'OZ_AUTH_USER_NAME_MIN_LENGTH' => 3,
	/**
	 * Maximum length for an auth user's username.
	 */
	'OZ_AUTH_USER_NAME_MAX_LENGTH' => 60,

	/**
	 * Regex pattern for validating an auth user's username.
	 *
	 * Technically we could accept any character in the username
	 * but it is better to restrict it to a reasonable set of characters
	 * to avoid issues with encoding and to prevent users
	 * from using invisible characters in their usernames.
	 *
	 * If you prefer to allow more characters, you can change this pattern in your configuration.
	 * The default pattern allows letters, numbers, dots, and underscores.
	 */
	'OZ_AUTH_USER_NAME_PATTERN' => '~^[a-zA-Z_][a-zA-Z0-9._]+$~',

	/**
	 * Minimum size (width and height) for auth user pictures.
	 * The size is in pixels.
	 */
	'OZ_AUTH_USER_PIC_MIN_SIZE'    => 150,

	/**
	 * Who may see an auth user's picture (`oz:auth-user-picture`,
	 * `/auth-users/{type}/{id}/picture[/{tokens}]`): a class implementing
	 * `OZONE\Core\Auth\Picture\Interfaces\AuthUserPictureAccessInterface`. Shipped:
	 * `SelfOrAdminPictureAccess` (the auth user themself and administrators), `SignedInPictureAccess`
	 * (anyone signed in) and `PublicPictureAccess` (anyone). Anyone else is refused (403).
	 *
	 * @see AuthUserPictureView
	 */
	'OZ_AUTH_USER_PICTURE_ACCESS'    => SelfOrAdminPictureAccess::class,

	/**
	 * Image tokens applied to every auth user picture before the ones its address asks for, so a project
	 * shapes them all alike whatever a link asks: a square crop, a size cap, a watermark token
	 * (`['thumb256']`). The tokens asked for are rendered on what these gave.
	 *
	 * @see AuthUserPictureView
	 */
	'OZ_AUTH_USER_PICTURE_TOKENS'    => [],

	/**
	 * What an auth user picture's address answers in place of a picture: one value for both cases,
	 * or `['refused' => ..., 'missing' => ...]` -- `refused` for a viewer the access rule refuses (and
	 * a type and id no auth user has), `missing` for an allowed viewer asking for an auth user with no
	 * picture. Each is:
	 *
	 * - `null`: the status, 403 or 404 (404 only ever to an allowed viewer);
	 * - an image's path, from the project's root (`app/files/avatar.png`): answered with 200, rendered
	 *   as a picture is, the forced tokens and the ones asked for included;
	 * - a class implementing `AuthUserPictureFallbackInterface`, which draws the image or answers null
	 *   for the status.
	 *
	 * An image for `refused` says nothing of whether the auth user has a picture, which is why one is
	 * chosen.
	 *
	 * @see AuthUserPictureFallbackInterface
	 */
	'OZ_AUTH_USER_PICTURE_FALLBACK'  => null,

	/**
	 * The auth user types a visitor may sign up as (`oz:signup`, its `auth_user_type` field), each
	 * with a table made by `makeAuthUserTable()`. Only `user` by default: sign-up is public, and a
	 * type listed here is one anybody may create an account of, so a project's `admins` or `staff`
	 * type is never in it unless the project puts it there.
	 */
	'OZ_AUTH_USER_SIGN_UP_TYPES'   => [AuthUsersRepository::DEFAULT_AUTH_USER_TYPE],

	/**
	 * Allowed gender list.
	 *
	 * 'None' means the user does not have a gender or is neutral
	 * 'Other' means the user has a gender but not in the list
	 */
	'OZ_AUTH_USER_ALLOWED_GENDERS' => [
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
	'OZ_AUTH_USER_EMAIL_REQUIRED'  => true,

	/**
	 * Should we require phone number for new auth user registration?
	 *
	 * @default false
	 */
	'OZ_AUTH_USER_PHONE_REQUIRED'  => false,

	/**
	 * Should we require username for new auth user registration?
	 *
	 * @default false
	 */
	'OZ_AUTH_USER_USERNAME_REQUIRED'  => false,
];
