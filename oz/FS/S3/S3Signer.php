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

use SensitiveParameter;

/**
 * Class S3Signer.
 *
 * AWS Signature Version 4, as the S3 API (MinIO and the other S3-compatible services) expects it.
 */
final class S3Signer
{
	public const ALGORITHM          = 'AWS4-HMAC-SHA256';
	public const UNSIGNED_PAYLOAD   = 'UNSIGNED-PAYLOAD';
	public const EMPTY_PAYLOAD_HASH = 'e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855';

	public function __construct(
		private readonly string $access_key,
		#[SensitiveParameter]
		private readonly string $secret_key,
		private readonly string $region,
		private readonly string $service = 's3',
	) {}

	/**
	 * Signs a request.
	 *
	 * @param string                $method       the HTTP method
	 * @param string                $path         the request path, not encoded
	 * @param array<string, string> $query        the query parameters, not encoded
	 * @param array<string, string> $headers      the headers to sign, `host` included
	 * @param string                $payload_hash the hex SHA-256 of the body, or {@see self::UNSIGNED_PAYLOAD}
	 * @param int                   $time         the request time
	 *
	 * @return array<string, string> the headers (lower-cased names) with `x-amz-date`,
	 *                               `x-amz-content-sha256` and `authorization` added
	 */
	public function signHeaders(
		string $method,
		string $path,
		array $query,
		array $headers,
		string $payload_hash,
		int $time
	): array {
		$headers  = \array_change_key_case($headers, \CASE_LOWER);
		$amz_date = \gmdate('Ymd\THis\Z', $time);

		$headers['x-amz-date']           = $amz_date;
		$headers['x-amz-content-sha256'] = $payload_hash;

		\ksort($headers);

		$canonical_headers = '';

		foreach ($headers as $name => $value) {
			$canonical_headers .= $name . ':' . \trim((string) $value) . "\n";
		}

		$signed_headers = \implode(';', \array_keys($headers));
		$scope          = $this->scope($time);
		$canonical      = \implode("\n", [
			\strtoupper($method),
			self::encodePath($path),
			self::canonicalQuery($query),
			$canonical_headers,
			$signed_headers,
			$payload_hash,
		]);

		$headers['authorization'] = \sprintf(
			'%s Credential=%s/%s, SignedHeaders=%s, Signature=%s',
			self::ALGORITHM,
			$this->access_key,
			$scope,
			$signed_headers,
			$this->signature($canonical, $amz_date, $time)
		);

		return $headers;
	}

	/**
	 * The query of a presigned URL: only `host` is signed and the payload is not.
	 *
	 * @param string                $method  the HTTP method
	 * @param string                $host    the host the client will use, with its port when not the default
	 * @param string                $path    the request path, not encoded
	 * @param array<string, string> $query   extra query parameters, not encoded
	 * @param int                   $expires the URL lifetime, in seconds
	 * @param int                   $time    the signing time
	 *
	 * @return array<string, string> the query parameters, `X-Amz-Signature` included
	 */
	public function presignQuery(
		string $method,
		string $host,
		string $path,
		array $query,
		int $expires,
		int $time
	): array {
		$amz_date = \gmdate('Ymd\THis\Z', $time);

		$query += [
			'X-Amz-Algorithm'     => self::ALGORITHM,
			'X-Amz-Credential'    => $this->access_key . '/' . $this->scope($time),
			'X-Amz-Date'          => $amz_date,
			'X-Amz-Expires'       => (string) $expires,
			'X-Amz-SignedHeaders' => 'host',
		];

		$canonical = \implode("\n", [
			\strtoupper($method),
			self::encodePath($path),
			self::canonicalQuery($query),
			'host:' . $host . "\n",
			'host',
			self::UNSIGNED_PAYLOAD,
		]);

		$query['X-Amz-Signature'] = $this->signature($canonical, $amz_date, $time);

		return $query;
	}

	/**
	 * Encodes a query the way it is signed.
	 *
	 * @param array<string, string> $query
	 */
	public static function canonicalQuery(array $query): string
	{
		$encoded = [];

		foreach ($query as $name => $value) {
			$encoded[\rawurlencode((string) $name)] = \rawurlencode((string) $value);
		}

		\ksort($encoded, \SORT_STRING);

		$parts = [];

		foreach ($encoded as $name => $value) {
			$parts[] = $name . '=' . $value;
		}

		return \implode('&', $parts);
	}

	/**
	 * Encodes each segment of a path, keeping the slashes.
	 */
	public static function encodePath(string $path): string
	{
		return \implode('/', \array_map(\rawurlencode(...), \explode('/', $path)));
	}

	/**
	 * The credential scope: `<date>/<region>/<service>/aws4_request`.
	 */
	private function scope(int $time): string
	{
		return \gmdate('Ymd', $time) . '/' . $this->region . '/' . $this->service . '/aws4_request';
	}

	private function signature(string $canonical_request, string $amz_date, int $time): string
	{
		$string_to_sign = \implode("\n", [
			self::ALGORITHM,
			$amz_date,
			$this->scope($time),
			\hash('sha256', $canonical_request),
		]);

		$key = \hash_hmac('sha256', \gmdate('Ymd', $time), 'AWS4' . $this->secret_key, true);
		$key = \hash_hmac('sha256', $this->region, $key, true);
		$key = \hash_hmac('sha256', $this->service, $key, true);
		$key = \hash_hmac('sha256', 'aws4_request', $key, true);

		return \hash_hmac('sha256', $string_to_sign, $key);
	}
}
