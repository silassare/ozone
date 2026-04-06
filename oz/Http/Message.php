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
use Override;
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
	 * @param $_name
	 * @param $_value
	 */
	public function __set($_name, $_value): void
	{
		// Do nothing
	}

	/**
	 * Just for pairing with __set.
	 *
	 * @param mixed $_name
	 */
	public function __get(mixed $_name): void
	{
		// Do nothing
	}

	/**
	 * Just for pairing with __set.
	 *
	 * @param mixed $_name
	 *
	 * @return false
	 */
	public function __isset(mixed $_name): bool
	{
		return false;
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function getProtocolVersion(): string
	{
		return $this->protocolVersion;
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
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
	#[Override]
	public function getHeaders(): array
	{
		return $this->headers->all();
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function hasHeader($name): bool
	{
		return $this->headers->has($name);
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function getHeader($name): array
	{
		return $this->headers->get($name, []);
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function getHeaderLine($name): string
	{
		return \implode(',', $this->headers->get($name, []));
	}

	/**
	 * Gets a header value as a boolean.
	 *
	 * Based on RFC 8941 representation of boolean values.
	 * ?1 is true, ?0 is false.
	 *
	 * @param string $name    The case-insensitive header name
	 * @param bool   $default The default value if the header is not set or invalid
	 *
	 * @return bool
	 */
	public function getHeaderAsBool(string $name, bool $default = false): bool
	{
		return $this->headers->getBool($name, $default);
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function withHeader($name, $value): static
	{
		$clone = clone $this;
		$clone->headers->set($name, $value);

		return $clone;
	}

	/**
	 * Returns a new instance with the specified boolean header.
	 *
	 * @param mixed $name
	 */
	public function withHeaderBool($name, bool $value): static
	{
		$clone = clone $this;
		$clone->headers->setBool($name, $value);

		return $clone;
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function withAddedHeader($name, $value): static
	{
		$clone = clone $this;
		$clone->headers->add($name, $value);

		return $clone;
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function withoutHeader($name): static
	{
		$clone = clone $this;
		$clone->headers->remove($name);

		return $clone;
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function getBody(): StreamInterface
	{
		return $this->body;
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function withBody(StreamInterface $body): static
	{
		$clone       = clone $this;
		$clone->body = $body;

		return $clone;
	}
}
