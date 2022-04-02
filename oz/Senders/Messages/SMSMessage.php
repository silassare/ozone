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

namespace OZONE\OZ\Senders\Messages;

use OZONE\OZ\Senders\Events\SendSMS;
use PHPUtils\Events\Event;

class SMSMessage extends Message
{
	/**
	 * @param string $phone
	 *
	 * @return $this
	 */
	public function sendTo(string $phone): static
	{
		Event::trigger(new SendSMS($phone, $this));

		return $this;
	}
}
