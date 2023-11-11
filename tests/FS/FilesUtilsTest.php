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

use OZONE\Core\FS\FilesManager;
use OZONE\Core\FS\FS;
use PHPUnit\Framework\TestCase;

/**
 * Class FilesUtilsTest.
 *
 * @internal
 *
 * @coversNothing
 */
final class FilesUtilsTest extends TestCase
{
	public function testGetRealExtension(): void
	{
		self::assertSame('jpeg', FS::getRealExtension('file-with-no-ext', 'image/jpeg'));
		self::assertSame('txt', FS::getRealExtension('foo.', 'text/plain'));
		self::assertSame('ofa', FS::getRealExtension('foo-bar.png', 'text/x-ozone-file-alias'));
		self::assertSame('png', FS::getRealExtension('foo-bar.baz.jpeg', 'image/png'));
	}

	public function testExtensionToMimeType(): void
	{
		self::assertSame('image/jpeg', FS::extensionToMimeType('jpeg'));
		self::assertSame('text/plain', FS::extensionToMimeType('txt'));
		self::assertSame('text/x-ozone-file-alias', FS::extensionToMimeType('ofa'));
		self::assertSame('image/png', FS::extensionToMimeType('png'));
		self::assertSame(FS::DEFAULT_FILE_MIME_TYPE, FS::extensionToMimeType(''));
	}

	public function testMimeTypeToExtension(): void
	{
		self::assertSame('jpeg', FS::mimeTypeToExtension('image/jpeg'));
		self::assertSame('txt', FS::mimeTypeToExtension('text/plain'));
		self::assertSame('ofa', FS::mimeTypeToExtension('text/x-ozone-file-alias'));
		self::assertSame('png', FS::mimeTypeToExtension('image/png'));
		self::assertSame(FS::DEFAULT_FILE_EXTENSION, FS::mimeTypeToExtension('unknown/type'));
	}

	public function testBase64ToFile(): void
	{
		$b64 = \base64_encode($expected = 'Africa loves you!');

		$content = (string) FS::base64ToFile($b64);

		self::assertSame($expected, $content);

		$b64_uri = 'data:text/plain;base64,' . $b64;

		$content = (string) FS::base64ToFile($b64_uri);

		self::assertSame($expected, $content);

		$b64_big = \base64_encode($expected = \str_repeat($expected, 12083));

		$content = (string) FS::base64ToFile($b64_big);

		self::assertSame($expected, $content);
	}

	public function testTempFileName(): void
	{
		$file = FS::newTempFile();
		$fm   = new FilesManager();

		self::assertTrue($fm->filter()
			->isReadable()
			->isWritable()
			->isFile()
			->check($file));
	}

	public function testFormatFileSize(): void
	{
		$expected_data_sizes = [
			'1.96 B',
			'1.96 KB',
			'1.96 MB',
			'1.96 GB',
			'1.96 TB',
			'1.96 PB',
			'1.96 EB',
			'1.96 ZB',
			'1.96 YB',
			'1 962.00 YB',
			'1 962 000.00 YB',
			'1 962 000 000.00 YB',
		];
		$expected_file_sizes = [
			'1.96 B',
			'1.92 KB',
			'1.87 MB',
			'1.83 GB',
			'1.78 TB',
			'1.74 PB',
			'1.70 EB',
			'1.66 ZB',
			'1.62 YB',
			'1 622.93 YB',
			'1 622 928.36 YB',
			'1 622 928 361.83 YB',
		];

		$results_data_sizes = [];
		$results_file_sizes = [];
		$len                = \count($expected_data_sizes);

		for ($i = 0; $i < $len; ++$i) {
			$size                 = 1.962 * (1000 ** $i);
			$results_data_sizes[] = FS::formatFileSize($size);
			$results_file_sizes[] = FS::formatFileSize($size, 2, 1024);
		}

		self::assertSame($expected_data_sizes, $results_data_sizes);
		self::assertSame($expected_file_sizes, $results_file_sizes);
	}
}
