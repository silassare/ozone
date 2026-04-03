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

namespace OZONE\Core\Auth;

use Override;
use OZONE\Core\App\Context;
use OZONE\Core\Auth\Events\AuthUserLoggedIn;
use OZONE\Core\Auth\Interfaces\AuthenticationMethodStatefulInterface;
use OZONE\Core\Auth\Interfaces\AuthUserInterface;
use OZONE\Core\Auth\Providers\TwoFactorAuthorizationProvider;
use OZONE\Core\Auth\TwoFactor\Channels\EmailOtpChannel;
use OZONE\Core\Auth\TwoFactor\Channels\SmsOtpChannel;
use OZONE\Core\Auth\TwoFactor\Channels\TotpChannel;
use OZONE\Core\Auth\TwoFactor\TwoFactorChannelInterface;
use OZONE\Core\Auth\TwoFactor\TwoFactorChannelRegistry;
use OZONE\Core\Db\OZAuth;
use OZONE\Core\Exceptions\UnauthorizedException;
use OZONE\Core\Hooks\Interfaces\BootHookReceiverInterface;

/**
 * Class Auth2FA.
 *
 * Listens for {@see AuthUserLoggedIn} events and intercepts the login when the user
 * has two-factor authentication enabled.
 *
 * Flow:
 *  1. {@see AuthUserLoggedIn} fires (user already attached to session by the auth method).
 *  2. This class detects 2FA is required, detaches the user, and saves a pending-user
 *     reference in the session store under 'oz.2fa_pending_user'.
 *  3. A {@see TwoFactorAuthorizationProvider} generates the OZAuth record and, for
 *     email/SMS channels, delivers the OTP code.
 *  4. The client submits the code to POST /auth/:ref/authorize.
 *  5. {@see TwoFactorAuthorizationProvider::onAuthorized()} re-attaches the user,
 *     sets 'oz.2fa.verified' in the session store, and re-dispatches AuthUserLoggedIn.
 *  6. On the second dispatch the 'oz.2fa.verified' guard in step 7 is consumed and
 *     the login proceeds normally.
 */
final class Auth2FA implements BootHookReceiverInterface
{
	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public static function boot(): void
	{
		// Register the three built-in channels.
		// Custom channels can be added here or in a BootHookReceiverInterface
		// that runs after this one:
		//   TwoFactorChannelRegistry::register(new MyCustomChannel());
		TwoFactorChannelRegistry::register(new TotpChannel());
		TwoFactorChannelRegistry::register(new EmailOtpChannel());
		TwoFactorChannelRegistry::register(new SmsOtpChannel());

		AuthUserLoggedIn::listen(static function (AuthUserLoggedIn $event) {
			// 2FA requires stateful auth (session). Skip silently for stateless methods.
			if (!$event->context->hasStatefulAuth()) {
				return;
			}

			self::check2FAAuthProcess(
				$event->context,
				$event->user,
				$event->context->requireStatefulAuth()
			);
		});
	}

	/**
	 * Registers a custom 2FA channel.
	 *
	 * This is a convenience wrapper around {@see TwoFactorChannelRegistry::register()}.
	 * It is intended to be called from a BootHookReceiverInterface::boot() method.
	 *
	 * @param TwoFactorChannelInterface $channel
	 */
	public static function registerChannel(TwoFactorChannelInterface $channel): void
	{
		TwoFactorChannelRegistry::register($channel);
	}

	/**
	 * Intercepts a login event and starts the 2FA flow when the user has it enabled.
	 *
	 * @param Context                               $context
	 * @param AuthUserInterface                     $user
	 * @param AuthenticationMethodStatefulInterface $auth_method
	 *
	 * @throws UnauthorizedException when 2FA is required and the flow is started
	 */
	private static function check2FAAuthProcess(
		Context $context,
		AuthUserInterface $user,
		AuthenticationMethodStatefulInterface $auth_method
	): void {
		$store = $auth_method->store();

		// Guard: the 'oz.2fa.verified' flag is set by TwoFactorAuthorizationProvider::onAuthorized()
		// just before it re-dispatches AuthUserLoggedIn.  Consuming it here allows the second
		// dispatch to proceed without triggering another 2FA challenge.
		if ($store->get('oz.2fa.verified')) {
			$store->remove('oz.2fa.verified');

			return;
		}

		if (!$user->getAuthUserDataStore()->has2FAEnabled()) {
			return;
		}

		// Select the appropriate delivery channel for this user.
		$channel = TwoFactorChannelRegistry::selectFor($user);

		// Undo the session attachment made by the login flow — we are not done yet.
		$auth_method->detachAuthUser();

		// Save a reference to the pending user so TwoFactorAuthorizationProvider
		// can recover and re-attach them after the code is verified.
		$store->set2FAPendingUser($user);

		// Build the provider and start the authorization flow.
		$provider = new TwoFactorAuthorizationProvider(
			$context,
			$user,
			$channel::getName(),
			$auth_method->stateID()
		);

		$provider->generate($user);

		// The JSON response now contains ref + refresh_key + channel + first (for otp).
		$data = $provider->getJSONResponse()->getData();

		// Throw an UnauthorizedException that signals "2FA required" to the client.
		// The client should redirect the user to a 2FA input screen and POST to
		// /auth/{ref}/authorize with the code.
		throw new UnauthorizedException('OZ_2FA_REQUIRED', [
			'two_fa_required'       => true,
			'channel'               => $channel::getName(),
			OZAuth::COL_REF         => $data[OZAuth::COL_REF] ?? null,
			OZAuth::COL_REFRESH_KEY => $data[OZAuth::COL_REFRESH_KEY] ?? null,
		]);
	}
}
