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

namespace OZONE\OZ\Sessions;

use OZONE\OZ\Cache\CacheManager;
use OZONE\OZ\Db\OZSession;
use PHPUtils\Store\Store;

/**
 * Class SessionDataStore.
 */
class SessionDataStore extends Store
{
	/**
	 * SessionDataStore constructor.
	 *
	 * @param \OZONE\OZ\Db\OZSession $session
	 */
	private function __construct(private OZSession $session)
	{
		parent::__construct($this->session->getData());
	}

	/**
	 * SessionDataStore destructor.
	 */
	public function __destruct()
	{
		unset($this->session);

		parent::__destruct();
	}

	/**
	 * Create instance with a given session entry.
	 *
	 * @param \OZONE\OZ\Db\OZSession $session
	 *
	 * @return $this
	 */
	public static function getInstance(OZSession $session): self
	{
		$sid     = $session->getID();
		$cache   = CacheManager::runtime(__METHOD__);
		$factory = function () use ($session) {
			return new self($session);
		};

		return $cache->factory($sid, $factory)
			->get();
	}

	/**
	 * Gets user verification state.
	 *
	 * @return bool
	 */
	public function getUserIsVerified(): bool
	{
		return $this->session->isVerified();
	}

	/**
	 * Sets user verification state.
	 *
	 * @param bool $verified
	 *
	 * @return $this
	 */
	public function setUserIsVerified(bool $verified): self
	{
		$this->session->setVerified($verified);

		return $this;
	}

	/**
	 * Gets session user id.
	 *
	 * @return null|string
	 */
	public function getUserID(): ?string
	{
		return $this->session->getUserID();
	}

	/**
	 * Gets session token.
	 *
	 * @return null|string
	 */
	public function getToken(): ?string
	{
		return $this->session->getToken();
	}
}
