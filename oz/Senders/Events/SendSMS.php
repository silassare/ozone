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

use OZONE\Core\Senders\Messages\SMSMessage;
use PHPUtils\Events\Event;

/**
 * Class SendSMS.
 *
 * This event is triggered to send an SMS.
 * Please note that the SMS is not sent.
 * The SMS sender should catch this event and send the SMS.
 */
final class SendSMS extends Event
{
	/**
	 * SendSMS constructor.
	 *
	 * @param SMSMessage $message the message to send
	 */
	public function __construct(
		public readonly SMSMessage $message
	) {}
}
