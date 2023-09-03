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

namespace OZONE\Core\Users\Traits;

use OZONE\Core\Auth\AuthAccessRights;
use OZONE\Core\Auth\Interfaces\AuthAccessRightsInterface;
use OZONE\Core\Crypt\Password;
use OZONE\Core\Exceptions\RuntimeException;
use OZONE\Core\Users\Users;

/**
 * Trait UserEntityTrait.
 */
trait UserEntityTrait
{
	/**
	 * {@inheritDoc}
	 */
	public function save(): bool
	{
		if (!$this->getID()) {
			// new user will be added
			$phone             = $this->getPhone();
			$email             = $this->getEmail();

			if (empty($phone) && empty($email)) {
				// Maybe "OZ_USER_PHONE_REQUIRED" and "OZ_USER_EMAIL_REQUIRED"
				// are both set to "false" in "oz.users" settings file.
				throw new RuntimeException('Both user Phone and Email should not be empty.');
			}
		}

		$pass = $this->getPass();

		// we should not store non-hashed password
		if (!Password::isHash($pass)) {
			$pass = Password::hash($pass);
			$this->setPass($pass);

			// when user password change, force login again
			// on all sessions associated with user
			if ($this->getID()) {
				Users::forceUserLogoutOnAllActiveSessions($this->getID());
			}
		}

		return parent::save();
	}

	/**
	 * {@inheritDoc}
	 */
	public function toArray($hide_sensitive_data = true): array
	{
		$arr = parent::toArray($hide_sensitive_data);

		$arr[self::COL_PASS] = null;

		return $arr;
	}

	/**
	 * Gets user access rights.
	 *
	 * @return \OZONE\Core\Auth\Interfaces\AuthAccessRightsInterface
	 */
	public function getAccessRights(): AuthAccessRightsInterface
	{
		$data   = $this->getData();
		$rights = $data['access_rights'] ?? [];

		return new AuthAccessRights($rights);
	}

	/**
	 * Sets user access rights.
	 *
	 * @param \OZONE\Core\Auth\Interfaces\AuthAccessRightsInterface $rights
	 *
	 * @return $this
	 */
	public function setAccessRights(AuthAccessRightsInterface $rights): static
	{
		$data                  = $this->getData();
		$data['access_rights'] = $rights->getOptions();
		$this->setData($data);

		return $this;
	}
}
