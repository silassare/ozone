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

namespace OZONE\Core\Cli;

use Override;
use OZONE\Core\Exceptions\RuntimeException;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Exception\ProcessSignaledException;
use Symfony\Component\Process\Process as SymfonyProcess;

/**
 * Every process OZone runs: a build's scope, a provisioning command, a database dump, a cron task's
 * command, the development server.
 *
 * What they all have to answer for is being killed. A killed process runs nothing of its own
 * afterwards -- no error handler, no shutdown function, no report -- so what it printed before it
 * died is the only account of it there can be, and the signal is the only thing that says why.
 * Symfony reads that output off the pipes and *then* throws {@see ProcessSignaledException} out of
 * `wait()`, which took it away everywhere here: a production deploy whose scope build was killed by
 * `SIGSEGV` kept `The process has been signaled with signal "11"` and nothing else, from a frame
 * naming the builder.
 *
 * So a signal is answered the way a timeout already is ({@see
 * Server\Interfaces\ShellRunnerInterface::TIMED_OUT}): as a failing exit code, with the output
 * intact and the signal kept on the object, so a caller reads it where it reads every other failure
 * and words it with the step it belongs to ({@see killedPhrase()}).
 */
final class Process extends SymfonyProcess
{
	/**
	 * The signal that killed this process: {@see killedBy()}.
	 */
	private ?int $killed_by = null;

	/**
	 * Process constructor.
	 *
	 * {@inheritDoc}
	 */
	public function __construct(
		array $command,
		?string $cwd = null,
		?array $env = null,
		mixed $input = null,
		?float $timeout = 0 // 0 means no timeout limit
	) {
		parent::__construct($command, $cwd, $env, $input, $timeout);
	}

	/**
	 * Enable TTY if supported.
	 */
	public function enableTtyIfSupported(): static
	{
		if (self::isTtySupported()) {
			$this->setTty(true);
		}

		return $this;
	}

	/**
	 * {@inheritDoc}
	 *
	 * A signal does not come out of here as an exception. `run()` and `mustRun()` both wait through
	 * this method, and so does a caller that started the process itself, which is why it is the one
	 * place this is done.
	 */
	#[Override]
	public function wait(?callable $callback = null): int
	{
		try {
			return parent::wait($callback);
		} catch (ProcessSignaledException $e) {
			$this->killed_by = $e->getSignal();

			// Symfony has already applied the Unix convention to the exit code -- 128 plus the
			// signal -- which is what a shell reports for the same death, so a caller that reads
			// codes needs to know nothing new. Applied here too rather than cast: it leaves the code
			// unset where it cannot read the signal back (a PHP built with `--enable-sigchild`), and a
			// killed process must never answer the 0 a cast would give.
			return $this->getExitCode() ?? 128 + $this->killed_by;
		}
	}

	/**
	 * {@inheritDoc}
	 *
	 * A process that was killed throws OZone's own failure, naming the signal and what the process
	 * printed before it died, rather than Symfony's, whose message is the exit code it was given:
	 * `Exit Code: 139(Unknown error)` is the one reading that answers nothing.
	 *
	 * @throws RuntimeException when the process was killed by a signal
	 */
	#[Override]
	public function mustRun(?callable $callback = null, array $env = []): static
	{
		if (0 === $this->run($callback, $env)) {
			return $this;
		}

		if (null !== ($killed = $this->killedPhrase())) {
			throw new RuntimeException(\sprintf(
				'The command was %s: %s. %s',
				$killed,
				$this->getCommandLine(),
				$this->saidBeforeItDied()
			), [
				'_command' => $this->getCommandLine(),
				'_signal'  => $this->killed_by,
				'_output'  => $this->isOutputDisabled() ? null : \substr($this->getOutput(), -2000),
				'_error'   => $this->isOutputDisabled() ? null : \substr($this->getErrorOutput(), -2000),
			]);
		}

		throw new ProcessFailedException($this);
	}

	/**
	 * The signal this process was killed by, or null where it ended on its own -- whatever its exit
	 * code was -- and where it was stopped by a signal this very process asked for
	 * ({@see stop()}, {@see signal()}), which is not news.
	 */
	public function killedBy(): ?int
	{
		return $this->killed_by;
	}

	/**
	 * How this process was killed, as a phrase a caller puts its own subject in front of -- `the
	 * scope build was ...`, `provision step "packages" was ...` -- and null where it was not killed,
	 * so a caller falls back to its own wording with `??`.
	 */
	public function killedPhrase(): ?string
	{
		return null === $this->killed_by ? null : self::phraseFor($this->killed_by);
	}

	/**
	 * The signal an exit code reports, by the convention a shell uses for a killed command (128 plus
	 * the signal), or null for any other code.
	 *
	 * For a caller that has the code and not the process: a provisioning step, a cron task's result.
	 * A command that chooses to exit 137 on its own is read as killed, which is what every reader of
	 * an exit code does and the reason a process says so itself where it can ({@see killedBy()}).
	 */
	public static function signalFromExitCode(int $code): ?int
	{
		// 31 signals, and nothing above them: SIGRTMIN and up are not what a process dies of here.
		return $code > 128 && $code <= 159 ? $code - 128 : null;
	}

	/**
	 * How a killed exit code reads, as {@see killedPhrase()} words it, and null for any other code.
	 */
	public static function killedPhraseFor(int $code): ?string
	{
		$signal = self::signalFromExitCode($code);

		return null === $signal ? null : self::phraseFor($signal);
	}

	/**
	 * What a signal that killed a process means, for the person reading the log.
	 *
	 * The numbers are Linux's, and only the ones a process is killed by are named: `9` because the
	 * kernel's out-of-memory killer sends it and a deploy host is where memory runs out, `11` and
	 * `7` because a shared object replaced under a running process and a crash in an extension are
	 * what they look like. `pcntl_strsignal()` would answer for the host, and ext-pcntl is not a
	 * dependency of this package; what is left out is reported by its number, which is what a kernel
	 * log is searched with anyway.
	 */
	public static function describeSignal(int $signal): string
	{
		return match ($signal) {
			1 => 'SIGHUP, the terminal or session it belonged to went away',
			2 => 'SIGINT, an interrupt from the keyboard',
			4 => 'SIGILL, an illegal instruction',
			6 => 'SIGABRT, the process aborted itself',
			7 => 'SIGBUS, a mapped file changed or could not be paged in',
			8 => 'SIGFPE, an arithmetic fault',
			9 => 'SIGKILL, which the kernel out-of-memory killer and "kill -9" both send',
			11 => 'SIGSEGV, a crash in the process',
			13 => 'SIGPIPE, it wrote to a pipe nothing reads any more',
			15 => 'SIGTERM, something asked it to stop',
			24 => 'SIGXCPU, the CPU time limit',
			25 => 'SIGXFSZ, the file size limit',
			default => 'not one OZone names',
		};
	}

	/**
	 * What the process printed before it died, as a sentence: the only account of itself a killed
	 * process can leave, and empty-handed is worth saying too.
	 */
	public function saidBeforeItDied(): string
	{
		if ($this->isOutputDisabled()) {
			return 'Its output was not being read.';
		}

		$said = \trim($this->getErrorOutput() . \PHP_EOL . $this->getOutput());

		return '' === $said
			? 'It printed nothing.'
			: 'What it printed before it died:' . \PHP_EOL . $said;
	}

	private static function phraseFor(int $signal): string
	{
		return \sprintf('killed by signal %d (%s)', $signal, self::describeSignal($signal));
	}
}
