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

use OZONE\Core\App\Settings;
use OZONE\Core\Auth\AuthUserDataStore;
use OZONE\Core\Auth\Interfaces\AuthUserInterface;
use OZONE\Core\Exceptions\RuntimeException;

/**
 * Class TwoFactorChannelRegistry.
 *
 * Holds all registered 2FA channels and selects the appropriate one for a user.
 * Channels are keyed by their name and tried in the priority order defined in
 * the oz.auth.2fa settings group.
 *
 * Developers can add custom channels via {@see TwoFactorChannelRegistry::register()}.
 */
final class TwoFactorChannelRegistry
{
	/** @var array<string, TwoFactorChannelInterface> */
	private static array $channels = [];

	/**
	 * Registers a 2FA channel.
	 *
	 * Replaces any existing channel with the same name.
	 *
	 * @param TwoFactorChannelInterface $channel
	 */
	public static function register(TwoFactorChannelInterface $channel): void
	{
		self::$channels[$channel::getName()] = $channel;
	}

	/**
	 * Returns all registered channels keyed by name.
	 *
	 * @return array<string, TwoFactorChannelInterface>
	 */
	public static function all(): array
	{
		return self::$channels;
	}

	/**
	 * Returns the channel registered under the given name, or null if not found.
	 *
	 * @param string $name
	 *
	 * @return null|TwoFactorChannelInterface
	 */
	public static function get(string $name): ?TwoFactorChannelInterface
	{
		return self::$channels[$name] ?? null;
	}

	/**
	 * Selects the best available 2FA channel for the given user.
	 *
	 * Selection order:
	 *  1. User's preferred method (from {@see AuthUserDataStore::get2FAMethod()})
	 *     if the channel is registered and available for the user.
	 *  2. Channels tried in the priority order from the oz.auth.2fa settings group
	 *     (OZ_2FA_CHANNEL_PRIORITY) until one is available.
	 *
	 * @param AuthUserInterface $user
	 *
	 * @return TwoFactorChannelInterface
	 *
	 * @throws RuntimeException when no channel is available for the user
	 */
	public static function selectFor(AuthUserInterface $user): TwoFactorChannelInterface
	{
		$preferred = $user->getAuthUserDataStore()->get2FAMethod();

		// Try user's preferred channel first.
		if (null !== $preferred) {
			$channel = self::$channels[$preferred] ?? null;
			if ($channel && $channel->isAvailableFor($user)) {
				return $channel;
			}
		}

		// Fall back to OZ_2FA_CHANNEL_PRIORITY order.
		/** @var string[] $priority */
		$priority = Settings::get('oz.auth.2fa', 'OZ_2FA_CHANNEL_PRIORITY');

		foreach ($priority as $name) {
			$channel = self::$channels[$name] ?? null;
			if ($channel && $channel->isAvailableFor($user)) {
				return $channel;
			}
		}

		// No channel is usable for this user despite 2FA being enabled.
		throw new RuntimeException(
			'No 2FA channel is available for this user. '
				. 'Make sure the user has a valid email, phone, or TOTP secret configured.',
			['_user_type' => $user->getAuthUserType(), '_user_id' => $user->getAuthIdentifier()]
		);
	}
}
