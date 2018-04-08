<?php
	/**
	 * Copyright (c) Emile Silas Sare <emile.silas@gmail.com>
	 *
	 * This file is part of the OZone package.
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
		const SMS_TYPE_AUTH_CODE = 1;

		static $sms_map = [
			SMSUtils::SMS_TYPE_AUTH_CODE => "OZ_SMS_TYPE_AUTH_CODE_MESSAGE"
		];

		/**
		 * @param int   $sms_type
		 * @param array $data
		 *
		 * @return string
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
		 */
		public static function getSenderInstance($sender_name = "")
		{
			$sender = SettingsManager::get('oz.config', '');
			if ($sender AND ClassLoader::exists($sender)) {
				/**
				 * @var \OZONE\OZ\Sender\SMSSenderInterface $instance
				 */
				return $instance = new $sender($sender_name);
			}

			return null;
		}
	}