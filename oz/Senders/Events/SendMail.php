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

use OZONE\Core\Senders\Messages\MailMessage;
use PHPUtils\Events\Event;

/**
 * Class SendMail.
 *
 * This event is triggered to send an email.
 * Please note that the email is not sent.
 * The mailer should catch this event and send the email.
 */
final class SendMail extends Event
{
	/**
	 * SendMail constructor.
	 *
	 * @param MailMessage $message the message
	 */
	public function __construct(
		public readonly MailMessage $message
	) {}
}
