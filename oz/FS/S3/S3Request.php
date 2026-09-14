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

namespace OZONE\Core\FS\S3;

use Psr\Http\Message\StreamInterface;

/**
 * Class S3Request.
 *
 * One signed request, as a transport receives it.
 *
 * `body` and `sink` are what keep an object out of memory: a stream body is sent as it is read, and
 * a sink is written as the response arrives. A transport that cannot stream one of them falls back
 * to a string, so a caller must still bound what it passes.
 */
final class S3Request
{
	/**
	 * @param string                 $method      the HTTP method
	 * @param string                 $url         the full URL, path already encoded
	 * @param array<string, string>  $headers     the signed headers, `host` included
	 * @param StreamInterface|string $body        the request body
	 * @param null|StreamInterface   $sink        where to write the response body; null to return it
	 * @param float                  $timeout     the connection and read timeout, in seconds
	 * @param bool                   $verify_tls  whether to verify the TLS certificate
	 * @param bool                   $expect_body false for HEAD, whose response body is discarded
	 */
	public function __construct(
		public readonly string $method,
		public readonly string $url,
		public readonly array $headers = [],
		public readonly StreamInterface|string $body = '',
		public readonly ?StreamInterface $sink = null,
		public readonly float $timeout = 30.0,
		public readonly bool $verify_tls = true,
		public readonly bool $expect_body = true,
	) {}

	/**
	 * The body length when it is known, null when it is not.
	 */
	public function bodySize(): ?int
	{
		return \is_string($this->body) ? \strlen($this->body) : $this->body->getSize();
	}

	/**
	 * The headers as `Name: value` lines, with `Content-Length` when the size is known.
	 *
	 * @return list<string>
	 */
	public function headerLines(): array
	{
		$lines = [];
		$size  = $this->bodySize();

		if (null !== $size) {
			$lines[] = 'Content-Length: ' . $size;
		}

		foreach ($this->headers as $name => $value) {
			$lines[] = $name . ': ' . $value;
		}

		return $lines;
	}
}
