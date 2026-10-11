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

namespace OZONE\Core\Messaging\Messages;

/**
 * Class NotificationMessage.
 *
 * A push notification: the destination of a user recipient is their identifier.
 */
class NotificationMessage extends Message
{
	public const CHANNEL = 'notification';

	/**
	 * NotificationMessage constructor.
	 *
	 * @param string $template the template name, without extension
	 */
	public function __construct(string $template)
	{
		parent::__construct(self::CHANNEL, $template);
	}
}
