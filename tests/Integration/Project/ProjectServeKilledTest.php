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

namespace OZONE\Tests\Integration\Project;

use Override;
use OZONE\Core\Testing\OZTestProject;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

/**
 * `oz project serve` when the server it started is killed rather than stopped.
 *
 * The command waits on the server process, and a signal used to come out of that wait as an
 * exception: the command ended on an uncaught throwable, with the exit code of a crash instead of
 * the server's, and nothing said what had happened to the server.
 *
 * @internal
 */
#[CoversNothing]
final class ProjectServeKilledTest extends TestCase
{
	private static ?OZTestProject $proj = null;

	#[Override]
	public static function setUpBeforeClass(): void
	{
		parent::setUpBeforeClass();

		self::$proj = OZTestProject::create('project-serve-killed', fresh: true);
	}

	#[Override]
	public static function tearDownAfterClass(): void
	{
		self::$proj?->destroy();

		parent::tearDownAfterClass();
	}

	public function testTheCommandSaysTheServerWasKilledAndAnswersItsCode(): void
	{
		$proj = self::$proj;

		self::assertNotNull($proj, 'The test project was not created.');

		$serve = $proj->oz('project', 'serve', '--scope=api', '--host=127.0.0.1');

		$serve->start();

		// The command writes server.json before it binds, then again with the server's own pid:
		// which is how a tool kills the server itself rather than the command waiting on it.
		$file     = $proj->getPath() . '/.ozone/cache/api/server.json';
		$deadline = \microtime(true) + 10.0;
		$pid      = null;

		while (null === $pid && \microtime(true) < $deadline) {
			if (\is_file($file)) {
				/** @var array{server_pid?: null|int} $info */
				$info = (array) \json_decode((string) \file_get_contents($file), true);
				$pid  = $info['server_pid'] ?? null;
			}

			\usleep(100_000);
		}

		if (null === $pid) {
			$serve->stop(0);

			self::fail('"oz project serve" did not write the server pid within 10 seconds.');
		}

		// The signal a host short of memory sends, to the server and not to the command: nothing of
		// the server runs afterwards, so the command waiting on it is the only thing left to report.
		\posix_kill($pid, 9);

		$code = $serve->wait();
		$out  = $serve->getOutput() . $serve->getErrorOutput();

		// 128 plus the signal, where an uncaught throwable answered 255 and the command never
		// reached the line that terminates with the server's code.
		self::assertSame(137, $code, $out);
		self::assertStringContainsString('The development server was killed by signal 9 (SIGKILL', $out, $out);
	}
}
