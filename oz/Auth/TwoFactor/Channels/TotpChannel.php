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

namespace OZONE\Core\Auth\TwoFactor\Channels;

use Override;
use OZONE\Core\Auth\Interfaces\AuthUserInterface;
use OZONE\Core\Auth\TwoFactor\TwoFactorChannelInterface;

/**
 * Class TotpChannel.
 *
 * Verifies the 2FA code locally via TOTP (RFC 6238) using the user's stored TOTP secret.
 * Available when the user has a non-empty TOTP secret in their data store.
 * No code is delivered — the user reads it from their authenticator app.
 */
final class TotpChannel implements TwoFactorChannelInterface
{
	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public static function getName(): string
	{
		return 'totp';
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function isAvailableFor(AuthUserInterface $user): bool
	{
		return !empty($user->getAuthUserDataStore()->get2FATotpSecret());
	}
}
