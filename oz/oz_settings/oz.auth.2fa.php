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

use OZONE\Core\Auth\TwoFactor\TwoFactorChannelInterface;

return [
	/**
	 * Channel selection priority when the user has no preferred 2FA method.
	 *
	 * The first channel whose {@see TwoFactorChannelInterface::isAvailableFor()}
	 * returns true for the user will be used.
	 *
	 * Override this in app/settings/oz.auth.2fa.php to change the order or
	 * restrict which channels are allowed.
	 *
	 * @default ['totp', 'email', 'sms']
	 */
	'OZ_2FA_CHANNEL_PRIORITY' => ['totp', 'email', 'sms'],

	/**
	 * Issuer name embedded in the otpauth:// URI shown to the user during TOTP setup.
	 *
	 * This name appears in authenticator apps (e.g. "MyApp (user@example.com)").
	 *
	 * @default 'OZone'
	 */
	'OZ_2FA_TOTP_ISSUER'      => 'OZone',

	/**
	 * TOTP time step in seconds (standard RFC 6238 value).
	 *
	 * @default 30
	 */
	'OZ_2FA_TOTP_STEP'        => 30,

	/**
	 * Number of time steps to check on each side of the current step.
	 *
	 * A value of 1 tolerates up to ±30 seconds of clock deviation.
	 *
	 * @default 1
	 */
	'OZ_2FA_TOTP_WINDOW'      => 1,

	/**
	 * Number of digits in the TOTP code (must match the authenticator app configuration).
	 *
	 * @default 6
	 */
	'OZ_2FA_TOTP_DIGITS'      => 6,

	/**
	 * 2FA OTP code lifetime in seconds.
	 *
	 * Applies to email/SMS channels.  The OZAuth record expires after this period.
	 *
	 * @default 300 (5 minutes)
	 */
	'OZ_2FA_CODE_LIFE_TIME'   => 300,

	/**
	 * Maximum number of verification attempts before the 2FA flow is rejected.
	 *
	 * @default 3
	 */
	'OZ_2FA_CODE_TRY_MAX'     => 3,
];
