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

namespace __PLH_NAMESPACE__;

use OZONE\Core\Db\OZMessage;
use OZONE\Core\Messaging\Channel;
use OZONE\Core\Messaging\RenderedMessage;
use OZONE\Core\Messaging\Transports\TransportInterface;
use RuntimeException;

/**
 * A transport for MessagingTest: appends what it delivers to a file, one JSON line per message.
 *
 * Unavailable while the unavailable flag file exists. While the fail file holds a number, that many
 * deliveries fail; while it holds "always", every one does.
 */
final class MessagingTestTransport implements TransportInterface
{
	public function __construct(private readonly string $channel) {}

	public static function fromChannel(Channel $channel): static
	{
		return new self($channel->name);
	}

	public function isAvailable(): bool
	{
		return !\is_file('__PLH_UNAVAILABLE_FILE__');
	}

	public function deliver(OZMessage $message, RenderedMessage $content): void
	{
		$fail_file = '__PLH_FAIL_FILE__';

		if (\is_file($fail_file)) {
			$fail = \trim((string) \file_get_contents($fail_file));

			if ('always' === $fail) {
				throw new RuntimeException('transport refused');
			}

			if ((int) $fail > 0) {
				\file_put_contents($fail_file, (string) ((int) $fail - 1));

				throw new RuntimeException('transport refused');
			}
		}

		\file_put_contents('__PLH_DELIVERIES_FILE__', \json_encode([
			'id'          => $message->getID(),
			'channel'     => $this->channel,
			'destination' => $message->getDestination(),
			'template'    => $message->getTemplate(),
			'text'        => $content->text,
			'html'        => $content->html,
			'subject'     => $content->subject,
			'segments'    => $content->smsSegments(),
		]) . "\n", \FILE_APPEND);
	}
}
