<?php

/**
 * Auto generated file,
 *
 * INFO: you are free to edit it,
 * but make sure to know what you are doing.
 *
 * Proudly With: gobl v1.5.0
 * Time: 1617030519
 */

namespace OZONE\OZ\Db;

use OZONE\OZ\Crypt\DoCrypt;
use OZONE\OZ\Db\Base\OZUser as BaseOZUser;
use OZONE\OZ\Exceptions\InternalErrorException;
use OZONE\OZ\OZone;
use OZONE\OZ\User\UsersManager;

/**
 * Class OZUser
 */
class OZUser extends BaseOZUser
{
	/**
	* @inheritdoc
	* @throws \OZONE\OZ\Exceptions\InternalErrorException
	*/
	public function save()
	{
		$emit_create_event = false;

		if (!$this->getId()) {
			// new user will be added
			$emit_create_event = true;
			$phone             = $this->getPhone();
			$email             = $this->getEmail();

			if (empty($phone) and empty($email)) {
				// Maybe "OZ_USERS_PHONE_REQUIRED" and "OZ_USERS_EMAIL_REQUIRED" are both set to "false" in "oz.users" settings file.
				throw new InternalErrorException('Both user Phone and Email should not be empty.', ['Maybe "OZ_USERS_PHONE_REQUIRED" and "OZ_USERS_EMAIL_REQUIRED" are both set to "false" in "oz.users" settings file.']);
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
			if ($this->getId()) {
				UsersManager::forceLoginOnUserAttachedSessions($this);
			}
		}

		$result = parent::save();

		if ($emit_create_event) {
			OZone::getEventManager()
				 ->trigger('OZ_EVENT_USER_ADDED', $this);
		}

		return $result;
	}

	/**
	 * @inheritdoc
	 */
	public function asArray($hide_private_column = true)
	{
		$data = parent::asArray($hide_private_column);

		$data[OZUser::COL_PASS] = null;

		return $data;
	}
}
