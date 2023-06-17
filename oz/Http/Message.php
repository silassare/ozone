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

namespace OZONE\Core\Http;

use InvalidArgumentException;
use Psr\Http\Message\MessageInterface;
use Psr\Http\Message\StreamInterface;

/**
 * Class Message.
 */
abstract class Message implements MessageInterface
{
	/**
	 * A map of valid protocol versions.
	 */
	protected static array $validProtocolVersions = [
		'1.0' => true,
		'1.1' => true,
		'2.0' => true,
	];

	/**
	 * Protocol version.
	 */
	protected string $protocolVersion = '1.1';

	/**
	 * Headers.
	 */
	protected Headers $headers;

	/**
	 * Body object.
	 */
	protected StreamInterface $body;

	/**
	 * Disable magic setter to ensure immutability.
	 *
	 * @param $name
	 * @param $value
	 */
	public function __set($name, $value): void
	{
		// Do nothing
	}

	/**
	 * {@inheritDoc}
	 */
	public function getProtocolVersion(): string
	{
		return $this->protocolVersion;
	}

	/**
	 * {@inheritDoc}
	 */
	public function withProtocolVersion($version): static
	{
		if (!isset(self::$validProtocolVersions[$version])) {
			throw new InvalidArgumentException(
				'Invalid HTTP version. Must be one of: '
				. \implode(', ', \array_keys(self::$validProtocolVersions))
			);
		}
		$clone                  = clone $this;
		$clone->protocolVersion = $version;

		return $clone;
	}

	/**
	 * {@inheritDoc}
	 */
	public function getHeaders(): array
	{
		return $this->headers->all();
	}

	/**
	 * {@inheritDoc}
	 */
	public function hasHeader($name): bool
	{
		return $this->headers->has($name);
	}

	/**
	 * {@inheritDoc}
	 */
	public function getHeader($name): array
	{
		return $this->headers->get($name, []);
	}

	/**
	 * {@inheritDoc}
	 */
	public function getHeaderLine($name): string
	{
		return \implode(',', $this->headers->get($name, []));
	}

	/**
	 * {@inheritDoc}
	 */
	public function withHeader($name, $value): static
	{
		$clone = clone $this;
		$clone->headers->set($name, $value);

		return $clone;
	}

	/**
	 * {@inheritDoc}
	 */
	public function withAddedHeader($name, $value): static
	{
		$clone = clone $this;
		$clone->headers->add($name, $value);

		return $clone;
	}

	/**
	 * {@inheritDoc}
	 */
	public function withoutHeader($name): static
	{
		$clone = clone $this;
		$clone->headers->remove($name);

		return $clone;
	}

	/**
	 * {@inheritDoc}
	 */
	public function getBody(): StreamInterface
	{
		return $this->body;
	}

	/**
	 * {@inheritDoc}
	 */
	public function withBody(StreamInterface $body): static
	{
		$clone       = clone $this;
		$clone->body = $body;

		return $clone;
	}
}
