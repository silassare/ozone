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

use OZONE\OZ\Senders\Events\SendNotification;
use PHPUtils\Events\Event;

class NotificationMessage extends Message
{
	/**
	 * @param string $target
	 *
	 * @return $this
	 */
	public function sendTo(string $target): static
	{
		Event::trigger(new SendNotification($target, $this));

		return $this;
	}
}
