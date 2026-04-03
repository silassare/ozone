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

// Test-specific 2FA settings.
// Priority order matters for TwoFactorChannelRegistryTest::selectFor tests.
return [
	'OZ_2FA_CHANNEL_PRIORITY' => ['totp', 'email', 'sms'],
	'OZ_2FA_TOTP_ISSUER'      => 'TestApp',
	'OZ_2FA_TOTP_STEP'        => 30,
	'OZ_2FA_TOTP_WINDOW'      => 1,
	'OZ_2FA_TOTP_DIGITS'      => 6,
	'OZ_2FA_CODE_LIFE_TIME'   => 300,
	'OZ_2FA_CODE_TRY_MAX'     => 3,
];
