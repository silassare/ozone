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
 * Class StatefulAuthStore.
 *
 * @extends Store<array>
 */
class StatefulAuthStore extends Store
{
	/**
	 * StatefulAuthStore constructor.
	 *
	 * @param array $state
	 */
	private function __construct(array $state)
	{
		parent::__construct($state);
	}

	/**
	 * Returns the state instance.
	 *
	 * @return $this
	 */
	public static function getInstance(string $state_id, array $data): self
	{
		$cache   = CacheManager::runtime(__METHOD__);
		$factory = static function () use ($data) {
			return new self($data);
		};

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
	 * @return self
	 *
	 * @internal
	 */
	public function setPreviousUser(AuthUserInterface $user): self
	{
		return $this->set('oz.previous_auth_user', AuthUsers::selector($user));
	}
}
