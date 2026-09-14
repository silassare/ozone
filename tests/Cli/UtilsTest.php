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

namespace OZONE\Tests\Cli;

use OZONE\Core\Cli\Process;
use OZONE\Core\Cli\Utils\Utils;
use PHPUnit\Framework\TestCase;

/**
 * Class UtilsTest.
 *
 * @internal
 *
 * @covers \OZONE\Core\Cli\Process
 * @covers \OZONE\Core\Cli\Utils\Utils
 */
final class UtilsTest extends TestCase
{
	public function testProjectFolderIsOneWithAnAppFile(): void
	{
		$dir = \sys_get_temp_dir() . '/oz_cli_project_' . \bin2hex(\random_bytes(6));

		\mkdir($dir . '/app', 0o775, true);

		try {
			self::assertNull(Utils::isProjectFolder($dir));

			\touch($dir . '/app/app.php');

			self::assertSame(
				\str_replace('\\', '/', $dir . '/app/app.php'),
				\str_replace('\\', '/', (string) Utils::isProjectFolder($dir))
			);
		} finally {
			@\unlink($dir . '/app/app.php');
			\rmdir($dir . '/app');
			\rmdir($dir);
		}
	}

	public function testPortHelpersTellTakenPortsFromFreeOnes(): void
	{
		$server = \stream_socket_server('tcp://127.0.0.1:0', $errno, $error);

		self::assertNotFalse($server, $error);

		try {
			$name = (string) \stream_socket_get_name($server, false);
			$port = (int) \substr($name, \strrpos($name, ':') + 1);

			self::assertTrue(Utils::isPortOpen($port));

			// The favorite port is taken: the first free one of the range is returned instead.
			$free = Utils::getOpenPort([$port], '127.0.0.1', $port, \min(65535, $port + 20));

			self::assertNotNull($free);
			self::assertNotSame($port, $free);
			self::assertFalse(Utils::isPortOpen($free));
		} finally {
			\fclose($server);
		}
	}

	public function testTableCliOptionsSkipAutoIncrementedColumns(): void
	{
		$table   = db()->getTableOrFail('oz_users');
		$options = Utils::buildTableCliOptions($table);

		self::assertArrayNotHasKey('user_id', $options);
		// Not nullable.
		self::assertTrue($options['user_email']->isRequired());
		// Nullable.
		self::assertFalse($options['user_phone']->isRequired());
	}

	public function testTableCliOptionsLeaveOutSoftDeleteColumns(): void
	{
		$options = Utils::buildTableCliOptions(db()->getTableOrFail('oz_users'));

		self::assertArrayNotHasKey('user_deleted', $options);
		self::assertArrayNotHasKey('user_deleted_at', $options);
	}

	public function testTableCliOptionsWithADefaultAreOptional(): void
	{
		$options = Utils::buildTableCliOptions(db()->getTableOrFail('oz_users'));

		// Not nullable, but defaults to true.
		self::assertFalse($options['user_is_valid']->isRequired());
	}

	public function testBoolTableCliOptionsTakeCommandLineBooleans(): void
	{
		$type = Utils::buildTableCliOptions(db()->getTableOrFail('oz_users'))['user_is_valid']->getType();

		self::assertFalse($type->validate('user_is_valid', 'false'));
		self::assertFalse($type->validate('user_is_valid', '0'));
		self::assertTrue($type->validate('user_is_valid', 'yes'));
	}

	public function testTableCliOptionsIncludesAndExcludes(): void
	{
		$table = db()->getTableOrFail('oz_users');

		self::assertSame(
			['user_phone', 'user_email'],
			\array_keys(Utils::buildTableCliOptions($table, ['user_email', 'user_phone']))
		);
		self::assertArrayNotHasKey('user_email', Utils::buildTableCliOptions($table, [], ['user_email']));
	}

	public function testProcessHasNoTimeoutByDefault(): void
	{
		$process = new Process([\PHP_BINARY, '-r', 'echo "ok";']);

		self::assertNull($process->getTimeout());

		$process->mustRun();

		self::assertSame('ok', $process->getOutput());
	}
}
