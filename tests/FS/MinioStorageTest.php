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

use Override;
use OZONE\Core\App\Settings;
use OZONE\Core\Db\OZFile;
use OZONE\Core\Exceptions\UnauthorizedException;
use OZONE\Core\FS\Drivers\MinioStorage;
use OZONE\Core\FS\FileStream;
use OZONE\Core\FS\FS;
use OZONE\Core\FS\S3\S3Client;
use OZONE\Tests\Support\IntegrationTestCase;
use OZONE\Tests\Support\RequiresMinioTrait;

/**
 * Class MinioStorageTest.
 *
 * Runs against a real MinIO: `make test-services` (see docker/compose.yaml). The unit suite's
 * S3ClientTest covers the same paths against a local fake, so the client is exercised without
 * Docker; this is what proves the signing and the multipart protocol against a real service.
 *
 * @internal
 *
 * @group minio
 *
 * @covers \OZONE\Core\FS\Drivers\MinioStorage
 * @covers \OZONE\Core\FS\S3\S3Client
 */
final class MinioStorageTest extends IntegrationTestCase
{
	use RequiresMinioTrait;

	#[Override]
	protected function setUp(): void
	{
		parent::setUp();

		self::requireMinio();
	}

	public function testStoresReadsAndDeletesAFile(): void
	{
		$storage = MinioStorage::get(FS::PRIVATE_STORAGE);
		$file    = self::saved($storage->saveRaw('hello minio', 'text/plain', 'hello.txt'));

		self::assertSame(FS::PRIVATE_STORAGE, $file->getStorage());
		self::assertSame(11, (int) $file->getSize());
		self::assertTrue($storage->exists($file));
		self::assertSame('hello minio', (string) $storage->getStream($file));

		self::assertTrue($storage->delete($file));
		self::assertFalse($storage->exists($file));
		self::assertFalse($storage->delete($file));
	}

	public function testWritesAppendsAndPrepends(): void
	{
		$storage = MinioStorage::get(FS::PRIVATE_STORAGE);
		$file    = self::saved($storage->saveRaw('x', 'text/plain', 'parts.txt'));

		$storage->write($file, 'b')->append($file, 'c')->prepend($file, 'a');

		self::assertSame('abc', (string) $storage->getStream($file));
		self::assertSame(3, (int) $file->getSize());

		$storage->delete($file);
	}

	public function testStoresAndReadsAFileLargerThanOnePart(): void
	{
		// Above the part size, so the upload really goes through the multipart path against a real
		// service: the fake S3 of the unit suite cannot prove the signing of each part.
		$part_size = S3Client::MIN_PART_SIZE;
		$storage   = MinioStorage::get(FS::PRIVATE_STORAGE);

		Settings::set('oz.files.minio', 'OZ_MINIO_PART_SIZE', $part_size);

		try {
			$source = FileStream::fromPath('php://temp', 'w+b');

			// Two full parts plus a remainder, written in chunks so the fixture itself is cheap.
			for ($i = 0; $i < 2; ++$i) {
				$source->write(\str_repeat(\chr(65 + $i), $part_size));
			}

			$source->write('tail');
			$source->rewind();

			$expected_size = 2 * $part_size + 4;
			$expected_hash = \md5((string) $source);

			$source->rewind();

			$file = self::saved($storage->saveStream($source, 'application/octet-stream', 'big.bin'));

			self::assertSame($expected_size, (int) $file->getSize());
			self::assertSame($expected_hash, \md5((string) $storage->getStream($file)));

			$storage->delete($file);
		} finally {
			Settings::unset('oz.files.minio', 'OZ_MINIO_PART_SIZE');
		}
	}

	public function testServesThroughPhpOrAPresignedUrl(): void
	{
		$storage = MinioStorage::get(FS::PRIVATE_STORAGE);
		$file    = self::saved($storage->saveRaw('served', 'text/plain', 'served.txt'));

		$response = $storage->serve($file, context()->getResponse());

		self::assertSame('served', (string) $response->getBody());
		self::assertSame('text/plain', $response->getHeaderLine('Content-Type'));

		Settings::set('oz.files.minio', 'OZ_MINIO_SERVE_MODE', 'redirect');

		try {
			$response = $storage->serve($file, context()->getResponse());

			self::assertSame(302, $response->getStatusCode());
			self::assertSame('served', \file_get_contents($response->getHeaderLine('Location')));
		} finally {
			Settings::unset('oz.files.minio', 'OZ_MINIO_SERVE_MODE');

			$storage->delete($file);
		}
	}

	public function testPrivateFilesHaveNoPublicUri(): void
	{
		$storage = MinioStorage::get(FS::PRIVATE_STORAGE);
		$file    = self::saved($storage->saveRaw('private', 'text/plain', 'private.txt'));

		try {
			$this->expectException(UnauthorizedException::class);

			$storage->publicUri(context(), $file);
		} finally {
			$storage->delete($file);
		}
	}

	private static function saved(OZFile $file): OZFile
	{
		$file->save();

		return $file;
	}
}
