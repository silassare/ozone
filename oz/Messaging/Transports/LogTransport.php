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

use Override;
use OZONE\Core\Db\OZMessage;
use OZONE\Core\Messaging\Channel;
use OZONE\Core\Messaging\RenderedMessage;
use OZONE\Core\OZone;

/**
 * Class LogTransport.
 *
 * Writes each message to the log instead of sending it: the default transport of every channel, so a
 * fresh project's messages (an email verification, a two-factor code) can be read in the log and
 * their wording reviewed, before any account with a provider exists.
 *
 * It is **unavailable in production**: a message would carry codes and links into a log nobody reads
 * as a mailbox. A message for it is then recorded as skipped and logged as an error, and
 * `oz doctor check` fails while an enabled channel uses it.
 */
final class LogTransport implements TransportInterface
{
	/**
	 * LogTransport constructor.
	 */
	public function __construct(private readonly string $channel) {}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public static function fromChannel(Channel $channel): static
	{
		return new self($channel->name);
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function isAvailable(): bool
	{
		return !OZone::inProductionMode();
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function deliver(OZMessage $message, RenderedMessage $content): void
	{
		$lines = [\sprintf(
			'[message] %s to "%s" on %s (message %s)',
			$message->getTemplate(),
			$message->getDestination(),
			$this->channel,
			$message->getID()
		)];

		if (null !== $content->subject) {
			$lines[] = 'Subject: ' . $content->subject;
		}

		$lines[] = $content->text;

		if ($content->hasHtml()) {
			$lines[] = '--- html ---';
			$lines[] = (string) $content->html;
		}

		oz_logger()->info(\implode(\PHP_EOL, $lines));
	}
}
