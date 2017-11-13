<?php
	/**
	 * Auto generated file, please don't edit.
	 *
	 * With: Gobl v1.0.0
	 * Time: 1508868493
	 */

	namespace OZONE\OZ\Db;

	use OZONE\OZ\Crypt\DoCrypt;
	use OZONE\OZ\Db\Base\OZUser as BaseOZUser;
	use OZONE\OZ\Db\Columns\Types\TypeEmail;
	use OZONE\OZ\Db\Columns\Types\TypePhone;
	use OZONE\OZ\Exceptions\InternalErrorException;
	use OZONE\OZ\OZone;

	class OZUser extends BaseOZUser
	{
		/**
		 * {@inheritdoc}
		 */
		public function save()
		{
			$emit_create_event = false;

			if (!$this->getId()) {
				// new user will be added
				$emit_create_event = true;
				$phone             = $this->getPhone();
				$email             = $this->getEmail();
				$pass              = $this->getPass();

				// we should store encrypted password
				$crypt = new DoCrypt();
				parent::setPass($crypt->passHash($pass));

				if (!empty($phone)) {
					// check if the phone is not already registered
					$phone_validator = new TypePhone();
					$phone_validator->notRegistered();
					$phone_validator->validate($phone);
				}

				if (!empty($email)) {
					// check if the email is not already registered
					$email_validator = new TypeEmail();
					$email_validator->notRegistered();
					$email_validator->validate($email);
				}

				if (empty($phone) AND empty($email)) {
					// Maybe "OZ_USERS_PHONE_REQUIRED" and "OZ_USERS_EMAIL_REQUIRED" are both set to "false" in "oz.users" settings file.
					throw new InternalErrorException('Both user Phone and Email should not be empty.', ['Maybe "OZ_USERS_PHONE_REQUIRED" and "OZ_USERS_EMAIL_REQUIRED" are both set to "false" in "oz.users" settings file.']);
				}
			}

			$result = parent::save();

			if ($emit_create_event) {
				OZone::getEventManager()
					 ->trigger('OZ_EVENT:USER_ADDED', $this);
			}

			return $result;
		}

		/**
		 * {@inheritdoc}
		 */
		public function asArray()
		{
			$data = parent::asArray();

			unset($data[OZUser::COL_PASS]);

			return $data;
		}
	}