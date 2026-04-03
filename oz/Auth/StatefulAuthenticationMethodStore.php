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

use OZONE\Core\Auth\Interfaces\AuthUserInterface;
use OZONE\Core\Cache\CacheManager;
use PHPUtils\Store\Store;

/**
 * Class StatefulAuthenticationMethodStore.
 *
 * @extends Store<array>
 */
class StatefulAuthenticationMethodStore extends Store
{
	/**
	 * StatefulAuthenticationMethodStore constructor.
	 *
	 * @param array $state
	 */
	private function __construct(array $state)
	{
		parent::__construct($state);
	}

	/**
	 * Returns the state instance.
	 */
	public static function getInstance(string $state_id, array $data): static
	{
		$cache   = CacheManager::runtime(__METHOD__);
		$factory = static fn (): static => new static($data);

		return $cache->factory($state_id, $factory)
			->get();
	}

	/**
	 * Gets previous user.
	 *
	 * @return null|AuthUserInterface
	 *
	 * @internal
	 */
	public function getPreviousUser(): ?AuthUserInterface
	{
		$selector = $this->get('oz.previous_auth_user');

		if (\is_array($selector)) {
			return AuthUsers::identifyBySelector($selector);
		}

		return null;
	}

	/**
	 * Sets previous user.
	 *
	 * @param AuthUserInterface $user
	 *
	 * @internal
	 */
	public function setPreviousUser(AuthUserInterface $user): static
	{
		return $this->set('oz.previous_auth_user', AuthUsers::selector($user));
	}

	/**
	 * Gets the user held pending 2FA verification.
	 *
	 * Returns null when there is no pending 2FA user in the current session.
	 *
	 * @return null|AuthUserInterface
	 *
	 * @internal
	 */
	public function get2FAPendingUser(): ?AuthUserInterface
	{
		$selector = $this->get('oz.2fa_pending_user');

		if (\is_array($selector)) {
			return AuthUsers::identifyBySelector($selector);
		}

		return null;
	}

	/**
	 * Stores the user that must complete 2FA before being fully attached to the session.
	 *
	 * @param AuthUserInterface $user
	 *
	 * @internal
	 */
	public function set2FAPendingUser(AuthUserInterface $user): static
	{
		return $this->set('oz.2fa_pending_user', AuthUsers::selector($user));
	}

	/**
	 * Removes the pending 2FA user entry from the session store.
	 *
	 * Called by {@see TwoFactorAuthorizationProvider::onAuthorized()} after the user
	 * is successfully re-attached.
	 *
	 * @internal
	 */
	public function clear2FAPendingUser(): static
	{
		return $this->remove('oz.2fa_pending_user');
	}
}
