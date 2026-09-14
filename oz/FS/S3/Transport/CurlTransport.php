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

namespace OZONE\Core\FS\S3\Transport;

use CurlHandle;
use Override;
use OZONE\Core\Exceptions\RuntimeException;
use OZONE\Core\FS\S3\Interfaces\S3TransportInterface;
use OZONE\Core\FS\S3\S3Request;
use OZONE\Core\FS\S3\S3Response;

/**
 * Class CurlTransport.
 *
 * cURL, used whenever `ext-curl` is loaded. It is the only transport that streams a request body,
 * so an object of any size is uploaded through a fixed buffer, and it sends `Expect: 100-continue`
 * on a large body so a rejected upload fails before the bytes are sent.
 *
 * `ext-curl` is a `suggest`, not a `require`: {@see StreamWrapperTransport} does the same work
 * through part-sized strings when it is missing.
 */
final class CurlTransport implements S3TransportInterface
{
	public const CHUNK_SIZE = 1 << 19;

	/**
	 * Body size above which `Expect: 100-continue` is sent.
	 */
	public const EXPECT_CONTINUE_SIZE = 1 << 20;

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public static function getName(): string
	{
		return 'curl';
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public static function isSupported(): bool
	{
		return \extension_loaded('curl') && \function_exists('curl_init');
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public static function streamsRequestBody(): bool
	{
		return true;
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function send(S3Request $request): S3Response
	{
		$handle = \curl_init();

		if (false === $handle) {
			throw new RuntimeException('Unable to initialize cURL for the object storage request.');
		}

		$headers = [];
		$body    = '';
		$size    = $request->bodySize();
		$sink    = $request->sink;
		$status  = 0;

		$lines = $request->headerLines();

		// curl adds Expect: 100-continue on its own above 1 KB, which costs a round trip on every
		// small request; keep it only where it pays -- a large body rejected before it is sent.
		$lines[] = (null !== $size && $size >= self::EXPECT_CONTINUE_SIZE) ? 'Expect: 100-continue' : 'Expect:';

		\curl_setopt_array($handle, [
			\CURLOPT_URL            => $request->url,
			\CURLOPT_CUSTOMREQUEST  => $request->method,
			\CURLOPT_HTTPHEADER     => $lines,
			\CURLOPT_RETURNTRANSFER => false,
			\CURLOPT_FOLLOWLOCATION => false,
			\CURLOPT_CONNECTTIMEOUT => (int) \max(1, \ceil($request->timeout)),
			\CURLOPT_TIMEOUT        => (int) \max(1, \ceil($request->timeout)),
			\CURLOPT_SSL_VERIFYPEER => $request->verify_tls,
			\CURLOPT_SSL_VERIFYHOST => $request->verify_tls ? 2 : 0,
			\CURLOPT_NOBODY         => !$request->expect_body,
			\CURLOPT_HEADERFUNCTION => static function ($ch, string $line) use (&$headers, &$status): int {
				$length = \strlen($line);

				if (\preg_match('~^HTTP/\S+\s+(\d{3})~', $line, $m)) {
					// A new status line (a 100 Continue, or a redirect): keep the last one only.
					$status  = (int) $m[1];
					$headers = [];

					return $length;
				}

				$pos = \strpos($line, ':');

				if (false !== $pos) {
					$headers[\strtolower(\trim(\substr($line, 0, $pos)))] = \trim(\substr($line, $pos + 1));
				}

				return $length;
			},
		]);

		self::setBody($handle, $request, $size);

		// An error response is returned to the caller even when a sink was given: its XML says what
		// went wrong, and writing it into the destination file would corrupt it.
		$write = static function ($ch, string $chunk) use ($sink, &$body, &$status): int {
			// $status is set by the header callback above, which runs first; psalm only sees its
			// initial 0.
			/** @psalm-suppress TypeDoesNotContainType */
			if (null !== $sink && $status >= 200 && $status < 300) {
				$sink->write($chunk);
			} else {
				$body .= $chunk;
			}

			return \strlen($chunk);
		};

		\curl_setopt($handle, \CURLOPT_WRITEFUNCTION, $write);

		$ok    = \curl_exec($handle);
		$error = \curl_error($handle);

		// No curl_close(): a no-op since PHP 8.0, and deprecated in 8.5. The handle is freed when it
		// goes out of scope.
		unset($handle);

		if (false === $ok) {
			throw new RuntimeException(\sprintf(
				'Unable to reach the object storage at "%s": %s.',
				$request->url,
				'' === $error ? 'unknown cURL error' : $error
			));
		}

		return new S3Response($status, $headers, $body);
	}

	/**
	 * Feeds the request body to cURL: read from the stream as it is sent, or in one piece when it
	 * is already a string.
	 */
	private static function setBody(CurlHandle $handle, S3Request $request, ?int $size): void
	{
		if ('GET' === $request->method || 'HEAD' === $request->method) {
			return;
		}

		if (\is_string($request->body)) {
			\curl_setopt($handle, \CURLOPT_POSTFIELDS, $request->body);

			return;
		}

		$source = $request->body;

		if ($source->isSeekable()) {
			$source->rewind();
		}

		\curl_setopt($handle, \CURLOPT_UPLOAD, true);

		if (null !== $size) {
			// CURLOPT_INFILESIZE, not _LARGE: PHP does not expose the latter. The value is a PHP
			// int, so 64-bit platforms still handle objects past 2 GB.
			\curl_setopt($handle, \CURLOPT_INFILESIZE, $size);
		}

		\curl_setopt($handle, \CURLOPT_READFUNCTION, static function ($ch, $fd, int $length) use ($source): string {
			if ($source->eof()) {
				return '';
			}

			return $source->read($length);
		});
	}
}
