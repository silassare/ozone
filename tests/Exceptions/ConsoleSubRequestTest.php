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

namespace OZONE\Tests\Exceptions;

use OZONE\Core\Exceptions\BaseException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

/**
 * Class ConsoleSubRequestTest.
 *
 * A sub-request refused under the console is answered with its status, and the command or job that
 * made it goes on: the error used to be printed and the whole process ended on it. Run in a process
 * of its own (`tests/Support/console_sub_request.php`), since the failure being guarded against is a
 * process ending, which would be PHPUnit's here.
 *
 * @internal
 */
#[CoversClass(BaseException::class)]
final class ConsoleSubRequestTest extends TestCase
{
	public function testARefusedSubRequestDoesNotEndTheProcess(): void
	{
		$process = new Process([\PHP_BINARY, \dirname(__DIR__) . '/Support/console_sub_request.php']);
		$process->setTimeout(120);
		$process->run();

		$out = $process->getErrorOutput() . $process->getOutput();

		self::assertSame(0, $process->getExitCode(), $out);
		self::assertStringContainsString('console=yes', $out);
		self::assertStringContainsString('status=403', $out);
		self::assertStringContainsString('survived', $out);
	}
}
