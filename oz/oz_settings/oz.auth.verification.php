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

use OZONE\Core\Auth\Providers\EmailOwnershipVerificationProvider;
use OZONE\Core\Auth\Providers\PhoneOwnershipVerificationProvider;
use OZONE\Core\Auth\VerificationPolicy;

/**
 * What a user must prove before signing up, or before recovering an account.
 *
 * Each entry maps a user type (the keys of `oz.auth.users.repositories`) to the authorization
 * providers that are accepted for it, by name. `VerificationPolicy::ANY_USER_TYPE` is the entry every
 * type falls back to, so a project that has one rule writes it once.
 *
 * An **empty list** for a type means nothing has to be proven: the identifier is taken from the form
 * as given, and it is the project's business to verify it later. Nothing else is turned off by that:
 * a user can still be asked to verify an address afterwards (`/auth/verify/email`), and two-factor
 * authentication is unaffected.
 *
 * A project registers its own provider in `oz.auth.providers` and names it here, which is how a
 * verification that is neither an email nor a phone (an invitation, an identity check, a third party)
 * takes part.
 */
return [
	/**
	 * What `POST /signup` accepts, per user type.
	 *
	 * @default email or phone ownership, for every type
	 */
	'OZ_SIGNUP_VERIFICATION' => [
		VerificationPolicy::ANY_USER_TYPE => [
			EmailOwnershipVerificationProvider::NAME,
			PhoneOwnershipVerificationProvider::NAME,
		],
	],

	/**
	 * What `POST /account-recovery` accepts, per user type.
	 *
	 * Leaving a type with no provider here lets anybody set a new password for an account of that
	 * type, so an empty list is only ever right when the route is not served or another guard stands
	 * in front of it.
	 *
	 * @default email or phone ownership, for every type
	 */
	'OZ_ACCOUNT_RECOVERY_VERIFICATION' => [
		VerificationPolicy::ANY_USER_TYPE => [
			EmailOwnershipVerificationProvider::NAME,
			PhoneOwnershipVerificationProvider::NAME,
		],
	],
];
