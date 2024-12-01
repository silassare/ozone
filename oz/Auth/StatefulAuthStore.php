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
	 * Gets previous user id.
	 *
	 * @return null|string
	 *
	 * @internal
	 */
	public function getPreviousUserID(): ?string
	{
		return $this->get('oz.previous_user_id', null);
	}

	/**
	 * Sets previous user id.
	 *
	 * This is set when a user is logged out.
	 *
	 * @param string $uid
	 *
	 * @return self
	 *
	 * @internal
	 */
	public function setPreviousUserID(string $uid): self
	{
		return $this->set('oz.previous_user_id', $uid);
	}
}
