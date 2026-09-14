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

namespace OZONE\Tests\Logger;

use OZONE\Core\Logger\LogWriter;
use PHPUnit\Framework\TestCase;

/**
 * Class LogWriterTest.
 *
 * @internal
 *
 * @covers \OZONE\Core\Logger\LogWriter
 */
final class LogWriterTest extends TestCase
{
	public function testLogsUnderTheProjectLogsDir(): void
	{
		$writer = new class extends LogWriter {
			public function file(): string
			{
				return $this->log_file;
			}
		};

		self::assertStringStartsWith(app()->getProjectDir()->getRoot(), $writer->file());
		self::assertStringEndsWith(
			DS . '.ozone' . DS . 'logs' . DS . 'ozone.' . OZ_SCOPE_NAME . '.log',
			$writer->file()
		);
	}

	public function testFullFileIsRotatedNotTruncated(): void
	{
		$dir  = \sys_get_temp_dir() . DS . 'oz-log-writer-test-' . \bin2hex(\random_bytes(4));
		$file = $dir . DS . 'app.log';
		\mkdir($dir);

		try {
			$writer = new LogWriter($file, 10, 2);

			foreach (['first', 'second', 'third', 'fourth'] as $message) {
				$writer->write('info', $message);
			}

			self::assertStringContainsString('fourth', (string) \file_get_contents($file));
			self::assertStringContainsString('third', (string) \file_get_contents($dir . DS . 'app.1.log'));
			self::assertStringContainsString('second', (string) \file_get_contents($dir . DS . 'app.2.log'));
			// Beyond max_files: dropped.
			self::assertFileDoesNotExist($dir . DS . 'app.3.log');
		} finally {
			\array_map('unlink', (array) \glob($dir . DS . '*'));
			\rmdir($dir);
		}
	}
}
