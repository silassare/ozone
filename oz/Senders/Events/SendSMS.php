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
 */
final class SendSMS extends Event
{
	protected SMSMessage $message;

	public function __construct(protected string $phone, SMSMessage $message)
	{
		$this->message = clone $message;
	}

	/**
	 * @return string
	 */
	public function getPhone(): string
	{
		return $this->phone;
	}

	/**
	 * @return SMSMessage
	 */
	public function getMessage(): SMSMessage
	{
		return $this->message;
	}
}
