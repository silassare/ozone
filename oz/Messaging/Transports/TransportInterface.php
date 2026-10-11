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

namespace OZONE\Core\Messaging\Transports;

use OZONE\Core\Db\OZMessage;
use OZONE\Core\Messaging\Channel;
use OZONE\Core\Messaging\RenderedMessage;
use Throwable;

/**
 * Interface TransportInterface.
 *
 * What carries the messages of a channel (`transport` of a channel in `oz.messaging`): SMTP, an SMS
 * aggregator, a push service. A project or a plugin implements it; OZone ships {@see LogTransport}.
 *
 * A transport only delivers: it never retries, never writes `oz_messages`, never decides whether a
 * message should go. {@see \OZONE\Core\Messaging\Messenger} does.
 */
interface TransportInterface
{
	/**
	 * Builds the transport of a channel, from its settings (credentials are usually read from the
	 * environment, through a settings file).
	 */
	public static function fromChannel(Channel $channel): static;

	/**
	 * Whether the transport can deliver right now: false when what it needs (credentials, a server)
	 * is missing. A message for an unavailable transport is recorded as skipped, not failed.
	 */
	public function isAvailable(): bool;

	/**
	 * Delivers one message to its destination (`$message->getDestination()`).
	 *
	 * @throws Throwable when the message was not handed over: the attempt is recorded as failed, and
	 *                   retried when the channel allows another
	 */
	public function deliver(OZMessage $message, RenderedMessage $content): void;
}
