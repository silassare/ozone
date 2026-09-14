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

namespace OZONE\Tests\Runtime;

use OZONE\Core\Exceptions\BaseException;
use OZONE\Core\OZone;
use OZONE\Core\Runtime\CgiRuntime;
use OZONE\Core\Runtime\ConsoleRuntime;
use OZONE\Core\Runtime\Exceptions\RequestFinished;
use OZONE\Core\Runtime\Runtime;
use OZONE\Core\Runtime\WorkerRuntime;
use PHPUnit\Framework\TestCase;

/**
 * Class RuntimeTest.
 *
 * How a request ends is the only thing a persistent process does differently, and it is the thing
 * that used to be hardcoded: `Context::finish()` called `exit`.
 *
 * @internal
 *
 * @covers \OZONE\Core\Runtime\CgiRuntime
 * @covers \OZONE\Core\Runtime\ConsoleRuntime
 * @covers \OZONE\Core\Runtime\Exceptions\RequestFinished
 * @covers \OZONE\Core\Runtime\Runtime
 * @covers \OZONE\Core\Runtime\WorkerRuntime
 */
final class RuntimeTest extends TestCase
{
	protected function tearDown(): void
	{
		Runtime::reset();

		parent::tearDown();
	}

	public function testTheDefaultIsOneRequestPerProcess(): void
	{
		// Nothing in the test suite declares a worker, so detection must not land on one: guessing
		// that way would leave a process that never exits. The suite runs under the `cli` SAPI, so
		// what it must land on is the console.
		$runtime = Runtime::detect();

		self::assertInstanceOf(ConsoleRuntime::class, $runtime);
		self::assertFalse($runtime->isPersistent());
		self::assertSame('cli', $runtime::getName());
	}

	public function testAWorkerIsNotAConsoleEvenUnderTheCliSapi(): void
	{
		// The bug this prevents: `PHP_SAPI === 'cli'` is true of RoadRunner, Swoole and ReactPHP
		// workers, and "console" is what decides whether an error is printed to a terminal and the
		// process killed, or turned into an HTTP response. Detection therefore asks about the loop
		// first -- and this suite runs under the `cli` SAPI, so the question is live here.
		self::assertSame('cli', \PHP_SAPI);

		\putenv('OZ_RUNTIME=worker');

		try {
			Runtime::reset();

			self::assertTrue(Runtime::isPersistent());
			self::assertFalse(Runtime::isConsole(), 'a worker must never be taken for a command line');
			self::assertFalse(OZone::isCliMode(), 'a worker must answer its client, not the terminal');
		} finally {
			\putenv('OZ_RUNTIME');
			Runtime::reset();
		}
	}

	public function testTheConsoleHasNoClientToAnswer(): void
	{
		Runtime::set(new ConsoleRuntime());

		self::assertTrue(Runtime::isConsole());
		self::assertTrue(OZone::isCliMode());
		self::assertFalse(Runtime::isPersistent());
	}

	public function testACgiRequestIsNotAConsole(): void
	{
		$runtime = new CgiRuntime();

		self::assertFalse($runtime->isConsole());
		self::assertFalse($runtime->isPersistent());
		self::assertSame('cgi', $runtime::getName());
	}

	public function testAWorkerUnwindsInsteadOfExiting(): void
	{
		$runtime = new WorkerRuntime('test-loop');

		self::assertTrue($runtime->isPersistent());

		try {
			$runtime->terminate(3);

			self::fail('terminate() must not return');
		} catch (RequestFinished $finished) {
			// The stack unwinds exactly as far as `exit` would, and the loop gets the code `exit`
			// would have used.
			self::assertSame(3, $finished->getExitCode());
		}
	}

	public function testTheRuntimeCanBeDeclared(): void
	{
		// A loop OZone cannot recognise -- Swoole, ReactPHP, an in-house one -- says so itself.
		$declared = new WorkerRuntime('swoole');

		Runtime::set($declared);

		self::assertSame($declared, Runtime::current());
		self::assertTrue(Runtime::isPersistent());
		self::assertSame('swoole', $declared->getLoopName());
	}

	public function testAnEnvironmentDeclarationIsHonoured(): void
	{
		\putenv('OZ_RUNTIME=worker');

		try {
			Runtime::reset();

			self::assertTrue(Runtime::isPersistent(), 'OZ_RUNTIME=worker must be honoured');
			self::assertSame('declared', Runtime::detect()->getLoopName());
		} finally {
			\putenv('OZ_RUNTIME');
			Runtime::reset();
		}
	}

	public function testAnInstalledExtensionIsNotEvidenceOfAWorker(): void
	{
		// Deliberately narrow detection: `ext-swoole` being loaded says nothing about how the script
		// was started, and a process that wrongly believes it is persistent never exits.
		\putenv('OZ_RUNTIME=cgi');

		try {
			Runtime::reset();

			self::assertFalse(Runtime::isPersistent());
		} finally {
			\putenv('OZ_RUNTIME');
			Runtime::reset();
		}
	}

	public function testFrankenPhpIsAWorkerOnlyWhenItStartedOneWorkerScript(): void
	{
		// FrankenPHP defines frankenphp_handle_request() in classic mode too, where the script runs
		// once per request: taken for a worker there, OZone::run() answered the mock boot request.
		require_once \dirname(__DIR__) . '/Support/frankenphp_stub.php';

		$saved = $_SERVER['FRANKENPHP_WORKER'] ?? null;

		try {
			unset($_SERVER['FRANKENPHP_WORKER']);

			self::assertNull(WorkerRuntime::detectLoop(), 'classic FrankenPHP is not a worker');

			$_SERVER['FRANKENPHP_WORKER'] = '1';

			self::assertSame('frankenphp', WorkerRuntime::detectLoop());
		} finally {
			if (null === $saved) {
				unset($_SERVER['FRANKENPHP_WORKER']);
			} else {
				$_SERVER['FRANKENPHP_WORKER'] = $saved;
			}

			Runtime::reset();
		}
	}

	public function testRequestFinishedIsNotAFrameworkException(): void
	{
		// It is control flow, and must never be converted into an error response.
		self::assertNotInstanceOf(BaseException::class, new RequestFinished());
	}
}
