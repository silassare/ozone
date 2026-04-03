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
 * Class SmsOtpChannel.
 *
 * Delivers the 2FA one-time code via SMS.
 * Available when the user has a non-empty phone number.
 */
final class SmsOtpChannel implements TwoFactorChannelInterface
{
	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public static function getName(): string
	{
		return 'sms';
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function isAvailableFor(AuthUserInterface $user): bool
	{
		$phone = $user->getAuthIdentifiers()[AuthUserInterface::IDENTIFIER_TYPE_PHONE] ?? null;

		return !empty($phone);
	}
}
