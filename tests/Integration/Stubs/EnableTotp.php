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

// Run inside a test project (`php enable_totp.php`): turns on two-factor authentication with an
// authenticator app for a user, as the project's settings page would, and prints the TOTP secret.

use OZONE\Core\Auth\TwoFactor\Channels\TotpChannel;
use OZONE\Core\Auth\TwoFactor\TOTP;
use OZONE\Core\Db\OZUsersQuery;
use OZONE\Core\OZone;

require __DIR__ . '/app/boot.php';

OZone::bootstrap(require __DIR__ . '/app/app.php');

$user = new OZUsersQuery()->whereEmailIs('__PLH_EMAIL__')->find()->fetchClass();

if (null === $user) {
	\fwrite(\STDERR, 'No user with this email.');

	exit(1);
}

$secret = TOTP::generateSecret();

$store = $user->getAuthUserDataStore();
$store->set2FAEnabled()
	->set2FAMethod(TotpChannel::getName())
	->set2FATotpSecret($secret);

$user->setAuthUserDataStore($store)->save();

echo $secret;
