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

use Override;
use OZONE\Core\Exceptions\RuntimeException;
use OZONE\Core\FS\S3\Interfaces\S3TransportInterface;
use OZONE\Core\FS\S3\S3Client;
use OZONE\Core\FS\S3\S3Request;
use OZONE\Core\FS\S3\S3Response;

/**
 * Class StreamWrapperTransport.
 *
 * PHP's HTTP stream wrapper. Always available, and the fallback when `ext-curl` is not loaded.
 *
 * It **cannot** stream a request body: the `content` context option is a string, so a stream body
 * is flattened. An upload therefore has to be split into parts to stay bounded, which
 * {@see S3Client} does based on {@see self::streamsRequestBody()}. The response
 * *is* streamed: the handle is read in chunks into the request's sink.
 */
final class StreamWrapperTransport implements S3TransportInterface
{
	public const CHUNK_SIZE = 1 << 19;

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public static function getName(): string
	{
		return 'stream-wrapper';
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public static function isSupported(): bool
	{
		return true;
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public static function streamsRequestBody(): bool
	{
		return false;
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function send(S3Request $request): S3Response
	{
		$body = \is_string($request->body) ? $request->body : (string) $request->body;

		$context = \stream_context_create([
			'http' => [
				'method'           => $request->method,
				'header'           => \implode("\r\n", \array_merge(['Connection: close'], $request->headerLines())),
				'content'          => $body,
				'ignore_errors'    => true,
				'follow_location'  => 0,
				'protocol_version' => 1.1,
				'timeout'          => $request->timeout,
			],
			'ssl'  => [
				'verify_peer'      => $request->verify_tls,
				'verify_peer_name' => $request->verify_tls,
			],
		]);

		$handle = @\fopen($request->url, 'rb', false, $context);

		if (false === $handle) {
			throw new RuntimeException(\sprintf('Unable to reach the object storage at "%s".', $request->url));
		}

		$meta = \stream_get_meta_data($handle);

		try {
			[$status, $headers] = self::parseHeaders($meta['wrapper_data'] ?? []);

			$content = '';

			if ($request->expect_body) {
				$sink = $request->sink;

				// An error response goes to the caller even when a sink was given: its XML says what
				// went wrong, and writing it into the destination file would corrupt it.
				$to_sink = null !== $sink && $status >= 200 && $status < 300;

				while (!\feof($handle)) {
					$chunk = \fread($handle, self::CHUNK_SIZE);

					if (false === $chunk || '' === $chunk) {
						break;
					}

					if ($to_sink) {
						$sink->write($chunk);
					} else {
						$content .= $chunk;
					}
				}
			}
		} finally {
			\fclose($handle);
		}

		return new S3Response($status, $headers, $content);
	}

	/**
	 * @param list<string> $lines the raw response header lines
	 *
	 * @return array{0: int, 1: array<string, string>}
	 */
	private static function parseHeaders(array $lines): array
	{
		$status  = 0;
		$headers = [];

		foreach ($lines as $line) {
			if (\preg_match('~^HTTP/\S+\s+(\d{3})~', $line, $m)) {
				// A new status line (e.g. after a 100 Continue): keep the last response only.
				$status  = (int) $m[1];
				$headers = [];

				continue;
			}

			$pos = \strpos($line, ':');

			if (false !== $pos) {
				$headers[\strtolower(\trim(\substr($line, 0, $pos)))] = \trim(\substr($line, $pos + 1));
			}
		}

		return [$status, $headers];
	}
}
