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

namespace OZONE\Core\FS\Drivers;

use Override;
use OZONE\Core\App\Context;
use OZONE\Core\App\Settings;
use OZONE\Core\Auth\Providers\FileAccessAuthorizationProvider;
use OZONE\Core\Db\OZFile;
use OZONE\Core\Exceptions\RuntimeException;
use OZONE\Core\Exceptions\UnauthorizedException;
use OZONE\Core\FS\FileStream;
use OZONE\Core\FS\FS;
use OZONE\Core\FS\Interfaces\StorageInterface;
use OZONE\Core\FS\S3\S3Client;
use OZONE\Core\FS\Traits\FileResponseTrait;
use OZONE\Core\Http\Body;
use OZONE\Core\Http\Response;
use OZONE\Core\Http\UploadedFile;
use OZONE\Core\Http\Uri;
use Psr\Http\Message\StreamInterface;

/**
 * Class MinioStorage.
 *
 * Stores files in MinIO, or any service speaking the S3 API, as configured in `oz.files.minio`:
 * one bucket and key prefix per storage slot.
 *
 * Objects are streamed both ways: an upload goes through {@see S3Client::putObjectFromStream()},
 * which splits it into parts when needed, and a download is written into a stream as it arrives, so
 * peak memory does not follow the file size. S3 cannot append: {@see self::append()} and
 * {@see self::prepend()} rewrite the whole object, through a temporary file rather than in memory.
 */
final class MinioStorage implements StorageInterface
{
	use FileResponseTrait;

	/**
	 * Bytes an object is allowed to take in memory before it spools to a temporary file.
	 */
	public const MEMORY_SPOOL_SIZE = 2 << 20;

	/**
	 * Chunk size used when copying one stream into another.
	 */
	public const COPY_CHUNK_SIZE = 1 << 19;

	private function __construct(
		private readonly string $name,
		private readonly S3Client $client,
		private readonly string $bucket,
		private readonly string $prefix,
		private readonly bool $public,
	) {}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public static function get(string $name): static
	{
		$buckets = Settings::get('oz.files.minio', 'OZ_MINIO_BUCKETS', []);
		$options = \is_array($buckets) ? ($buckets[$name] ?? null) : null;

		if (!\is_array($options) || empty($options['bucket'])) {
			throw new RuntimeException(\sprintf(
				'No MinIO bucket is configured for the storage "%s" (OZ_MINIO_BUCKETS in oz.files.minio).',
				$name
			));
		}

		return new self(
			$name,
			self::client(),
			(string) $options['bucket'],
			\trim((string) ($options['prefix'] ?? ''), '/'),
			(bool) ($options['public'] ?? false)
		);
	}

	/**
	 * The client of the service configured in `oz.files.minio`.
	 */
	public static function client(): S3Client
	{
		$access_key = (string) Settings::get('oz.files.minio', 'OZ_MINIO_ACCESS_KEY', '');
		$secret_key = (string) Settings::get('oz.files.minio', 'OZ_MINIO_SECRET_KEY', '');

		if ('' === $access_key || '' === $secret_key) {
			throw new RuntimeException(
				'The MinIO credentials are not set (OZ_MINIO_ACCESS_KEY and OZ_MINIO_SECRET_KEY in the project .env).'
			);
		}

		return new S3Client(
			(string) Settings::get('oz.files.minio', 'OZ_MINIO_ENDPOINT'),
			(string) Settings::get('oz.files.minio', 'OZ_MINIO_REGION', 'us-east-1'),
			$access_key,
			$secret_key,
			(bool) Settings::get('oz.files.minio', 'OZ_MINIO_PATH_STYLE', true),
			(float) Settings::get('oz.files.minio', 'OZ_MINIO_TIMEOUT', 30),
			(bool) Settings::get('oz.files.minio', 'OZ_MINIO_VERIFY_TLS', true),
			(int) Settings::get('oz.files.minio', 'OZ_MINIO_PART_SIZE', S3Client::DEFAULT_PART_SIZE),
		);
	}

	/**
	 * The bucket of this storage slot.
	 */
	public function getBucket(): string
	{
		return $this->bucket;
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function getStream(OZFile $file): FileStream
	{
		// A temporary file, not a string: the object may be arbitrarily large.
		$stream = FileStream::fromPath('php://temp/maxmemory:' . self::MEMORY_SPOOL_SIZE, 'w+b');

		$this->client->getObjectTo($this->bucket, $this->key($file->getRef()), $stream);

		$stream->rewind();

		return $stream;
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function upload(UploadedFile $upload): OZFile
	{
		if (($error = $upload->getError()) !== \UPLOAD_ERR_OK) {
			$info = FS::uploadErrorInfo($error);

			throw new RuntimeException($info['message'], ['_reason' => $info['reason']]);
		}

		if (null !== ($result = FS::parseFileAlias($upload))) {
			return $result->cloneFile();
		}

		$mimetype   = $upload->getCleanMediaType();
		$clean_name = $upload->getCleanFileName();
		$filename   = \trim($upload->getClientFilename());

		return $this->store(
			$upload->getStream(),
			$mimetype,
			$clean_name,
			'' === $filename ? $clean_name : $filename,
			FS::getRealExtension($clean_name, $mimetype)
		);
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function saveStream(FileStream $source, string $mimetype, string $filename): OZFile
	{
		$filename   = \trim($filename);
		$ext        = FS::getRealExtension($filename, $mimetype);
		$clean_name = FS::sanitizeFilename($filename, $ext, 'save');

		return $this->store($source, $mimetype, $clean_name, '' === $filename ? $clean_name : $filename, $ext);
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function saveFromPath(string $path, string $mimetype, string $filename): OZFile
	{
		return $this->saveStream(FileStream::fromPath($path), $mimetype, $filename);
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function saveRaw(string $content, string $mimetype, string $filename): OZFile
	{
		return $this->saveStream(FileStream::fromString($content), $mimetype, $filename);
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function exists(OZFile $file): bool
	{
		return null !== $this->client->headObject($this->bucket, $this->key($file->getRef()));
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function revocableAccessUri(Context $context, FileAccessAuthorizationProvider $provider): Uri
	{
		$file = $provider->getFile();

		$this->require($file);

		return FS::buildFileUri($context, $file, $provider->getCredentials());
	}

	/**
	 * {@inheritDoc}
	 *
	 * @throws UnauthorizedException when this storage slot is not public
	 */
	#[Override]
	public function publicUri(Context $context, OZFile $file): Uri
	{
		if (!$this->public) {
			throw new UnauthorizedException('Private files cannot be publicly accessed.');
		}

		$this->require($file);

		if (!Settings::get('oz.files', 'OZ_PUBLIC_URI_DIRECT_ACCESS_ENABLED')) {
			return FS::buildFileUri($context, $file);
		}

		return Uri::createFromString(
			$this->client->objectUrl($this->bucket, $this->key($file->getRef()), self::publicEndpoint())
		);
	}

	/**
	 * {@inheritDoc}
	 *
	 * In `redirect` mode (OZ_MINIO_SERVE_MODE), the client is sent to a presigned URL instead.
	 *
	 * @psalm-suppress MoreSpecificReturnType, LessSpecificReturnStatement
	 */
	#[Override]
	public function serve(OZFile $file, Response $response): Response
	{
		$key = $this->key($file->getRef());

		if ('redirect' === Settings::get('oz.files.minio', 'OZ_MINIO_SERVE_MODE', 'proxy')) {
			$url = $this->client->presignedUrl(
				'GET',
				$this->bucket,
				$key,
				(int) Settings::get('oz.files.minio', 'OZ_MINIO_PRESIGN_TTL', 300),
				self::publicEndpoint()
			);

			return $response
				->withStatus(302)
				->withHeader('Location', $url)
				->withHeader('Cache-Control', 'private, no-store');
		}

		$body = Body::fromPath('php://temp/maxmemory:' . self::MEMORY_SPOOL_SIZE, 'w+b');

		$this->client->getObjectTo($this->bucket, $key, $body);

		$body->rewind();

		return self::withFileHeaders($file, $response)->withBody($body);
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function write(OZFile $file, FileStream|string $content): static
	{
		return $this->replace($file, self::asStream($content));
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function append(OZFile $file, FileStream|string $data): static
	{
		return $this->replace($file, $this->concat($file, $data, true));
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function prepend(OZFile $file, FileStream|string $data): static
	{
		return $this->replace($file, $this->concat($file, $data, false));
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function delete(OZFile $file): bool
	{
		if (!$file->canDelete() || !$this->exists($file)) {
			return false;
		}

		$this->client->deleteObject($this->bucket, $this->key($file->getRef()));

		return true;
	}

	/**
	 * Stores new content under a `<year>/<month>/<name>` ref.
	 */
	private function store(
		StreamInterface|string $content,
		string $mimetype,
		string $clean_name,
		string $filename,
		string $ext
	): OZFile {
		$ref  = \date('Y') . '/' . \date('m') . '/' . $clean_name;
		$size = $this->client->putObjectFromStream(
			$this->bucket,
			$this->key($ref),
			self::asStream($content),
			$mimetype
		);

		$f = new OZFile();
		$f->setName($clean_name)
			->setRealName($filename)
			->setRef($ref)
			->setStorage($this->name)
			->setMime($mimetype)
			->setExtension($ext)
			->setSize($size);

		return $f;
	}

	/**
	 * Replaces the content of a file and records its new size.
	 */
	private function replace(OZFile $file, StreamInterface|string $content): static
	{
		$size = $this->client->putObjectFromStream(
			$this->bucket,
			$this->key($file->getRef()),
			self::asStream($content),
			$file->getMime()
		);

		$file->setSize($size)->setUpdatedAt(\time())->save();

		return $this;
	}

	/**
	 * The object's content with `$data` added at one end, spooled to disk rather than concatenated
	 * in memory.
	 *
	 * S3 has no append: the object has to be rewritten whole either way, and this keeps the cost in
	 * temporary disk space instead of in `memory_limit`. A server-side `UploadPartCopy` would avoid
	 * the round trip, but only for parts of at least 5 MiB.
	 */
	private function concat(OZFile $file, StreamInterface|string $data, bool $after): FileStream
	{
		$out = FileStream::fromPath('php://temp/maxmemory:' . self::MEMORY_SPOOL_SIZE, 'w+b');

		if (!$after) {
			self::copy(self::asStream($data), $out);
		}

		$this->client->getObjectTo($this->bucket, $this->key($file->getRef()), $out);

		if ($after) {
			self::copy(self::asStream($data), $out);
		}

		$out->rewind();

		return $out;
	}

	/**
	 * A stream for a string or stream input, so nothing downstream has to care which it was.
	 */
	private static function asStream(StreamInterface|string $content): StreamInterface
	{
		return \is_string($content) ? FileStream::fromString($content) : $content;
	}

	/**
	 * Copies a stream into another, in chunks.
	 */
	private static function copy(StreamInterface $from, StreamInterface $to): void
	{
		if ($from->isSeekable()) {
			$from->rewind();
		}

		while (!$from->eof()) {
			$chunk = $from->read(self::COPY_CHUNK_SIZE);

			if ('' === $chunk) {
				break;
			}

			$to->write($chunk);
		}
	}

	/**
	 * Fails when the object of a file is missing.
	 */
	private function require(OZFile $file): void
	{
		if (!$this->exists($file)) {
			throw new RuntimeException('File not found.', ['_ref' => $file->getRef()]);
		}
	}

	/**
	 * The object key of a file ref.
	 */
	private function key(string $ref): string
	{
		$ref = \ltrim(\str_replace('\\', '/', $ref), '/');

		return '' === $this->prefix ? $ref : $this->prefix . '/' . $ref;
	}

	private static function publicEndpoint(): ?string
	{
		$endpoint = Settings::get('oz.files.minio', 'OZ_MINIO_PUBLIC_ENDPOINT');

		return empty($endpoint) ? null : (string) $endpoint;
	}
}
