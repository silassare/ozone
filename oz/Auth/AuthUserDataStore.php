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

use OZONE\Core\Access\AccessRights;
use OZONE\Core\Access\Interfaces\AccessRightsInterface;
use OZONE\Core\Auth\Interfaces\AuthUserInterface;
use OZONE\Core\Auth\TwoFactor\TwoFactorChannelRegistry;
use OZONE\Core\Cache\CacheRegistry;
use PHPUtils\Store\Store;

/**
 * Class AuthUserDataStore.
 *
 * @extends Store<array>
 */
class AuthUserDataStore extends Store
{
	/**
	 * AuthUserDataStore constructor.
	 *
	 * @param array $data
	 */
	private function __construct(array $data)
	{
		parent::__construct($data);
	}

	/**
	 * Returns the store instance.
	 */
	public static function getInstance(AuthUserInterface $user, array $data): static
	{
		return CacheRegistry::runtime(__METHOD__)->remember(AuthUsers::ref($user), static fn (): static => new static($data));
	}

	/**
	 * Gets the user access rights.
	 *
	 * > IMPORTANT: Don't use this method to check access rights,
	 * > as the request may be using scoped auth credentials.
	 * >
	 * > use {@see AuthenticationMethodInterface::getAccessRights()} instead.
	 * >
	 * > Example: `auth()->getAccessRights()`
	 */
	public function getAuthUserAccessRights(): AccessRightsInterface
	{
		$data = $this->get('access_rights') ?? [];

		return new AccessRights($data, false);
	}

	/**
	 * Sets the user access rights.
	 */
	public function setAuthUserAccessRights(AccessRightsInterface $rights): static
	{
		$this->set('access_rights', $rights->toArray());

		return $this;
	}

	/**
	 * Checks if the user has 2FA enabled.
	 */
	public function has2FAEnabled(): bool
	{
		return true === (bool) $this->get('2fa.enabled');
	}

	/**
	 * Sets the 2FA enabled status.
	 *
	 * @param bool $enabled
	 */
	public function set2FAEnabled(bool $enabled = true): static
	{
		$this->set('2fa.enabled', $enabled);

		return $this;
	}

	/**
	 * Gets the user's preferred 2FA channel name.
	 *
	 * Returns null when no preference is set (auto-selection applies).
	 * Possible values are channel names such as 'totp', 'email', 'sms',
	 * or any name registered with {@see TwoFactorChannelRegistry}.
	 *
	 * @return null|string
	 */
	public function get2FAMethod(): ?string
	{
		$method = $this->get('2fa.method');

		return \is_string($method) && '' !== $method ? $method : null;
	}

	/**
	 * Sets the user's preferred 2FA channel name.
	 *
	 * Pass null to clear the preference and fall back to auto-selection.
	 *
	 * @param null|string $method channel name (e.g. 'totp', 'email', 'sms') or null
	 */
	public function set2FAMethod(?string $method): static
	{
		$this->set('2fa.method', $method);

		return $this;
	}

	/**
	 * Gets the base32-encoded TOTP secret stored for this user.
	 *
	 * Returns null when TOTP has not been set up.
	 *
	 * @return null|string
	 */
	public function get2FATotpSecret(): ?string
	{
		$secret = $this->get('2fa.totp_secret');

		return \is_string($secret) && '' !== $secret ? $secret : null;
	}

	/**
	 * Stores the base32-encoded TOTP secret for this user.
	 *
	 * Pass null to remove the TOTP secret (disables TOTP channel for this user).
	 *
	 * @param null|string $secret base32-encoded TOTP secret or null
	 */
	public function set2FATotpSecret(?string $secret): static
	{
		$this->set('2fa.totp_secret', $secret);

		return $this;
	}
}
