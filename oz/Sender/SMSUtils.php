<?php

/**
 * Copyright (c) 2017-present, Emile Silas Sare
 *
 * This file is part of OZone (O'Zone) package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace OZONE\OZ\Sender;

use OZONE\OZ\Core\SettingsManager;
use OZONE\OZ\Loader\ClassLoader;

\defined('OZ_SELF_SECURITY_CHECK') || die;

final class SMSUtils
{
	const SMS_TYPE_AUTH_CODE      = 1;

	const SMS_TYPE_PASS_AUTH_CODE = 2;

	/**
	 * Map sms type to message.
	 *
	 * @param int $type
	 *
	 * @throws \Exception
	 *
	 * @return null|string
	 */
	public static function getSMSMessage($type)
	{
		$sms_map = [
			self::SMS_TYPE_AUTH_CODE      => 'OZ_SMS_AUTH_CODE_MESSAGE',
			self::SMS_TYPE_PASS_AUTH_CODE => 'OZ_SMS_AUTH_CODE_PASSWORD_EDIT_MESSAGE',
		];

		if (isset($sms_map[$type])) {
			return $sms_map[$type];
		}

		return null;
	}

	/**
	 * Returns SMS sender instance or null if none is defined.
	 *
	 * @param string $sender_name
	 *
	 * @return null|\OZONE\OZ\Sender\SMSSenderInterface
	 */
	public static function getSenderInstance($sender_name = '')
	{
		$sender = SettingsManager::get('oz.config', 'OZ_APP_SMS_SENDER_CLASS');

		if ($sender && ClassLoader::exists($sender)) {
			/* @var \OZONE\OZ\Sender\SMSSenderInterface $instance */
			return $instance = new $sender($sender_name);
		}

		return null;
	}
}
