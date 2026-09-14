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

namespace OZONE\Tests\FS;

use OZONE\Core\App\Settings;
use OZONE\Core\FS\Drivers\MinioStorage;
use OZONE\Core\FS\FileStream;
use OZONE\Core\FS\FS;
use OZONE\Core\FS\S3\Interfaces\S3TransportInterface;
use OZONE\Core\FS\S3\S3Client;
use OZONE\Core\FS\S3\Transport\CurlTransport;
use OZONE\Core\FS\S3\Transport\StreamWrapperTransport;
use OZONE\Tests\Support\RequiresMinioTrait;
use PHPUnit\Framework\TestCase;

/**
 * Class S3ClientTest.
 *
 * Runs against a real MinIO: `make test-services` (see docker/compose.yaml). Every case runs on
 * both transports, so the streaming path and the part-by-part fallback are both proven against a
 * service that actually checks the signatures.
 *
 * @internal
 *
 * @group minio
 *
 * @covers \OZONE\Core\FS\S3\S3Client
 * @covers \OZONE\Core\FS\S3\S3Request
 * @covers \OZONE\Core\FS\S3\S3Response
 * @covers \OZONE\Core\FS\S3\Transport\CurlTransport
 * @covers \OZONE\Core\FS\S3\Transport\StreamWrapperTransport
 */
final class S3ClientTest extends TestCase
{
	use RequiresMinioTrait;

	private static string $bucket = '';

	/** @var list<string> the keys written by the running test */
	private array $written = [];

	public static function setUpBeforeClass(): void
	{
		parent::setUpBeforeClass();

		self::requireMinio();

		self::$bucket = MinioStorage::get(FS::PRIVATE_STORAGE)->getBucket();
	}

	protected function tearDown(): void
	{
		if ('' !== self::$bucket) {
			$client = $this->client(new StreamWrapperTransport());

			foreach ($this->written as $key) {
				$client->deleteObject(self::$bucket, $key);
			}
		}

		$this->written = [];

		parent::tearDown();
	}

	/**
	 * @dataProvider provideTransports
	 */
	public function testPutAndGetAnObject(S3TransportInterface $transport): void
	{
		$client = $this->client($transport);
		$key    = $this->key('hello.txt');

		$client->putObject(self::$bucket, $key, 'hello minio', 'text/plain');

		self::assertSame('hello minio', $client->getObject(self::$bucket, $key));
		self::assertSame(11, $client->objectSize(self::$bucket, $key));
	}

	/**
	 * @dataProvider provideTransports
	 */
	public function testGetObjectToWritesIntoTheSinkWithoutReturningTheBody(
		S3TransportInterface $transport
	): void {
		$client  = $this->client($transport);
		$key     = $this->key('blob.bin');
		$content = \str_repeat('abcdefgh', 200000); // ~1.5 MB, over the transports' chunk size

		$client->putObject(self::$bucket, $key, $content, 'application/octet-stream');

		$sink    = FileStream::fromPath('php://temp', 'w+b');
		$written = $client->getObjectTo(self::$bucket, $key, $sink);

		$sink->rewind();

		self::assertSame(\strlen($content), $written);
		self::assertSame(\md5($content), \md5((string) $sink));
	}

	/**
	 * @dataProvider provideTransports
	 */
	public function testPutObjectFromStreamUsesOneRequestBelowThePartSize(
		S3TransportInterface $transport
	): void {
		$client  = $this->client($transport);
		$key     = $this->key('small.bin');
		$content = \str_repeat('x', 1024);

		$stored = $client->putObjectFromStream(
			self::$bucket,
			$key,
			FileStream::fromString($content),
			'application/octet-stream'
		);

		self::assertSame(1024, $stored);
		self::assertSame($content, $client->getObject(self::$bucket, $key));
	}

	/**
	 * @dataProvider provideTransports
	 */
	public function testPutObjectFromStreamSplitsAboveThePartSize(S3TransportInterface $transport): void
	{
		// The smallest part size the service accepts, so the test data stays small.
		$part_size = S3Client::MIN_PART_SIZE;
		$client    = $this->client($transport, $part_size);
		$key       = $this->key('multi.bin');

		// Two full parts and a remainder, to check ordering and the final short part.
		$content = \str_repeat('A', $part_size) . \str_repeat('B', $part_size) . \str_repeat('C', 1234);

		$stored = $client->putObjectFromStream(
			self::$bucket,
			$key,
			FileStream::fromString($content),
			'application/octet-stream'
		);

		self::assertSame(\strlen($content), $stored);

		$sink = FileStream::fromPath('php://temp', 'w+b');
		$client->getObjectTo(self::$bucket, $key, $sink);
		$sink->rewind();

		self::assertSame(\strlen($content), $client->objectSize(self::$bucket, $key));
		self::assertSame(\md5($content), \md5((string) $sink), 'The parts must be reassembled in order.');
	}

	/**
	 * @dataProvider provideTransports
	 */
	public function testPutObjectFromStreamStoresAnEmptyStream(S3TransportInterface $transport): void
	{
		$client = $this->client($transport);
		$key    = $this->key('empty.bin');

		$stored = $client->putObjectFromStream(
			self::$bucket,
			$key,
			FileStream::fromString(''),
			'application/octet-stream'
		);

		self::assertSame(0, $stored);
		self::assertSame('', $client->getObject(self::$bucket, $key));
	}

	/**
	 * The point of the whole exercise: peak memory must follow the part size, not the file size.
	 *
	 * @dataProvider provideTransports
	 */
	public function testLargeUploadAndDownloadStayWithinAFixedBuffer(S3TransportInterface $transport): void
	{
		// Called through a variable: the function is PHP 8.2+, OZone supports 8.1, and the
		// compatibility sniff cannot see the guard below it.
		$reset_peak = 'memory_reset_peak_usage';

		if (!\function_exists($reset_peak)) {
			self::markTestSkipped('memory_reset_peak_usage() needs PHP 8.2+; the peak cannot be isolated.');
		}

		$part_size = S3Client::MIN_PART_SIZE;
		$parts     = 5;
		$size      = $part_size * $parts;
		$source    = \tempnam(\sys_get_temp_dir(), 'oz_s3_big_');

		self::assertIsString($source);

		// Written in chunks, so building the fixture does not itself cost the file size.
		$handle = \fopen($source, 'wb');

		for ($i = 0; $i < $parts; ++$i) {
			\fwrite($handle, \str_repeat(\chr(65 + $i), $part_size));
		}

		\fclose($handle);

		$client = $this->client($transport, $part_size);
		$key    = $this->key('big.bin');

		try {
			$reset_peak();
			$before = \memory_get_usage();

			$stored = $client->putObjectFromStream(
				self::$bucket,
				$key,
				FileStream::fromPath($source),
				'application/octet-stream'
			);

			$upload_peak = \memory_get_peak_usage() - $before;

			self::assertSame($size, $stored);

			$reset_peak();
			$before = \memory_get_usage();

			$sink = FileStream::fromPath('php://temp/maxmemory:1024', 'w+b');

			$client->getObjectTo(self::$bucket, $key, $sink);

			$download_peak = \memory_get_peak_usage() - $before;

			// Generous, so the test is about the order of magnitude and not about allocator detail:
			// three parts of headroom, where the old code needed the whole file (and twice it for
			// append/prepend).
			$budget = 3 * $part_size;

			self::assertLessThan($budget, $upload_peak, \sprintf(
				'Uploading %d bytes peaked at %d bytes of PHP memory.',
				$size,
				$upload_peak
			));
			self::assertLessThan($budget, $download_peak, \sprintf(
				'Downloading %d bytes peaked at %d bytes of PHP memory.',
				$size,
				$download_peak
			));

			// And the bytes actually made the round trip.
			$sink->rewind();

			self::assertSame($size, $client->objectSize(self::$bucket, $key));
			self::assertSame(\md5_file($source), \md5((string) $sink));
		} finally {
			@\unlink($source);
		}
	}

	/**
	 * @dataProvider provideTransports
	 */
	public function testHeadAndDeleteAnObject(S3TransportInterface $transport): void
	{
		$client = $this->client($transport);
		$key    = $this->key('gone.txt');

		$client->putObject(self::$bucket, $key, 'bye', 'text/plain');

		self::assertNotNull($client->headObject(self::$bucket, $key));

		$client->deleteObject(self::$bucket, $key);

		self::assertNull($client->headObject(self::$bucket, $key));
		self::assertNull($client->objectSize(self::$bucket, $key));
	}

	/**
	 * @dataProvider provideTransports
	 */
	public function testBucketExistence(S3TransportInterface $transport): void
	{
		$client = $this->client($transport);

		self::assertTrue($client->bucketExists(self::$bucket));
		self::assertFalse($client->bucketExists('oz-never-created-bucket'));
	}

	/**
	 * @return array<string, array{S3TransportInterface}>
	 */
	public static function provideTransports(): iterable
	{
		$transports = ['stream-wrapper' => [new StreamWrapperTransport()]];

		if (CurlTransport::isSupported()) {
			$transports['curl'] = [new CurlTransport()];
		}

		return $transports;
	}

	public function testPartSizeIsNeverBelowTheServiceMinimum(): void
	{
		self::assertSame(S3Client::MIN_PART_SIZE, $this->client(new StreamWrapperTransport(), 1024)->getPartSize());
	}

	public function testOnlyCurlStreamsARequestBody(): void
	{
		self::assertTrue(CurlTransport::streamsRequestBody());
		self::assertFalse(StreamWrapperTransport::streamsRequestBody());
		self::assertTrue(StreamWrapperTransport::isSupported(), 'The fallback must always be available.');
	}

	/**
	 * A unique key, deleted in tearDown().
	 */
	private function key(string $name): string
	{
		$key = 'tests/s3-client/' . \bin2hex(\random_bytes(4)) . '/' . $name;

		$this->written[] = $key;

		return $key;
	}

	/**
	 * A client for the configured MinIO, on a given transport.
	 */
	private function client(S3TransportInterface $transport, ?int $part_size = null): S3Client
	{
		return new S3Client(
			(string) Settings::get('oz.files.minio', 'OZ_MINIO_ENDPOINT'),
			(string) Settings::get('oz.files.minio', 'OZ_MINIO_REGION', 'us-east-1'),
			(string) Settings::get('oz.files.minio', 'OZ_MINIO_ACCESS_KEY'),
			(string) Settings::get('oz.files.minio', 'OZ_MINIO_SECRET_KEY'),
			(bool) Settings::get('oz.files.minio', 'OZ_MINIO_PATH_STYLE', true),
			30.0,
			(bool) Settings::get('oz.files.minio', 'OZ_MINIO_VERIFY_TLS', true),
			$part_size,
			$transport,
		);
	}
}
