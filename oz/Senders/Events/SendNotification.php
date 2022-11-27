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

namespace OZONE\OZ\Senders\Events;

use OZONE\OZ\Senders\Messages\NotificationMessage;
use PHPUtils\Events\Event;

/**
 * Class SendNotification.
 */
final class SendNotification extends Event
{
	protected NotificationMessage $message;

	public function __construct(protected string $target, NotificationMessage $message)
	{
		$this->message = clone $message;
	}

	/**
	 * @return string
	 */
	public function getTarget(): string
	{
		return $this->target;
	}

	/**
	 * @return \OZONE\OZ\Senders\Messages\NotificationMessage
	 */
	public function getMessage(): NotificationMessage
	{
		return $this->message;
	}
}
