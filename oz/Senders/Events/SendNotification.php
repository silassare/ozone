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

namespace OZONE\Core\Senders\Events;

use OZONE\Core\Senders\Messages\NotificationMessage;
use PHPUtils\Events\Event;

/**
 * Class SendNotification.
 *
 * This event is triggered to send a notification.
 * Please note that the notification is not sent.
 * The notification sender should catch this event and send the notification.
 */
final class SendNotification extends Event
{
	/**
	 * SendNotification constructor.
	 *
	 * @param NotificationMessage $message the message to send
	 */
	public function __construct(
		public readonly NotificationMessage $message
	) {}
}
