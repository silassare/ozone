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
	use OZONE\OZ\OZone;

	class OZUser extends BaseOZUser
	{
		/**
		 * {@inheritdoc}
		 */
		public function setPass($pass)
		{
			$crypt = new DoCrypt();

			return parent::setPass($crypt->passHash($pass));
		}

		public function save()
		{
			$emit_event = false;
			if (!$this->getId()) {
				$emit_event = true;
			}

			$result = parent::save();

			if ($emit_event) {
				OZone::getEventManager()
					 ->trigger('OZ_EVENT:USER_CREATED', $this);
			}

			return $result;
		}

		/**
		 * {@inheritdoc}
		 */
		public function asArray()
		{
			$data = $this->asArray();

			unset($data[OZUser::COL_PASS]);

			return $data;
		}
	}