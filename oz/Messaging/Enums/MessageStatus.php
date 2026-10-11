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

namespace OZONE\Core\Messaging\Enums;

/**
 * Enum MessageStatus.
 *
 * What happened to a message on its channel (`oz_messages.msg_status`).
 */
enum MessageStatus: string
{
	/**
	 * Recorded, waiting for its transport: in the queue, or between two attempts.
	 */
	case QUEUED = 'queued';

	/**
	 * Handed over to the transport.
	 */
	case SENT = 'sent';

	/**
	 * Every attempt failed; the last error is on the record.
	 */
	case FAILED = 'failed';

	/**
	 * Not attempted, which is not a failure: no destination (a user without an email address), a
	 * channel switched off, a transport without what it needs. The reason is on the record.
	 */
	case SKIPPED = 'skipped';
}
