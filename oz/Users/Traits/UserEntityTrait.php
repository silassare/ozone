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

namespace OZONE\OZ\Users\Traits;

use OZONE\OZ\Crypt\DoCrypt;
use OZONE\OZ\Exceptions\RuntimeException;
use OZONE\OZ\Users\Events\UserCreated;
use OZONE\OZ\Users\UsersManager;
use PHPUtils\Events\Event;

trait UserEntityTrait
{
	/**
	 * {@inheritDoc}
	 */
	public function save(): bool
	{
		$emit_create_event = false;

		if (!$this->getID()) {
			// new user will be added
			$emit_create_event = true;
			$phone             = $this->getPhone();
			$email             = $this->getEmail();

			if (empty($phone) && empty($email)) {
				// Maybe "OZ_USER_PHONE_REQUIRED" and "OZ_USER_EMAIL_REQUIRED"
				// are both set to "false" in "oz.users" settings file.
				throw new RuntimeException('Both user Phone and Email should not be empty.');
			}
		}

		$crypt = new DoCrypt();
		$pass  = $this->getPass();

		// we should not store unencrypted password
		if (!$crypt->isHash($pass)) {
			$pass = $crypt->passHash($pass);
			$this->setPass($pass);

			// when user password change, force login again
			// on all sessions associated with user
			if ($this->getID()) {
				UsersManager::forceUserLogoutOnActiveSessions($this);
			}
		}

		$result = parent::save();

		if ($emit_create_event) {
			Event::trigger(new UserCreated($this));
		}

		return $result;
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
}
