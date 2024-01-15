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

namespace OZONE\Core\Sessions;

use OZONE\Core\Cache\CacheManager;
use OZONE\Core\Db\OZSession;
use PHPUtils\Store\Store;

/**
 * Class SessionState.
 */
class SessionState extends Store
{
	/**
	 * SessionState constructor.
	 *
	 * @param OZSession $session
	 */
	private function __construct(private OZSession $session)
	{
		parent::__construct($this->session->getData());
	}

	/**
	 * SessionState destructor.
	 */
	public function __destruct()
	{
		unset($this->session);

		parent::__destruct();
	}

	/**
	 * Create instance with a given session entry.
	 *
	 * @param OZSession $session
	 *
	 * @return $this
	 */
	public static function getInstance(OZSession $session): self
	{
		$sid     = $session->getID();
		$cache   = CacheManager::runtime(__METHOD__);
		$factory = static function () use ($session) {
			return new self($session);
		};

		return $cache->factory($sid, $factory)
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
