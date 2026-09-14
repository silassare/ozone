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

use Generator;

/**
 * Class ResponseEmitter.
 *
 * Sends a response to the client: status line, headers, then the body in chunks, stopping
 * when the client goes away.
 */
final class ResponseEmitter
{
	private const CHUNK_SIZE = 4096;

	/**
	 * Final touches before sending: no body headers on an empty response, a
	 * `Content-Length` when the size is known, and byte ranges / conditional requests for a
	 * response that opted in with `Accept-Ranges`.
	 *
	 * Message's `with*()` methods return `static`, which psalm widens to Message here.
	 *
	 * @psalm-suppress MoreSpecificReturnType, LessSpecificReturnStatement, ArgumentTypeCoercion
	 */
	public static function prepare(Response $response, Request $request): Response
	{
		if ($response->isEmpty()) {
			return $response->withoutHeader('Content-Type')
				->withoutHeader('Content-Length');
		}

		$size = $response->getBody()->getSize();

		if (null !== $size) {
			$response = $response->withHeader('Content-Length', (string) $size);
		}

		if ($response->hasHeader('Accept-Ranges')) {
			$response = RangeResponse::apply($request, $response);
		}

		return $response;
	}

	/**
	 * Sends the response. Headers are skipped when already sent.
	 */
	public static function emit(Response $response): void
	{
		if (!\headers_sent()) {
			self::emitHeaders($response);
		}

		if (!$response->isEmpty()) {
			self::emitBody($response);
		}
	}

	/**
	 * The body of a prepared response, chunk by chunk: what is sent, and nothing more.
	 *
	 * A 206 body is left where {@see RangeResponse::apply()} positioned it and is read for
	 * `Content-Length` bytes only; any other seekable body is rewound first. The body is never
	 * loaded whole, so a response sink can stream a large file with it.
	 *
	 * @param Response $response   the prepared response
	 * @param int      $chunk_size the largest chunk to read at once
	 *
	 * @return Generator<int, string>
	 */
	public static function bodyChunks(Response $response, int $chunk_size = self::CHUNK_SIZE): Generator
	{
		if ($response->isEmpty()) {
			return;
		}

		$body = $response->getBody();

		// A 206 body was already positioned at the range start by RangeResponse::apply().
		if ($body->isSeekable() && 206 !== $response->getStatusCode()) {
			$body->rewind();
		}

		$remaining = (int) $response->getHeaderLine('Content-Length') ?: $body->getSize();

		while (!$body->eof() && (null === $remaining || $remaining > 0)) {
			$data = $body->read(null === $remaining ? $chunk_size : \min($chunk_size, $remaining));

			if ('' === $data) {
				// A stream that reports no end but has nothing left: stop instead of spinning.
				break;
			}

			if (null !== $remaining) {
				$remaining -= \strlen($data);
			}

			yield $data;
		}
	}

	private static function emitHeaders(Response $response): void
	{
		$status_code = $response->getStatusCode();

		\header(\sprintf(
			'HTTP/%s %s %s',
			$response->getProtocolVersion(),
			$status_code,
			$response->getReasonPhrase()
		));

		foreach ($response->getHeaders() as $name => $values) {
			/** @var string $name */
			$replace = (0 === \strcasecmp($name, 'Content-Type'));

			foreach ($values as $value) {
				\header(\sprintf('%s: %s', $name, $value), $replace, $status_code);
			}
		}
	}

	private static function emitBody(Response $response): void
	{
		foreach (self::bodyChunks($response) as $data) {
			echo $data;

			if (\CONNECTION_NORMAL !== \connection_status()) {
				$response->getBody()->close();

				break;
			}
		}
	}
}
