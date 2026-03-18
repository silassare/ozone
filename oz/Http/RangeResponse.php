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

/**
 * Class RangeResponse.
 *
 * Handles HTTP Range requests and conditional requests (ETag / If-None-Match).
 *
 * Intended to be used with any response that has `Accept-Ranges: bytes` set.
 * The response must already have `Content-Length` set to the full resource size
 * and a seekable, readable body before calling {@see self::apply()}.
 *
 * The caller (`Context::fixResponse()`) is responsible for setting
 * `Content-Length` from the full body size before invoking this class.
 * For 206 responses this class overrides `Content-Length` with the chunk size;
 * `Context::respond()` honours `Content-Length` when emitting the body, so
 * the emit loop naturally stops after the requested byte range without loading
 * the entire file into memory.
 */
final class RangeResponse
{
	/**
	 * Apply range and conditional request logic to a 200 response.
	 *
	 * Behaviour:
	 * - If the response has an `ETag` header and the request sends a matching
	 *   `If-None-Match` header (GET / HEAD only), a 304 Not Modified response
	 *   with an empty body is returned.
	 * - If the request includes a `Range` header (GET only) and the response
	 *   body is seekable, the body is seeked to the requested start position
	 *   and a 206 Partial Content response is returned.
	 * - If the requested range is unsatisfiable, a 416 Range Not Satisfiable
	 *   response with an empty body and a `Content-Range: bytes * / {total}` header
	 *   is returned.
	 * - All other cases return the response unchanged.
	 *
	 * @param Request  $request
	 * @param Response $response a 200 response with Content-Length and Accept-Ranges: bytes already set
	 *
	 * @return Response
	 */
	public static function apply(Request $request, Response $response): Response
	{
		if (200 !== $response->getStatusCode()) {
			return $response;
		}

		$method = $request->getMethod();

		// Conditional request: If-None-Match -> 304 Not Modified (GET / HEAD only).
		if ($response->hasHeader('ETag') && \in_array($method, ['GET', 'HEAD'], true)) {
			$etag        = $response->getHeaderLine('ETag');
			$ifNoneMatch = \trim($request->getHeaderLine('If-None-Match'));

			if ('' !== $ifNoneMatch && self::etagMatches($ifNoneMatch, $etag)) {
				return $response->withStatus(304)
					->withoutHeader('Content-Length')
					->withoutHeader('Content-Type')
					->withBody(Body::create());
			}
		}

		// Range request (GET only).
		$rangeHeader = \trim($request->getHeaderLine('Range'));

		if ('' === $rangeHeader || 'GET' !== $method) {
			return $response;
		}

		$body = $response->getBody();

		if (!$body->isSeekable() || !$body->isReadable()) {
			return $response;
		}

		$totalSize = (int) $response->getHeaderLine('Content-Length');

		if ($totalSize <= 0) {
			return $response;
		}

		$range = self::parseRange($rangeHeader, $totalSize);

		if (null === $range) {
			// Unsatisfiable or multi-range request.
			return $response->withStatus(416)
				->withHeader('Content-Range', "bytes */{$totalSize}")
				->withoutHeader('Content-Length')
				->withBody(Body::create());
		}

		[$start, $end] = $range;
		$chunkSize     = $end - $start + 1;

		// Seek to the range start. Context::respond() will read exactly
		// $chunkSize bytes because Content-Length is overridden below.
		$body->seek($start);

		return $response
			->withStatus(206)
			->withHeader('Content-Range', "bytes {$start}-{$end}/{$totalSize}")
			->withHeader('Content-Length', (string) $chunkSize)
			->withBody($body);
	}

	/**
	 * Check whether an `If-None-Match` header value matches an `ETag`.
	 *
	 * Supports the `*` wildcard and weak-ETag comparison (W/ prefix stripped).
	 *
	 * @param string $ifNoneMatch the `If-None-Match` value from the request
	 * @param string $etag        the `ETag` value from the response
	 *
	 * @return bool
	 */
	private static function etagMatches(string $ifNoneMatch, string $etag): bool
	{
		if ('*' === $ifNoneMatch) {
			return true;
		}

		$normalize = static fn (string $t): string => \ltrim(\trim($t), 'W/');
		$tags      = \array_map($normalize, \explode(',', $ifNoneMatch));

		return \in_array($normalize($etag), $tags, true);
	}

	/**
	 * Parse a `Range` header value and return [start, end] or null if invalid / unsatisfiable.
	 *
	 * Only single-range `bytes=start-end`, `bytes=start-`, and `bytes=-suffix`
	 * are supported.  Multi-range requests are treated as unsatisfiable so the
	 * client falls back to a plain full-file request.
	 *
	 * @param string $header    the `Range` header value
	 * @param int    $totalSize total resource size in bytes
	 *
	 * @return null|array{int, int} [start, end] on success, null if unsatisfiable or multi-range
	 */
	private static function parseRange(string $header, int $totalSize): ?array
	{
		if (!\preg_match('/^bytes=(\d*)-(\d*)$/', $header, $m)) {
			// Multi-range or invalid format.
			return null;
		}

		$startStr = $m[1];
		$endStr   = $m[2];

		if ('' === $startStr && '' === $endStr) {
			return null;
		}

		if ('' === $startStr) {
			// Suffix range: bytes=-N (last N bytes).
			$suffix = (int) $endStr;

			if (0 === $suffix) {
				return null;
			}

			$start = \max(0, $totalSize - $suffix);
			$end   = $totalSize - 1;
		} elseif ('' === $endStr) {
			// Open-ended range: bytes=N-
			$start = (int) $startStr;
			$end   = $totalSize - 1;
		} else {
			$start = (int) $startStr;
			$end   = (int) $endStr;
		}

		if ($start > $end || $start >= $totalSize) {
			return null;
		}

		$end = \min($end, $totalSize - 1);

		return [$start, $end];
	}
}
