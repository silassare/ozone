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

use InvalidArgumentException;
use OZONE\Core\Exceptions\RuntimeException;
use OZONE\Core\FS\S3\Interfaces\S3TransportInterface;
use OZONE\Core\FS\S3\Transport\CurlTransport;
use OZONE\Core\FS\S3\Transport\StreamWrapperTransport;
use Psr\Http\Message\StreamInterface;
use SensitiveParameter;
use SimpleXMLElement;
use Throwable;

/**
 * Class S3Client.
 *
 * The few S3 API calls OZone's object storage needs.
 *
 * Nothing here holds a whole object in memory. A download is written into the caller's stream as it
 * arrives; an upload is streamed when the transport can ({@see CurlTransport}) and otherwise split
 * into parts, so peak memory is one part whatever the file size. The payload hash is computed by
 * reading a seekable body once, without buffering it, and falls back to `UNSIGNED-PAYLOAD` only for
 * a body that cannot be read twice.
 */
final class S3Client
{
	/**
	 * Default size of a multipart part, and the size above which an upload is split.
	 *
	 * S3 rejects a non-final part below 5 MiB, so this is also the floor for {@see self::$part_size}.
	 */
	public const DEFAULT_PART_SIZE = 16 << 20;
	public const MIN_PART_SIZE     = 5 << 20;
	public const MAX_PARTS         = 10000;

	private readonly S3Signer $signer;

	private readonly S3TransportInterface $transport;

	private readonly string $scheme;

	private readonly string $host;

	private readonly string $base_path;

	private readonly int $part_size;

	/**
	 * @param string                    $endpoint   the service URL, e.g. `http://minio:9000`
	 * @param string                    $region     the region (MinIO accepts any, `us-east-1` by default)
	 * @param bool                      $path_style whether the bucket is in the path (MinIO) or in the host name
	 * @param float                     $timeout    the connection and read timeout, in seconds
	 * @param bool                      $verify_tls whether to verify the TLS certificate of an `https` endpoint
	 * @param null|int                  $part_size  the multipart part size, in bytes
	 * @param null|S3TransportInterface $transport  the transport; the best available one by default
	 */
	public function __construct(
		string $endpoint,
		string $region,
		string $access_key,
		#[SensitiveParameter]
		string $secret_key,
		private readonly bool $path_style = true,
		private readonly float $timeout = 30.0,
		private readonly bool $verify_tls = true,
		?int $part_size = null,
		?S3TransportInterface $transport = null,
	) {
		[$this->scheme, $this->host, $this->base_path] = self::parseEndpoint($endpoint);

		$this->signer    = new S3Signer($access_key, $secret_key, $region);
		$this->transport = $transport ?? self::defaultTransport();
		$this->part_size = \max(self::MIN_PART_SIZE, $part_size ?? self::DEFAULT_PART_SIZE);
	}

	/**
	 * The best transport available here: cURL when `ext-curl` is loaded, the stream wrapper else.
	 */
	public static function defaultTransport(): S3TransportInterface
	{
		return CurlTransport::isSupported() ? new CurlTransport() : new StreamWrapperTransport();
	}

	/**
	 * The transport in use, for diagnostics.
	 */
	public function getTransport(): S3TransportInterface
	{
		return $this->transport;
	}

	/**
	 * The multipart part size in use.
	 */
	public function getPartSize(): int
	{
		return $this->part_size;
	}

	/**
	 * Stores an object, replacing any previous one with the same key.
	 *
	 * Kept for a body already in memory; prefer {@see self::putObjectFromStream()}, which never
	 * needs the whole object at once.
	 */
	public function putObject(string $bucket, string $key, string $body, string $content_type): void
	{
		$this->send('PUT', $bucket, $key, [], $body, ['content-type' => $content_type]);
	}

	/**
	 * Stores an object from a stream, in one request or in parts.
	 *
	 * A single request is used when the size is known, it is not above the part size, and the
	 * transport can stream it; otherwise the stream is uploaded part by part, so peak memory is one
	 * part however large the object is.
	 *
	 * @return int the number of bytes stored
	 */
	public function putObjectFromStream(
		string $bucket,
		string $key,
		StreamInterface $body,
		string $content_type
	): int {
		$size = $body->getSize();

		if (null !== $size && $size <= $this->part_size) {
			if ($body->isSeekable()) {
				$body->rewind();
			}

			// Small enough to send in one request. Read it into a string for a transport that
			// cannot stream a body: bounded by the part size, which is the point.
			$payload = $this->transport::streamsRequestBody() ? $body : (string) $body;

			$this->send('PUT', $bucket, $key, [], $payload, ['content-type' => $content_type]);

			return $size;
		}

		return $this->putObjectMultipart($bucket, $key, $body, $content_type);
	}

	/**
	 * Reads an object into memory.
	 *
	 * Prefer {@see self::getObjectTo()} for anything that is not known to be small.
	 */
	public function getObject(string $bucket, string $key): string
	{
		return $this->send('GET', $bucket, $key)->body;
	}

	/**
	 * Reads an object into a stream, in chunks.
	 *
	 * @return int the number of bytes written, from the response `Content-Length`
	 */
	public function getObjectTo(string $bucket, string $key, StreamInterface $sink): int
	{
		$response = $this->send('GET', $bucket, $key, [], '', [], $sink);

		return $response->contentLength() ?? 0;
	}

	/**
	 * Reads the headers of an object, or null when there is no such object.
	 *
	 * @return null|array<string, string>
	 */
	public function headObject(string $bucket, string $key): ?array
	{
		$response = $this->request('HEAD', $bucket, $key);

		if (404 === $response->status) {
			return null;
		}

		$this->assertSuccess('HEAD', $response);

		return $response->headers;
	}

	/**
	 * The size of an object, or null when there is no such object.
	 */
	public function objectSize(string $bucket, string $key): ?int
	{
		$headers = $this->headObject($bucket, $key);

		if (null === $headers) {
			return null;
		}

		return isset($headers['content-length']) ? (int) $headers['content-length'] : null;
	}

	/**
	 * Deletes an object; deleting a missing one succeeds.
	 */
	public function deleteObject(string $bucket, string $key): void
	{
		$this->send('DELETE', $bucket, $key);
	}

	/**
	 * Whether a bucket exists and is reachable with these credentials.
	 */
	public function bucketExists(string $bucket): bool
	{
		$response = $this->request('HEAD', $bucket);

		if (404 === $response->status) {
			return false;
		}

		$this->assertSuccess('HEAD', $response);

		return true;
	}

	/**
	 * Creates a bucket, unless it already exists.
	 */
	public function createBucket(string $bucket): void
	{
		if (!$this->bucketExists($bucket)) {
			$this->send('PUT', $bucket);
		}
	}

	/**
	 * The plain URL of an object, readable without credentials when the bucket allows anonymous reads.
	 *
	 * @param null|string $endpoint the endpoint the client will use, when not this client's
	 */
	public function objectUrl(string $bucket, string $key, ?string $endpoint = null): string
	{
		[$scheme, $host, $base_path] = null === $endpoint
			? [$this->scheme, $this->host, $this->base_path]
			: self::parseEndpoint($endpoint);

		[$host, $path] = $this->locate($host, $base_path, $bucket, $key);

		return $scheme . '://' . $host . S3Signer::encodePath($path);
	}

	/**
	 * A URL that grants `$method` on an object, without credentials, for `$ttl` seconds.
	 *
	 * @param null|string $endpoint the endpoint the client will use, when not this client's
	 *                              (e.g. the public URL of an internal MinIO)
	 */
	public function presignedUrl(
		string $method,
		string $bucket,
		string $key,
		int $ttl,
		?string $endpoint = null,
		?int $time = null
	): string {
		[$scheme, $host, $base_path] = null === $endpoint
			? [$this->scheme, $this->host, $this->base_path]
			: self::parseEndpoint($endpoint);

		[$host, $path] = $this->locate($host, $base_path, $bucket, $key);
		$query         = $this->signer->presignQuery($method, $host, $path, [], $ttl, $time ?? \time());

		return $scheme . '://' . $host . S3Signer::encodePath($path) . '?' . S3Signer::canonicalQuery($query);
	}

	/**
	 * Uploads a stream as a multipart upload, one part at a time.
	 *
	 * A failed part aborts the upload, so the service does not keep the parts already sent.
	 *
	 * @return int the number of bytes stored
	 */
	private function putObjectMultipart(
		string $bucket,
		string $key,
		StreamInterface $body,
		string $content_type
	): int {
		if ($body->isSeekable()) {
			$body->rewind();
		}

		$upload_id = $this->createMultipartUpload($bucket, $key, $content_type);
		$parts     = [];
		$total     = 0;

		try {
			$number = 1;

			while (!$body->eof()) {
				$chunk = self::readUpTo($body, $this->part_size);

				if ('' === $chunk) {
					break;
				}

				if ($number > self::MAX_PARTS) {
					throw new RuntimeException(\sprintf(
						'Object too large: more than %d parts of %d bytes.',
						self::MAX_PARTS,
						$this->part_size
					));
				}

				$parts[$number] = $this->uploadPart($bucket, $key, $upload_id, $number, $chunk);
				$total += \strlen($chunk);
				++$number;
			}

			if (empty($parts)) {
				// S3 refuses a multipart upload with no part; an empty object is a plain PUT.
				$this->abortMultipartUpload($bucket, $key, $upload_id);
				$this->putObject($bucket, $key, '', $content_type);

				return 0;
			}

			$this->completeMultipartUpload($bucket, $key, $upload_id, $parts);
		} catch (Throwable $t) {
			try {
				$this->abortMultipartUpload($bucket, $key, $upload_id);
			} catch (Throwable) {
				// The upload failed already; report that, not the cleanup.
			}

			throw $t;
		}

		return $total;
	}

	/**
	 * Starts a multipart upload and returns its id.
	 */
	private function createMultipartUpload(string $bucket, string $key, string $content_type): string
	{
		$response = $this->send('POST', $bucket, $key, ['uploads' => ''], '', ['content-type' => $content_type]);
		$xml      = self::parseXml($response->body);
		$id       = (string) ($xml->UploadId ?? '');

		if ('' === $id) {
			throw new RuntimeException('The object storage returned no upload id for the multipart upload.');
		}

		return $id;
	}

	/**
	 * Uploads one part and returns its ETag.
	 */
	private function uploadPart(string $bucket, string $key, string $upload_id, int $number, string $chunk): string
	{
		$response = $this->send('PUT', $bucket, $key, [
			'partNumber' => (string) $number,
			'uploadId'   => $upload_id,
		], $chunk);

		$etag = $response->header('etag');

		if (null === $etag || '' === $etag) {
			throw new RuntimeException(\sprintf('The object storage returned no ETag for part %d.', $number));
		}

		return $etag;
	}

	/**
	 * Completes a multipart upload.
	 *
	 * @param array<int, string> $parts part number => ETag
	 */
	private function completeMultipartUpload(string $bucket, string $key, string $upload_id, array $parts): void
	{
		\ksort($parts);

		$xml = '<CompleteMultipartUpload>';

		foreach ($parts as $number => $etag) {
			$xml .= \sprintf(
				'<Part><PartNumber>%d</PartNumber><ETag>%s</ETag></Part>',
				$number,
				\htmlspecialchars($etag, \ENT_XML1 | \ENT_QUOTES, 'UTF-8')
			);
		}

		$xml .= '</CompleteMultipartUpload>';

		$response = $this->send('POST', $bucket, $key, ['uploadId' => $upload_id], $xml, [
			'content-type' => 'application/xml',
		]);

		// CompleteMultipartUpload can report a failure inside a 200 response, so the body decides.
		$body = self::parseXml($response->body);

		if ('Error' === $body->getName() || isset($body->Code)) {
			throw new RuntimeException(\sprintf(
				'The object storage failed to complete the multipart upload (%s).',
				(string) ($body->Code ?? 'unknown')
			));
		}
	}

	/**
	 * Abandons a multipart upload, so its parts are not kept.
	 */
	private function abortMultipartUpload(string $bucket, string $key, string $upload_id): void
	{
		$this->send('DELETE', $bucket, $key, ['uploadId' => $upload_id]);
	}

	/**
	 * Sends a request and fails on an error response.
	 *
	 * @param array<string, string> $query
	 * @param array<string, string> $headers
	 */
	private function send(
		string $method,
		string $bucket,
		string $key = '',
		array $query = [],
		StreamInterface|string $body = '',
		array $headers = [],
		?StreamInterface $sink = null
	): S3Response {
		$response = $this->request($method, $bucket, $key, $body, $headers, $query, $sink);

		$this->assertSuccess($method, $response);

		return $response;
	}

	/**
	 * Signs and sends a request.
	 *
	 * @param array<string, string> $headers
	 * @param array<string, string> $query
	 */
	private function request(
		string $method,
		string $bucket,
		string $key = '',
		StreamInterface|string $body = '',
		array $headers = [],
		array $query = [],
		?StreamInterface $sink = null
	): S3Response {
		[$host, $path] = $this->locate($this->host, $this->base_path, $bucket, $key);

		$headers['host'] = $host;
		$signed          = $this->signer->signHeaders(
			$method,
			$path,
			$query,
			$headers,
			$this->payloadHash($body),
			\time()
		);

		$url = $this->scheme . '://' . $host . S3Signer::encodePath($path);

		if (!empty($query)) {
			$url .= '?' . S3Signer::canonicalQuery($query);
		}

		return $this->transport->send(new S3Request(
			$method,
			$url,
			$signed,
			$body,
			$sink,
			$this->timeout,
			$this->verify_tls,
			'HEAD' !== $method,
		));
	}

	/**
	 * The hex SHA-256 of a request body.
	 *
	 * A seekable stream is read once and rewound, so the hash costs a pass over the data but never
	 * a copy of it. Only a body that cannot be read twice is sent as `UNSIGNED-PAYLOAD`, which some
	 * services accept over HTTPS only.
	 */
	private function payloadHash(StreamInterface|string $body): string
	{
		if (\is_string($body)) {
			return '' === $body ? S3Signer::EMPTY_PAYLOAD_HASH : \hash('sha256', $body);
		}

		if (0 === $body->getSize()) {
			return S3Signer::EMPTY_PAYLOAD_HASH;
		}

		if (!$body->isSeekable()) {
			return S3Signer::UNSIGNED_PAYLOAD;
		}

		$body->rewind();

		$context = \hash_init('sha256');

		while (!$body->eof()) {
			$chunk = $body->read(CurlTransport::CHUNK_SIZE);

			if ('' === $chunk) {
				break;
			}

			\hash_update($context, $chunk);
		}

		$body->rewind();

		return \hash_final($context);
	}

	private function assertSuccess(string $method, S3Response $response): void
	{
		if ($response->isSuccess()) {
			return;
		}

		$code = null;

		if (\preg_match('~<Code>([^<]+)</Code>~', $response->body, $m)) {
			$code = $m[1];
		}

		$message = \sprintf(
			'Object storage %s request failed (%d%s).',
			$method,
			$response->status,
			$code ? ', ' . $code : ''
		);

		throw new RuntimeException($message, [
			'_status' => $response->status,
			'_code'   => $code,
		]);
	}

	/**
	 * The host and the path of an object.
	 *
	 * @return array{0: string, 1: string}
	 */
	private function locate(string $host, string $base_path, string $bucket, string $key): array
	{
		$key = \ltrim($key, '/');

		if ($this->path_style) {
			return [$host, $base_path . '/' . $bucket . ('' === $key ? '' : '/' . $key)];
		}

		return [$bucket . '.' . $host, $base_path . '/' . $key];
	}

	/**
	 * Reads up to `$length` bytes from a stream.
	 */
	private static function readUpTo(StreamInterface $source, int $length): string
	{
		$read = '';

		while (\strlen($read) < $length && !$source->eof()) {
			$chunk = $source->read($length - \strlen($read));

			if ('' === $chunk) {
				break;
			}

			$read .= $chunk;
		}

		return $read;
	}

	/**
	 * Parses an S3 XML response.
	 */
	private static function parseXml(string $body): SimpleXMLElement
	{
		$previous = \libxml_use_internal_errors(true);

		try {
			$xml = \simplexml_load_string($body);
		} finally {
			\libxml_clear_errors();
			\libxml_use_internal_errors($previous);
		}

		if (false === $xml) {
			throw new RuntimeException('The object storage returned a malformed XML response.');
		}

		return $xml;
	}

	/**
	 * @return array{0: string, 1: string, 2: string} the scheme, the host (with its port when not
	 *                                                the default one) and the base path
	 */
	private static function parseEndpoint(string $endpoint): array
	{
		$parts  = \parse_url($endpoint);
		$scheme = \strtolower($parts['scheme'] ?? '');

		if (!\in_array($scheme, ['http', 'https'], true) || empty($parts['host'])) {
			throw new InvalidArgumentException(\sprintf('Invalid object storage endpoint: "%s".', $endpoint));
		}

		$host = $parts['host'];
		$port = $parts['port'] ?? null;

		if (null !== $port && !('http' === $scheme && 80 === $port) && !('https' === $scheme && 443 === $port)) {
			$host .= ':' . $port;
		}

		return [$scheme, $host, \rtrim($parts['path'] ?? '', '/')];
	}
}
