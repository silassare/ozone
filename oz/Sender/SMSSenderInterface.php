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

use OZONE\OZ\Db\OZUser;

\defined('OZ_SELF_SECURITY_CHECK') || die;

interface SMSSenderInterface
{
	/**
	 * SMSSenderInterface constructor.
	 *
	 * @param string $sender_name the sms sender name
	 */
	public function __construct($sender_name = '');

	/**
	 * @param \OZONE\OZ\Db\OZUser $user
	 * @param string              $message
	 *
	 * @return bool
	 */
	public function send(OZUser $user, $message);

	/**
	 * @param \OZONE\OZ\Db\OZUser[] $users
	 * @param string                $message
	 *
	 * @return bool
	 */
	public function sendBulk($users, $message);

	/**
	 * @param string $phone
	 * @param string $message
	 *
	 * @return bool
	 */
	public function sendToNumber($phone, $message);

	/**
	 * @param string $phone
	 * @param string $message
	 *
	 * @return bool
	 */
	public function sendBulkToNumbers($phone, $message);

	/**
	 * @return string
	 */
	public function getLastErrorMessage();
}
