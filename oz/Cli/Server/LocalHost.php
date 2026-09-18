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

use Override;
use OZONE\Core\Cli\Server\Interfaces\HostInterface;
use Symfony\Component\Process\Process;

/**
 * Class LocalHost.
 *
 * The machine running `oz`. Commands run through a shell, so that they can carry pipes and environment
 * prefixes as written.
 */
final class LocalHost implements HostInterface
{
	/**
	 * @param float $timeout seconds a single command may take; package installs are slow
	 */
	public function __construct(private readonly float $timeout = 900.0) {}

	#[Override]
	public function run(string $command, string &$output = ''): int
	{
		$process = Process::fromShellCommandline($command, null, null, null, $this->timeout);

		$process->run();

		$output = $process->getOutput() . $process->getErrorOutput();

		return (int) $process->getExitCode();
	}

	#[Override]
	public function describe(): string
	{
		return 'this machine';
	}

	#[Override]
	public function isLocal(): bool
	{
		return true;
	}

	#[Override]
	public function hasBinary(string $binary): bool
	{
		foreach (\explode(\PATH_SEPARATOR, (string) \getenv('PATH')) as $dir) {
			if ('' !== $dir && \is_executable(\rtrim($dir, DS) . DS . $binary)) {
				return true;
			}
		}

		return false;
	}

	#[Override]
	public function isRoot(): bool
	{
		return \function_exists('posix_geteuid') && 0 === \posix_geteuid();
	}

	#[Override]
	public function exists(string $path): bool
	{
		return \file_exists($path);
	}

	#[Override]
	public function isDir(string $path): bool
	{
		return \is_dir($path);
	}

	#[Override]
	public function isLink(string $path): bool
	{
		return \is_link($path);
	}

	#[Override]
	public function readLink(string $path): ?string
	{
		if (!\is_link($path)) {
			return null;
		}

		$target = \readlink($path);

		return false === $target ? null : $target;
	}

	#[Override]
	public function listDirs(string $path): array
	{
		if (!\is_dir($path)) {
			return [];
		}

		$dirs = [];

		foreach (\scandir($path) ?: [] as $entry) {
			if ('.' !== $entry && '..' !== $entry && \is_dir($path . DS . $entry)) {
				$dirs[] = $entry;
			}
		}

		\sort($dirs);

		return $dirs;
	}

	#[Override]
	public function readFile(string $path): ?string
	{
		if (!\is_file($path) || !\is_readable($path)) {
			return null;
		}

		$content = \file_get_contents($path);

		return false === $content ? null : $content;
	}

	#[Override]
	public function writeFile(string $path, string $content): bool
	{
		$dir = \dirname($path);

		if (!\is_dir($dir) && !@\mkdir($dir, 0o755, true) && !\is_dir($dir)) {
			return false;
		}

		return false !== @\file_put_contents($path, $content);
	}

	#[Override]
	public function upload(string $local_path, string $path): bool
	{
		if (\realpath($local_path) === \realpath($path)) {
			return true;
		}

		$dir = \dirname($path);

		if (!\is_dir($dir) && !@\mkdir($dir, 0o755, true) && !\is_dir($dir)) {
			return false;
		}

		return @\copy($local_path, $path);
	}
}
