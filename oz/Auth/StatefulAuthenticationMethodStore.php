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
}
