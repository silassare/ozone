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

use OZONE\Core\Senders\Events\SendNotification;
use PHPUtils\Events\Event;

/**
 * Class NotificationMessage.
 */
class NotificationMessage extends Message
{
	/**
	 * {@inheritDoc}
	 */
	public function send(string $to): static
	{
		Event::trigger(new SendNotification($to, $this));

		return $this;
	}
}
