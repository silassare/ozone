<?php

/**
 * Copyright (c) 2017-present, Emile Silas Sare
 *
 * This file is part of OZone package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace OZONE\OZ\Users\Events;

use OZONE\OZ\Core\Context;
use OZONE\OZ\Db\OZUser;
use PHPUtils\Events\Event;

/**
 * Class UserLogInInvalidPass.
 */
final class UserLogInInvalidPass extends Event
{
	public function __construct(private Context $context, private OZUser $user)
	{
	}

	/**
	 * @return \OZONE\OZ\Core\Context
	 */
	public function getContext(): Context
	{
		return $this->context;
	}

	/**
	 * @return \OZONE\OZ\Db\OZUser
	 */
	public function getUser(): OZUser
	{
		return $this->user;
	}
}
