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
use PHPUtils\Events\Event;

/**
 * Class UserLogInUnknown.
 */
final class UserLogInUnknown extends Event
{
	public function __construct(private Context $context)
	{
	}

	/**
	 * @return \OZONE\OZ\Core\Context
	 */
	public function getContext(): Context
	{
		return $this->context;
	}
}
