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
use OZONE\Core\Auth\Interfaces\AuthenticationMethodInterface;
use OZONE\Core\Auth\Interfaces\AuthUserInterface;
use OZONE\Core\Cache\CacheManager;
use PHPUtils\Store\Store;

/**
 * Class AuthUserDataStore.
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
	 *
	 * @return $this
	 */
	public static function getInstance(AuthUserInterface $user, array $data): self
	{
		$ref     = AuthUsers::ref($user);
		$cache   = CacheManager::runtime(__METHOD__);
		$factory = static function () use ($data) {
			return new self($data);
		};

		return $cache->factory($ref, $factory)
			->get();
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
	public function setAuthUserAccessRights(AccessRightsInterface $rights): self
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
	 *
	 * @return $this
	 */
	public function set2FAEnabled(bool $enabled = true): self
	{
		$this->set('2fa.enabled', $enabled);

		return $this;
	}
}
