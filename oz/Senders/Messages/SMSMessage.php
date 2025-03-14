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

namespace OZONE\Core\Senders\Messages;

use OZONE\Core\Auth\Interfaces\AuthUserInterface;
use OZONE\Core\Senders\Events\SendSMS;

/**
 * Class SMSMessage.
 *
 * @extends Message<string|AuthUserInterface>
 */
class SMSMessage extends Message
{
	/**
	 * {@inheritDoc}
	 */
	public function send(): static
	{
		(new SendSMS($this))->dispatch();

		return $this;
	}
}
