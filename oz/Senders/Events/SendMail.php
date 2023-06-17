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
 */
final class SendMail extends Event
{
	protected MailMessage $message;

	public function __construct(protected string $email, MailMessage $message)
	{
		$this->message = clone $message;
	}

	/**
	 * @return string
	 */
	public function getEmail(): string
	{
		return $this->email;
	}

	/**
	 * @return \OZONE\Core\Senders\Messages\MailMessage
	 */
	public function getMessage(): MailMessage
	{
		return $this->message;
	}
}
