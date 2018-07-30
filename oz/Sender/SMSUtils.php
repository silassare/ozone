<?php
	/**
	 * Copyright (c) Emile Silas Sare <emile.silas@gmail.com>
	 *
	 * This file is part of OZone (O'Zone) package.
	 *
	 * For the full copyright and license information, please view the LICENSE
	 * file that was distributed with this source code.
	 */

	namespace OZONE\OZ\Sender;

	use OZONE\OZ\Core\SettingsManager;
	use OZONE\OZ\Lang\Polyglot;
	use OZONE\OZ\Loader\ClassLoader;

	defined('OZ_SELF_SECURITY_CHECK') or die;

	final class SMSUtils
	{
		const SMS_TYPE_AUTH_CODE      = 1;
		const SMS_TYPE_PASS_AUTH_CODE = 2;

		static $sms_map = [
			SMSUtils::SMS_TYPE_AUTH_CODE      => "OZ_SMS_AUTH_CODE_MESSAGE",
			SMSUtils::SMS_TYPE_PASS_AUTH_CODE => "OZ_SMS_AUTH_CODE_PASSWORD_EDIT_MESSAGE",
		];

		/**
		 * @param int   $sms_type
		 * @param array $data
		 *
		 * @return string
		 * @throws \Exception
		 */
		public static function getSMSMessage($sms_type, array $data = [])
		{
			if (isset(self::$sms_map[$sms_type])) {
				$sms = self::$sms_map[$sms_type];

				return Polyglot::translate($sms, $data);
			}

			return "";
		}

		/**
		 * Returns SMS sender instance or null if none is defined.
		 *
		 * @param string $sender_name
		 *
		 * @return null|\OZONE\OZ\Sender\SMSSenderInterface
		 * @throws \OZONE\OZ\Exceptions\InternalErrorException
		 */
		public static function getSenderInstance($sender_name = "")
		{
			$sender = SettingsManager::get('oz.config', 'OZ_APP_SMS_SENDER_CLASS');
			if ($sender AND ClassLoader::exists($sender)) {
				/**
				 * @var \OZONE\OZ\Sender\SMSSenderInterface $instance
				 */
				return $instance = new $sender($sender_name);
			}

			return null;
		}
	}