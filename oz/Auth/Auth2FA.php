<?php

/**
 * Copyright (c) 2017-present, Emile Silas Sare
 *
 * This file is part of OZone package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace OZONE\Core\Auth;

use OZONE\Core\Auth\Events\AuthUserLoggedIn;
use OZONE\Core\Auth\Interfaces\AuthUserInterface;
use OZONE\Core\Auth\Interfaces\StatefulAuthMethodInterface;
use OZONE\Core\Exceptions\RuntimeException;
use OZONE\Core\Hooks\Interfaces\BootHookReceiverInterface;

/**
 * Class Auth2FA.
 */
final class Auth2FA implements BootHookReceiverInterface
{
	/**
	 * {@inheritDoc}
	 */
	public static function boot(): void
	{
		AuthUserLoggedIn::listen(static function (AuthUserLoggedIn $event) {
			self::check2FAAuthProcess($event->user, $event->context->requireStatefulAuth());
		});
	}

	/**
	 * Check a 2FA auth process after login.
	 *
	 * @param AuthUserInterface           $user
	 * @param StatefulAuthMethodInterface $auth_method
	 */
	private static function check2FAAuthProcess(AuthUserInterface $user, StatefulAuthMethodInterface $auth_method): void
	{
		if ($user->getAuthUserDataStore()->has2FAEnabled()) {
			// TODO
			// if user enable 2FA, we need to verify it first
			// before attaching the user to the session
			// To do that:
			// 1) we start a 2FA auth process
			// 2) we make sure to attach
			// 		- the 2FA process to the current auth method
			//		- and the auth method to the 2FA process
			// 3) after the 2FA process is done we can attach the user to the auth method
			throw new RuntimeException('User 2FA not yet implemented.', [
				'_auth_methode_state_id' => $auth_method->stateID(),
				'_user'                  => AuthUsers::selector($user),
			]);
		}
	}
}
