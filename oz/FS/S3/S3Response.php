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

/**
 * Class S3Response.
 *
 * `body` is empty when the request carried a sink: the bytes went there instead, so a response
 * never holds an object in memory unless the caller asked for it.
 */
final class S3Response
{
	/**
	 * @param int                   $status  the HTTP status
	 * @param array<string, string> $headers the response headers, names lower-cased
	 * @param string                $body    the response body, empty when it went to a sink
	 */
	public function __construct(
		public readonly int $status,
		public readonly array $headers = [],
		public readonly string $body = '',
	) {}

	/**
	 * A response header, or null.
	 */
	public function header(string $name): ?string
	{
		return $this->headers[\strtolower($name)] ?? null;
	}

	/**
	 * The `Content-Length` of the response, or null when it was not sent.
	 */
	public function contentLength(): ?int
	{
		$value = $this->header('content-length');

		return null === $value ? null : (int) $value;
	}

	/**
	 * Whether the status is a 2xx.
	 */
	public function isSuccess(): bool
	{
		return $this->status >= 200 && $this->status < 300;
	}
}
