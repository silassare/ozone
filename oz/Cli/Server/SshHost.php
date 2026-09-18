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

namespace OZONE\Core\Cli\Server;

use InvalidArgumentException;
use Override;
use OZONE\Core\Cli\Server\Interfaces\HostInterface;
use Symfony\Component\Process\Process;

/**
 * Class SshHost.
 *
 * A server reached with the system `ssh` client. OZone stores no key: the client's own configuration,
 * `ssh-agent`, or an identity file given explicitly authenticate. One connection is opened and reused
 * (`ControlMaster`), since a plan asks the host many small questions. A host key seen for the first time
 * is accepted and remembered (`StrictHostKeyChecking=accept-new`); a changed one is refused.
 *
 * What is asked of the host is plain POSIX shell (`test`, `readlink`, `cat`, `id`), so a BusyBox system
 * such as Alpine answers as Debian does. Files travel through the connection's standard input.
 *
 * `OZ_SSH_COMMAND` replaces the client and its first arguments, as `GIT_SSH_COMMAND` does for git.
 */
final class SshHost implements HostInterface
{
	/**
	 * @param string       $destination `user@host`, or a host of the client's configuration
	 * @param int          $port        the SSH port
	 * @param null|string  $identity    a private key file, instead of the agent's keys
	 * @param float        $timeout     seconds a single command may take; package installs are slow
	 * @param list<string> $options     more `-o Name=value` client options, as `Name=value`
	 */
	public function __construct(
		private readonly string $destination,
		private readonly int $port = 22,
		private readonly ?string $identity = null,
		private readonly float $timeout = 900.0,
		private readonly array $options = [],
	) {
		if ('' === $destination || \str_starts_with($destination, '-')) {
			throw new InvalidArgumentException(\sprintf('Invalid SSH destination: "%s".', $destination));
		}
	}

	/**
	 * Builds a host from `user@host[:port]`.
	 *
	 * @param list<string> $options
	 */
	public static function fromTarget(string $target, ?string $identity = null, array $options = []): self
	{
		$port = 22;

		if (\preg_match('~^(.+):(\d+)$~', $target, $matches)) {
			$target = $matches[1];
			$port   = (int) $matches[2];
		}

		return new self($target, $port, $identity, 900.0, $options);
	}

	/**
	 * The `ssh` command line that runs a shell command on the host.
	 *
	 * @return list<string>
	 */
	public function sshCommand(string $command): array
	{
		$args = [
			...self::client(),
			'-p',
			(string) $this->port,
			'-o',
			'BatchMode=yes',
			'-o',
			'StrictHostKeyChecking=accept-new',
			'-o',
			'ControlMaster=auto',
			'-o',
			'ControlPath=' . self::controlDir() . '/oz-ssh-%C',
			'-o',
			'ControlPersist=60',
		];

		if (null !== $this->identity && '' !== $this->identity) {
			\array_push($args, '-i', $this->identity, '-o', 'IdentitiesOnly=yes');
		}

		foreach ($this->options as $option) {
			\array_push($args, '-o', $option);
		}

		\array_push($args, $this->destination, '--', $command);

		return $args;
	}

	#[Override]
	public function run(string $command, string &$output = ''): int
	{
		[$code, $stdout, $stderr] = $this->exec($command);

		$output = $stdout . $stderr;

		return $code;
	}

	#[Override]
	public function describe(): string
	{
		return 22 === $this->port ? $this->destination : $this->destination . ':' . $this->port;
	}

	#[Override]
	public function isLocal(): bool
	{
		return false;
	}

	#[Override]
	public function hasBinary(string $binary): bool
	{
		return $this->succeeds(\sprintf('command -v %s >/dev/null 2>&1', \escapeshellarg($binary)));
	}

	#[Override]
	public function isRoot(): bool
	{
		return $this->succeeds('[ "$(id -u)" = 0 ]');
	}

	#[Override]
	public function exists(string $path): bool
	{
		return $this->succeeds('test -e ' . \escapeshellarg($path));
	}

	#[Override]
	public function isDir(string $path): bool
	{
		return $this->succeeds('test -d ' . \escapeshellarg($path));
	}

	#[Override]
	public function isLink(string $path): bool
	{
		return $this->succeeds('test -L ' . \escapeshellarg($path));
	}

	#[Override]
	public function readLink(string $path): ?string
	{
		$quoted          = \escapeshellarg($path);
		[$code, $stdout] = $this->exec(\sprintf('test -L %s && readlink %s', $quoted, $quoted));

		return 0 === $code ? \rtrim($stdout, "\n") : null;
	}

	#[Override]
	public function listDirs(string $path): array
	{
		// The glob stays outside the quotes; `[ -d ]` drops the pattern itself when nothing matched.
		[$code, $stdout] = $this->exec(\sprintf(
			'for d in %s/*/; do [ -d "$d" ] && basename "$d"; done; true',
			\escapeshellarg(\rtrim($path, '/'))
		));

		if (0 !== $code) {
			return [];
		}

		$dirs = \array_values(\array_filter(\explode("\n", $stdout), static fn (string $d): bool => '' !== $d));

		\sort($dirs);

		return $dirs;
	}

	#[Override]
	public function readFile(string $path): ?string
	{
		$quoted          = \escapeshellarg($path);
		[$code, $stdout] = $this->exec(\sprintf('test -f %s && cat %s', $quoted, $quoted));

		return 0 === $code ? $stdout : null;
	}

	#[Override]
	public function writeFile(string $path, string $content): bool
	{
		return 0 === $this->exec(self::writeCommand($path), $content)[0];
	}

	#[Override]
	public function upload(string $local_path, string $path): bool
	{
		$stream = @\fopen($local_path, 'rb');

		if (false === $stream) {
			return false;
		}

		try {
			return 0 === $this->exec(self::writeCommand($path), $stream)[0];
		} finally {
			\fclose($stream);
		}
	}

	/**
	 * Runs a command on the host.
	 *
	 * @param null|resource|string $input what the command reads on its standard input
	 *
	 * @return array{0: int, 1: string, 2: string} the exit code, stdout and stderr
	 */
	private function exec(string $command, mixed $input = null): array
	{
		$process = new Process($this->sshCommand($command), null, null, $input, $this->timeout);

		$process->run();

		return [(int) $process->getExitCode(), $process->getOutput(), $process->getErrorOutput()];
	}

	private function succeeds(string $command): bool
	{
		return 0 === $this->exec($command)[0];
	}

	/**
	 * Writes the standard input to a file, creating its directory.
	 */
	private static function writeCommand(string $path): string
	{
		return \sprintf(
			'mkdir -p %s && cat > %s',
			\escapeshellarg(\dirname($path)),
			\escapeshellarg($path)
		);
	}

	/**
	 * The client and its first arguments: `ssh`, or the words of `OZ_SSH_COMMAND` (as git's
	 * `GIT_SSH_COMMAND`: a client configuration, known hosts kept apart, a wrapper). Split on spaces:
	 * no quoting.
	 *
	 * @return list<string>
	 */
	private static function client(): array
	{
		$command = \trim((string) \getenv('OZ_SSH_COMMAND'));

		if ('' === $command) {
			return ['ssh'];
		}

		return \array_values(\array_filter(
			(array) \preg_split('~\s+~', $command),
			static fn ($word): bool => \is_string($word) && '' !== $word
		));
	}

	/**
	 * Where the shared connection's socket lives: a Unix socket path is limited to about 100 bytes, so a
	 * long temporary directory falls back to /tmp.
	 */
	private static function controlDir(): string
	{
		$dir = \rtrim(\sys_get_temp_dir(), '/');

		return \strlen($dir) > 60 ? '/tmp' : $dir;
	}
}
