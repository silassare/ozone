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
use OZONE\Core\Exceptions\RuntimeException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Exception\ProcessFailedException;

/**
 * Every process OZone runs, and the one thing they all have to answer for: being killed.
 *
 * @internal
 */
#[CoversClass(Process::class)]
final class ProcessTest extends TestCase
{
	/**
	 * A command that kills itself with the signal the out-of-memory killer sends.
	 */
	private const string SUICIDE = 'printf OZ-SAID-OUT; printf OZ-SAID-ERR >&2; kill -9 $$';

	public function testAKilledProcessAnswersAnExitCodeAndKeepsWhatItPrinted(): void
	{
		$process = new Process(['sh', '-c', self::SUICIDE]);

		// Not an exception: 128 plus the signal, as a shell reports the same death, so a caller
		// reads it where it reads every other failure. Before this, the exception came out of
		// `run()` and took the output below with it.
		self::assertSame(137, $process->run());
		self::assertFalse($process->isSuccessful());
		self::assertSame(9, $process->killedBy());

		self::assertSame('OZ-SAID-OUT', $process->getOutput());
		self::assertSame('OZ-SAID-ERR', $process->getErrorOutput());

		// The signal by number and by name, as a phrase a caller puts its own subject in front of.
		self::assertSame(
			'killed by signal 9 (SIGKILL, which the kernel out-of-memory killer and "kill -9" both send)',
			$process->killedPhrase()
		);

		$said = $process->saidBeforeItDied();

		self::assertStringContainsString('OZ-SAID-OUT', $said);
		self::assertStringContainsString('OZ-SAID-ERR', $said);
	}

	public function testAProcessThatEndedOnItsOwnWasNotKilled(): void
	{
		$process = new Process(['sh', '-c', 'exit 3']);

		self::assertSame(3, $process->run());
		self::assertNull($process->killedBy());
		self::assertNull($process->killedPhrase());

		// A failure of its own keeps Symfony's: nothing of ours is better placed to say it.
		$this->expectException(ProcessFailedException::class);

		new Process(['sh', '-c', 'exit 3'])->mustRun();
	}

	public function testMustRunOnAKilledProcessNamesTheSignalAndWhatItSaid(): void
	{
		try {
			new Process(['sh', '-c', self::SUICIDE])->mustRun();

			self::fail('A killed process must fail mustRun().');
		} catch (RuntimeException $e) {
			// Where Symfony's own answers "Exit Code: 137(Unknown error)", which is the reading
			// that says nothing.
			self::assertStringContainsString('killed by signal 9 (SIGKILL', $e->getMessage());
			self::assertStringContainsString('OZ-SAID-OUT', $e->getMessage());
			// Read with the sensitive keys: a command line carries a password often enough -- the
			// database dump's `-p` -- that none of this may reach a client-facing payload, and the log
			// is written from the whole of it.
			self::assertSame(9, $e->getData(true)['_signal'] ?? null);
			self::assertSame([], $e->getData());
		}
	}

	public function testAProcessWaitedOnByItsCallerIsCoveredToo(): void
	{
		// `run()` waits through the same method, and so does a caller that started the process
		// itself: `oz project serve` is one, and the signal used to come out of its `wait()`.
		$process = new Process(['sh', '-c', self::SUICIDE]);

		$process->start();

		self::assertSame(137, $process->wait());
		self::assertSame(9, $process->killedBy());
	}

	public function testAProcessWhoseOutputIsNotReadSaysThatInstead(): void
	{
		$process = new Process(['sh', '-c', self::SUICIDE]);

		$process->disableOutput();

		self::assertSame(137, $process->run());
		self::assertSame('Its output was not being read.', $process->saidBeforeItDied());
	}

	/**
	 * @return list<array{0: int, 1: null|int}>
	 */
	public static function exitCodes(): array
	{
		return [
			[0, null],
			[3, null],
			// The timeout a host answers with: below the signals on purpose, as `timeout(1)` has it.
			[124, null],
			[128, null],
			[137, 9],
			[139, 11],
			[159, 31],
			[160, null],
		];
	}

	#[DataProvider('exitCodes')]
	public function testTheSignalAnExitCodeReports(int $code, ?int $signal): void
	{
		self::assertSame($signal, Process::signalFromExitCode($code));

		null === $signal
			? self::assertNull(Process::killedPhraseFor($code))
			: self::assertStringContainsString(
				\sprintf('killed by signal %d', $signal),
				(string) Process::killedPhraseFor($code)
			);
	}

	public function testASignalOZoneDoesNotNameIsStillReportedByItsNumber(): void
	{
		self::assertStringContainsString('SIGSEGV', Process::describeSignal(11));
		self::assertSame('not one OZone names', Process::describeSignal(99));
	}
}
