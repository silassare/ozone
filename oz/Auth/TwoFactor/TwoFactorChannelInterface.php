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

namespace OZONE\Core\Auth\TwoFactor;

use OZONE\Core\Auth\Interfaces\AuthUserInterface;

/**
 * Interface TwoFactorChannelInterface.
 *
 * Implement this interface to add a custom 2FA delivery channel.
 * Register it with {@see TwoFactorChannelRegistry::register()}.
 */
interface TwoFactorChannelInterface
{
	/**
	 * Returns the unique channel name (e.g. 'email', 'sms', 'totp').
	 *
	 * The name is stored in the OZAuth payload and used to route
	 * the authorization provider to the correct send/verify logic.
	 *
	 * @return string
	 */
	public static function getName(): string;

	/**
	 * Returns true when this channel can be used for the given user.
	 *
	 * Called during channel auto-selection to skip channels that are not
	 * set up for the user (e.g. no email address, no TOTP secret).
	 *
	 * @param AuthUserInterface $user
	 *
	 * @return bool
	 */
	public function isAvailableFor(AuthUserInterface $user): bool;
}
