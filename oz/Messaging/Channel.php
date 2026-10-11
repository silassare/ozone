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

namespace OZONE\Core\Messaging;

use OZONE\Core\App\Settings;
use OZONE\Core\Auth\Interfaces\AuthUserInterface;
use OZONE\Core\Exceptions\RuntimeException;
use OZONE\Core\Messaging\Transports\LogTransport;
use OZONE\Core\Messaging\Transports\TransportInterface;

/**
 * Class Channel.
 *
 * A channel as `OZ_MESSAGING_CHANNELS` (`oz.messaging`) declares it, each option defaulted.
 */
final class Channel
{
	/**
	 * What a channel declares, and the value of what it leaves out.
	 */
	public const DEFAULTS = [
		'enabled'     => true,
		'transport'   => LogTransport::class,
		'async'       => true,
		'retry_max'   => 3,
		'retry_delay' => 60,
		'identifier'  => AuthUserInterface::IDENTIFIER_TYPE_ID,
		'html'        => false,
		'gsm7'        => false,
	];

	/**
	 * Channel constructor.
	 *
	 * @param string                           $name        the channel's name
	 * @param bool                             $enabled     whether its messages are sent
	 * @param class-string<TransportInterface> $transport   what carries them
	 * @param bool                             $async       whether they are delivered by a queue job
	 * @param int                              $retry_max   how many attempts a queued one gets
	 * @param int                              $retry_delay seconds between two attempts
	 * @param string                           $identifier  the identifier of a user recipient that is the
	 *                                                      destination (`AuthUserInterface::IDENTIFIER_TYPE_*`)
	 * @param bool                             $html        whether an HTML part is rendered
	 * @param bool                             $gsm7        whether the text is folded to GSM 03.38
	 */
	public function __construct(
		public readonly string $name,
		public readonly bool $enabled,
		public readonly string $transport,
		public readonly bool $async,
		public readonly int $retry_max,
		public readonly int $retry_delay,
		public readonly string $identifier,
		public readonly bool $html,
		public readonly bool $gsm7,
	) {}

	/**
	 * Gets a declared channel.
	 *
	 * @throws RuntimeException when the channel is not declared, or its transport is not a transport
	 */
	public static function get(string $name): self
	{
		$declared = self::declared();

		if (!isset($declared[$name]) || !\is_array($declared[$name])) {
			throw (new RuntimeException(\sprintf(
				'The messaging channel "%s" is not declared: no message can be sent on it.',
				$name
			), ['declared' => \array_keys($declared)]))->suspectConfig('oz.messaging', 'OZ_MESSAGING_CHANNELS');
		}

		$options   = $declared[$name] + self::DEFAULTS;
		$transport = $options['transport'];

		if (!\is_string($transport) || !\is_subclass_of($transport, TransportInterface::class)) {
			throw (new RuntimeException(\sprintf(
				'The transport of the messaging channel "%s" should implement "%s".',
				$name,
				TransportInterface::class
			), [
				'transport' => \is_string($transport) ? $transport : \get_debug_type($transport),
			]))->suspectConfig('oz.messaging', 'OZ_MESSAGING_CHANNELS');
		}

		return new self(
			$name,
			(bool) $options['enabled'],
			$transport,
			(bool) $options['async'],
			\max(1, (int) $options['retry_max']),
			\max(0, (int) $options['retry_delay']),
			(string) $options['identifier'],
			(bool) $options['html'],
			(bool) $options['gsm7'],
		);
	}

	/**
	 * Gets every declared channel.
	 *
	 * @return array<string, self>
	 */
	public static function all(): array
	{
		$channels = [];

		foreach (\array_keys(self::declared()) as $name) {
			$channels[(string) $name] = self::get((string) $name);
		}

		return $channels;
	}

	/**
	 * Builds this channel's transport.
	 */
	public function transport(): TransportInterface
	{
		$class = $this->transport;

		return $class::fromChannel($this);
	}

	/**
	 * Whether this channel's transport is the log transport, which sends nothing.
	 */
	public function usesLogTransport(): bool
	{
		return LogTransport::class === $this->transport;
	}

	/**
	 * @return array<array-key, mixed>
	 */
	private static function declared(): array
	{
		return (array) Settings::get('oz.messaging', 'OZ_MESSAGING_CHANNELS', []);
	}
}
