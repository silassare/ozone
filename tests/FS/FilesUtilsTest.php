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

namespace OZONE\OZ\Tests\FS;

use OZONE\OZ\FS\FilesManager;
use OZONE\OZ\FS\FilesUtils;
use PHPUnit\Framework\TestCase;

/**
 * Class FilesUtilsTest.
 *
 * @internal
 * @coversNothing
 */
final class FilesUtilsTest extends TestCase
{
	public function testGetFilesUploadDirectory(): void
	{
		static::assertSame(OZ_FILES_DIR . 'uploads' . DS, FilesUtils::getFilesUploadDirectory());
	}

	public function testGetRealExtension(): void
	{
		static::assertSame('jpeg', FilesUtils::getRealExtension('file-with-no-ext', 'image/jpeg'));
		static::assertSame('txt', FilesUtils::getRealExtension('foo.', 'text/plain'));
		static::assertSame('ofa', FilesUtils::getRealExtension('foo-bar.png', 'text/x-ozone-file-alias'));
		static::assertSame('png', FilesUtils::getRealExtension('foo-bar.baz.jpeg', 'image/png'));
	}

	public function testExtensionToMimeType(): void
	{
		static::assertSame('image/jpeg', FilesUtils::extensionToMimeType('jpeg'));
		static::assertSame('text/plain', FilesUtils::extensionToMimeType('txt'));
		static::assertSame('text/x-ozone-file-alias', FilesUtils::extensionToMimeType('ofa'));
		static::assertSame('image/png', FilesUtils::extensionToMimeType('png'));
		static::assertSame(FilesUtils::DEFAULT_FILE_MIME_TYPE, FilesUtils::extensionToMimeType(''));
	}

	public function testMimeTypeToExtension(): void
	{
		static::assertSame('jpeg', FilesUtils::mimeTypeToExtension('image/jpeg'));
		static::assertSame('txt', FilesUtils::mimeTypeToExtension('text/plain'));
		static::assertSame('ofa', FilesUtils::mimeTypeToExtension('text/x-ozone-file-alias'));
		static::assertSame('png', FilesUtils::mimeTypeToExtension('image/png'));
		static::assertSame(FilesUtils::DEFAULT_FILE_EXTENSION, FilesUtils::mimeTypeToExtension('unknown/type'));
	}

	public function testBase64ToFile(): void
	{
		$b64 = \base64_encode($expected = 'Africa loves you!');

		$content = (string) FilesUtils::base64ToFile($b64);

		static::assertSame($expected, $content);

		$b64_uri = 'data:text/plain;base64,' . $b64;

		$content = (string) FilesUtils::base64ToFile($b64_uri);

		static::assertSame($expected, $content);

		$b64_big = \base64_encode($expected = \str_repeat($expected, 12083));

		$content = (string) FilesUtils::base64ToFile($b64_big);

		static::assertSame($expected, $content);
	}

	public function testTempFileName(): void
	{
		$file = FilesUtils::newTempFile();
		$fm   = new FilesManager();

		static::assertTrue($fm->filter()->isReadable()->isWritable()->isFile()->check($file));
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
			$results_data_sizes[] = FilesUtils::formatFileSize($size, 2, 1000);
			$results_file_sizes[] = FilesUtils::formatFileSize($size, 2, 1024);
		}

		static::assertSame($expected_data_sizes, $results_data_sizes);
		static::assertSame($expected_file_sizes, $results_file_sizes);
	}

	public function testGetFileDriver(): void
	{
	}

	public function testGetFileWithId(): void
	{
	}

	public function testParseFileAlias(): void
	{
	}
}
