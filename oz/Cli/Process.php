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
use OZONE\Core\App\Settings;
use OZONE\Core\Cli\Server\Interfaces\HostInterface;
use OZONE\Core\Exceptions\RuntimeException;
use OZONE\Core\OZone;
use OZONE\Core\Runtime\Runtime;
use PHPUtils\FS\PathUtils;
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
 *
 * A process started while this one serves requests is given {@see CHILD_ENV} of its environment
 * and what its caller passes, nothing else ({@see start()}): whoever built it, so no caller can
 * forget it. A command that needs more names it.
 *
 * A process running PHP is started with {@see php()}, with {@see phpBinary()}: never with
 * `PHP_BINARY`, which is the command line's only when this process is one.
 */
final class Process extends SymfonyProcess
{
	/**
	 * The settings group of {@see PHP_CLI_SETTING}: one a request cannot rewrite
	 * ({@see Settings::set()} refuses it), since the value is a program OZone executes.
	 */
	public const string PHP_CLI_SETTING_GROUP = 'oz.config';

	/**
	 * The setting naming the PHP command-line binary a PHP process is started with: {@see phpBinary()}.
	 */
	public const string PHP_CLI_SETTING = 'OZ_PHP_CLI_BINARY';

	/**
	 * The SAPIs whose own binary is the command line's: the built-in server is `php -S`, a mode of
	 * that same executable.
	 */
	private const array CLI_SAPIS = ['cli', 'cli-server'];

	/**
	 * The variables a process started while this one serves requests is given of its environment,
	 * every other one removed: where programs are found and files written (PATH, HOME, TMPDIR), and
	 * what a program's text and clock read (LANG, LC_ALL, TZ).
	 *
	 * A list of what is kept, not of what is dropped. Under CGI the request itself is environment
	 * -- every header as `HTTP_*`, the cookie and the `Authorization` among them, and whatever the
	 * server adds (`SSL_CLIENT_*`, `UNIQUE_ID`, `CONTEXT_*`, a site's own FastCGI parameters) -- and
	 * a list of what to drop is out of date with the first server that adds one more. The worker
	 * servers' variables go too: `RR_MODE`, `FRANKENPHP_WORKER` or `OZ_RUNTIME` in a job's
	 * environment would have `oz jobs run` take itself for a worker ({@see Runtime::detect()}).
	 * The project's own values are not passed either: an OZone process in a project reads its `.env`
	 * itself, from the file, and never from its environment.
	 */
	public const array CHILD_ENV = ['PATH', 'HOME', 'TMPDIR', 'LANG', 'LC_ALL', 'TZ'];

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
	 * While this process serves requests, the process is given {@see CHILD_ENV} of this one's
	 * environment and the variables its caller passed -- to the constructor, to `setEnv()` or here --
	 * and nothing else ({@see childEnv()}). Here, where `run()`, `mustRun()` and `restart()` all
	 * start, so that whoever built the process cannot leave it out. A command line passes its
	 * environment on, as before.
	 */
	#[Override]
	public function start(?callable $callback = null, array $env = []): void
	{
		if (!Runtime::isConsole()) {
			/** @var array<string, false|string> $given */
			$given = $env + $this->getEnv();
			$env   = self::childEnv(false, $_ENV + \getenv(), $given) ?? [];
		}

		parent::start($callback, $env);
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
	 * A PHP process: `$args` run by {@see phpBinary()}. The way every PHP process OZone starts is
	 * built, a plugin's own included; its environment is what every process gets ({@see start()}).
	 *
	 * @param list<string>               $args  the script and its arguments
	 * @param null|array<string, string> $env   variables to set, over what the process inherits
	 * @param null|float                 $timeout seconds, 0 or null for none
	 */
	public static function php(
		array $args,
		?string $cwd = null,
		?array $env = null,
		mixed $input = null,
		?float $timeout = 0
	): self {
		return new self([self::phpBinary(), ...$args], $cwd, $env, $input, $timeout);
	}

	/**
	 * The environment of a process this one starts, from what it would pass on.
	 *
	 * A command line passes its environment on, as a shell does: null, or the variables given. A
	 * process that serves requests -- under CGI, PHP-FPM or a worker server -- passes {@see CHILD_ENV}
	 * and nothing else: every other variable it holds is set to false, which is how Symfony's
	 * process is told not to pass one on. The variables given are set over that.
	 *
	 * @param bool                             $console   whether this process is a command line
	 * @param array<array-key, mixed>          $inherited what it would pass on: its `$_ENV` and `getenv()`
	 * @param null|array<string, false|string> $env       the variables given (false: not passed on)
	 *
	 * @return null|array<string, false|string>
	 *
	 * @internal
	 */
	public static function childEnv(bool $console, array $inherited, ?array $env): ?array
	{
		if ($console) {
			return $env;
		}

		$clean = [];

		foreach ($inherited as $name => $value) {
			$name         = (string) $name;
			$clean[$name] = \in_array($name, self::CHILD_ENV, true) && \is_string($value) ? $value : false;
		}

		return \array_merge($clean, $env ?? []);
	}

	/**
	 * The PHP command-line binary to start a PHP process with -- `oz jobs run` for an async job, a
	 * scope's build, a plugin's own script -- whatever runs this one.
	 *
	 * `PHP_BINARY` is the binary of *this* process, which is the command line's only under the `cli`
	 * SAPI and the built-in server (`php -S`, a mode of the same executable). A request runs a cron
	 * tick in its own process when no scheduler does, and there it is php-fpm, php-cgi or a server
	 * embedding PHP -- FrankenPHP leaves it empty --, none of which runs a script given to it as an
	 * argument.
	 *
	 * In order:
	 * - `OZ_PHP_CLI_BINARY` (`oz.config`, or the `.env`) when it is set: the absolute path of an
	 *   executable file, or this throws, since a binary the project named is never swapped for a
	 *   guess;
	 * - `PHP_BINARY`, under a command-line SAPI;
	 * - a `php` beside `PHP_BINARY`, then in `PHP_BINDIR`, then on PATH, this version's own name
	 *   first in each (`php8.5`, `php85`), since a plain `php` may be another version's. `PHP_BINDIR`
	 *   is where this build installed its command line: Debian puts PHP-FPM in `sbin/` and the CLI
	 *   in `bin/`, and a PHP-FPM pool clears its environment by default, PATH with it;
	 * - else it throws, naming the setting.
	 *
	 * Nothing is run to find out, so nothing is started on every call: a candidate is an executable
	 * file that is not this process's own binary, which a SAPI other than the command line's has
	 * just shown not to be one. Anything else a machine has is named in the setting.
	 *
	 * @throws RuntimeException naming `OZ_PHP_CLI_BINARY` when it is unusable or nothing is found
	 */
	public static function phpBinary(): string
	{
		return self::findPhpBinary(
			self::configuredPhpBinary(),
			\PHP_BINARY,
			\PHP_SAPI,
			\PHP_BINDIR,
			\getenv('PATH'),
			self::phpVersion()
		);
	}

	/**
	 * The PHP command-line binary of a host OZone deploys to or writes the units of
	 * (`oz deploy run`, `oz deploy init`, `oz jobs supervisor`): the one given (`--php`), else, on
	 * this machine, {@see phpBinary()}, else the first of {@see phpNames()} the host has on its PATH,
	 * asked of the host itself ({@see HostInterface::findExecutable()}).
	 *
	 * A binary given is the absolute path of an executable file on the host, or this throws: what
	 * was named is never swapped for a guess. The version looked for is this one, which a deploy
	 * already expects the host to run (the PHP-FPM pool of `oz deploy init` is this version's).
	 *
	 * @throws RuntimeException when the binary given is unusable, or the host has none
	 */
	public static function hostPhpBinary(HostInterface $host, ?string $given = null): string
	{
		if (null !== $given && '' !== $given) {
			if (!\str_starts_with($given, '/') || !$host->isExecutableFile($given)) {
				throw new RuntimeException(\sprintf(
					'--php is "%s", which is not the absolute path of an executable file on %s.',
					$given,
					$host->describe()
				));
			}

			return $given;
		}

		if ($host->isLocal()) {
			return self::phpBinary();
		}

		$names = self::phpNames(self::phpVersion());

		return $host->findExecutable($names) ?? throw new RuntimeException(\sprintf(
			'%s has none of %s on its PATH. Install the PHP command line there, or give its path with --php.',
			$host->describe(),
			\implode(', ', $names)
		));
	}

	/**
	 * The names a PHP command line goes by, most specific first: this version's own (`php8.5` as
	 * Debian names it, `php85` as Alpine does), since a plain `php` may be another version's.
	 *
	 * @param string $version `major.minor`
	 *
	 * @return list<string>
	 */
	public static function phpNames(string $version): array
	{
		return ['php' . $version, 'php' . \str_replace('.', '', $version), 'php'];
	}

	/**
	 * What `OZ_PHP_CLI_BINARY` says, unchecked: null where it is not set (null or '').
	 *
	 * Read only while an app runs: with none there is no project to have set it, and reading a group
	 * before the app has added its sources would keep it without them.
	 */
	public static function configuredPhpBinary(): mixed
	{
		if (!OZone::isRunning()) {
			return null;
		}

		$value = Settings::get(self::PHP_CLI_SETTING_GROUP, self::PHP_CLI_SETTING);

		return '' === $value ? null : $value;
	}

	/**
	 * The rules of {@see phpBinary()}, given what it reads of this process, so that each of them can
	 * be asked about a process this one is not.
	 *
	 * @param mixed        $configured the value of `OZ_PHP_CLI_BINARY`, null when it is not set
	 * @param string       $binary     the process's own binary, `PHP_BINARY`
	 * @param string       $sapi       its SAPI, `PHP_SAPI`
	 * @param string       $bindir     where its build installed its binaries, `PHP_BINDIR`
	 * @param false|string $path       its PATH, false when it has none
	 * @param string       $version    its PHP version, `major.minor`
	 *
	 * @throws RuntimeException naming `OZ_PHP_CLI_BINARY` when it is unusable or nothing is found
	 *
	 * @internal
	 */
	public static function findPhpBinary(
		mixed $configured,
		string $binary,
		string $sapi,
		string $bindir,
		false|string $path,
		string $version
	): string {
		if (null !== $configured) {
			if (
				!\is_string($configured)
				|| PathUtils::isRelative($configured)
				|| !\is_file($configured)
				|| !\is_executable($configured)
			) {
				throw new RuntimeException(\sprintf(
					'%s is %s, which is not the absolute path of an executable file.',
					self::PHP_CLI_SETTING,
					\is_string($configured) ? '"' . $configured . '"' : \get_debug_type($configured)
				))->suspectConfig(self::PHP_CLI_SETTING_GROUP, self::PHP_CLI_SETTING);
			}

			return $configured;
		}

		$own = '' === $binary ? false : \realpath($binary);

		if (false !== $own && \in_array($sapi, self::CLI_SAPIS, true) && \is_executable($own)) {
			return $binary;
		}

		$dirs = ['' === $binary ? '' : \dirname($binary), $bindir];

		if (false !== $path) {
			\array_push($dirs, ...\explode(\PATH_SEPARATOR, $path));
		}

		$dirs  = \array_values(\array_unique(\array_filter($dirs, static fn (string $dir): bool => '' !== $dir)));
		$names = self::phpNames($version);

		foreach ($dirs as $dir) {
			foreach ($names as $name) {
				$found = self::which($name, [$dir]);

				if (null !== $found && \realpath($found) !== $own) {
					return $found;
				}
			}
		}

		throw new RuntimeException(\sprintf(
			'No PHP command-line binary to start a PHP process with: this process runs under the "%s" SAPI'
				. ' (%s), and none of %s is in %s. Set %s (%s, or the .env) to its absolute path.',
			$sapi,
			'' === $binary ? 'PHP_BINARY is empty' : 'PHP_BINARY is ' . $binary,
			\implode(', ', $names),
			[] === $dirs
				? 'any directory: there is no PHP_BINARY, PHP_BINDIR or PATH'
				: \implode(\PATH_SEPARATOR, $dirs),
			self::PHP_CLI_SETTING,
			self::PHP_CLI_SETTING_GROUP
		), [
			'_sapi'     => $sapi,
			'_binary'   => $binary,
			'_searched' => $dirs,
		])->suspectConfig(self::PHP_CLI_SETTING_GROUP, self::PHP_CLI_SETTING);
	}

	/**
	 * The first executable file of that name in the directories, PATH's when none are given, or null.
	 *
	 * @param null|list<string> $dirs
	 */
	public static function which(string $name, ?array $dirs = null): ?string
	{
		$dirs ??= \explode(\PATH_SEPARATOR, (string) \getenv('PATH'));

		foreach ($dirs as $dir) {
			if ('' === $dir) {
				continue;
			}

			$file = \rtrim($dir, \DIRECTORY_SEPARATOR) . \DIRECTORY_SEPARATOR . $name;

			if (\is_file($file) && \is_executable($file)) {
				return $file;
			}
		}

		return null;
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

	/**
	 * The version of this PHP, `major.minor`.
	 */
	private static function phpVersion(): string
	{
		return \PHP_MAJOR_VERSION . '.' . \PHP_MINOR_VERSION;
	}

	private static function phraseFor(int $signal): string
	{
		return \sprintf('killed by signal %d (%s)', $signal, self::describeSignal($signal));
	}
}
