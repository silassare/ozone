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

use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

/**
 * Class WorkerLoopTest.
 *
 * A worker loop, for real: one process answering several requests through `OZone::handleRequest()`,
 * run as its own process by `tests/Support/worker_loop.php`.
 *
 * It is a subprocess rather than an in-process loop for two reasons. The claim being tested is about
 * a process surviving its own responses, which a test that shares the process cannot observe; and
 * the framework clears output buffers down to level 1 before writing an error response, so under
 * PHPUnit -- which owns level 1 -- the emitted bytes are not the test's to read.
 *
 * @internal
 *
 * @coversNothing
 */
final class WorkerLoopTest extends TestCase
{
	private static ?string $output = null;

	public function testTheLoopRunsToTheEnd(): void
	{
		$out = self::runLoop();

		// Under the classic runtime the first response exits, so the loop never reaches its end.
		self::assertStringContainsString('loop ended', $out);
	}

	public function testOneProcessServesEveryRequest(): void
	{
		$out = self::runLoop();

		\preg_match_all('~^served (\d+) path=(\S+) pid=(\d+) ~m', $out, $m, \PREG_SET_ORDER);

		self::assertCount(5, $m, 'not every request was served');
		self::assertSame(['0', '1', '2', '3', '4'], \array_column($m, 1));

		// One process: the point of a worker.
		self::assertCount(1, \array_unique(\array_column($m, 3)));
	}

	public function testTheRuntimeIsThePersistentOne(): void
	{
		self::assertStringContainsString('runtime=worker persistent=yes console=no', self::runLoop());
	}

	public function testAFailedRequestIsAnsweredEveryTime(): void
	{
		$out = self::runLoop();

		\preg_match_all('~^served \d+ path=(\S+) pid=\d+ bytes=(\d+) ~m', $out, $m, \PREG_SET_ORDER);

		$failed = [];

		foreach ($m as $line) {
			if ('/no-such-route' === $line[1]) {
				$failed[] = (int) $line[2];
			}
		}

		self::assertCount(2, $failed);
		self::assertGreaterThan(0, $failed[0], 'the first failing request got no response');

		// `BaseException::$just_die` is set by the first error a process reports and is never unset
		// by itself: without `OZone::endRequest()` releasing it, the second failure would take the
		// "error handling error" path and answer with nothing.
		self::assertSame($failed[0], $failed[1], 'the second failure was answered differently');
	}

	public function testAHandlerThatRespondsLeavesNoOutputBufferBehind(): void
	{
		$out = self::runLoop();

		\preg_match_all('~^served \d+ path=\S+ pid=\d+ bytes=\d+ leaked_ob=(-?\d+) ~m', $out, $m);

		self::assertCount(5, $m[1]);

		// `Router::runRoute()` used to answer an exception with `ob_clean()`, which empties its
		// buffer but leaves it open; only the error path closed it. A handler that responds and then
		// throws -- what `respond(): never` publishes, and what application code is written around --
		// skips that path, so a worker leaked one level per such request, unbounded, with the
		// response nesting deeper inside orphaned buffers. The level must not grow.
		self::assertSame(['0'], \array_unique($m[1]), 'an output buffer outlived its request');
	}

	public function testASinkTakesTheResponseObjectAndNothingIsWritten(): void
	{
		$sinks = self::sinkLines();

		self::assertCount(5, $sinks);

		foreach ($sinks as $path => $line) {
			// Exactly one response handed over, and not one byte on the process output: under
			// RoadRunner that output is the protocol pipe.
			self::assertSame(1, $line['sent'], $path . ': the sink did not get exactly one response');
			self::assertSame(0, $line['output'], $path . ': something was written around the sink');
		}

		self::assertSame(404, $sinks['/no-such-route']['status']);
		self::assertSame(204, $sinks['/responds-mid-handler']['status']);
	}

	public function testFinishHookRunsAfterTheSinkSentTheResponse(): void
	{
		$sinks = self::sinkLines();

		// FinishHook is "after the response was sent", whichever way it left.
		self::assertSame('send,finish', $sinks['/']['events']);
		self::assertSame('send,finish', $sinks['/no-such-route']['events']);
		self::assertSame('send,finish', $sinks['/responds-mid-handler']['events']);
	}

	public function testTheLastResortErrorPageGoesToTheSinkToo(): void
	{
		$critical = self::sinkLines()['/critical'];

		self::assertSame(500, $critical['status']);
		self::assertSame(0, $critical['output']);
		self::assertStringContainsString('unhandled error', $critical['body']);
	}

	public function testARequestBuiltFromPartsIsServedAsBuilt(): void
	{
		$echo = self::sinkLines()['/echo'];

		self::assertSame(200, $echo['status']);
		self::assertSame(['parsed' => ['a' => 1], 'client_ip' => '10.1.2.3'], \json_decode($echo['body'], true));
	}

	public function testACrashOutsideARequestEndsTheProcessLoudly(): void
	{
		$process = new Process([\PHP_BINARY, \dirname(__DIR__) . '/Support/worker_crash.php']);
		$process->setTimeout(120);
		$process->run();

		// A supervisor reads the status: 0 would be a clean stop, and the server would not say why.
		self::assertSame(1, $process->getExitCode(), $process->getErrorOutput());
		self::assertStringContainsString(
			'RuntimeException: the worker loop itself failed',
			$process->getErrorOutput()
		);
	}

	public function testTheConfigurationIsBuiltOnce(): void
	{
		$out = self::runLoop();

		self::assertStringNotContainsString('same_router=no', $out);
		self::assertStringContainsString('same_router=yes', $out);
	}

	/**
	 * The requests the loop served through a response sink, by path.
	 *
	 * @return array<string, array{status: int, sent: int, output: int, events: string, body: string}>
	 */
	private static function sinkLines(): array
	{
		\preg_match_all(
			'~^sink \d+ path=(\S+) status=(\d+) sent=(\d+) output=(\d+) events=(\S+) body=(.*)$~m',
			self::runLoop(),
			$m,
			\PREG_SET_ORDER
		);

		$lines = [];

		foreach ($m as $line) {
			$lines[$line[1]] = [
				'status' => (int) $line[2],
				'sent'   => (int) $line[3],
				'output' => (int) $line[4],
				'events' => $line[5],
				'body'   => $line[6],
			];
		}

		return $lines;
	}

	/**
	 * Runs the worker script once for the whole class -- it bootstraps a sandbox project of its own,
	 * which is the slow part.
	 */
	private static function runLoop(): string
	{
		if (null === self::$output) {
			$process = new Process([\PHP_BINARY, \dirname(__DIR__) . '/Support/worker_loop.php']);
			$process->setTimeout(120);
			$process->run();

			self::assertSame(
				0,
				$process->getExitCode(),
				\sprintf("the worker loop failed:\n%s", $process->getErrorOutput()),
			);

			self::$output = $process->getErrorOutput();
		}

		return self::$output;
	}
}
