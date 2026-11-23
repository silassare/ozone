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

namespace OZONE\Tests\Support;

use PHPUnit\Framework\Assert;

/**
 * A throwaway directory tree standing for a machine's binaries: files made executable or not, and
 * never run -- what is under test only looks at them.
 */
final class ExecutableTree
{
	/**
	 * A fresh, empty tree, in a temporary directory whose files can be executable.
	 *
	 * The image's TMPDIR is a tmpfs mounted `noexec`, where `is_executable()` is false of every file,
	 * which would make a test pass or fail for that reason alone; so the first temporary directory
	 * that can hold an executable file is taken, and none fails the test rather than passing it.
	 */
	public static function create(): string
	{
		foreach ([\sys_get_temp_dir(), '/tmp', '/var/tmp'] as $dir) {
			$probe = $dir . \DIRECTORY_SEPARATOR . 'oz_exe_probe_' . \getmypid();

			if (!\is_dir($dir) || !\is_writable($dir) || false === @\file_put_contents($probe, '')) {
				continue;
			}

			\chmod($probe, 0o755);
			$executable = \is_executable($probe);
			\unlink($probe);

			if ($executable) {
				$root = $dir . \DIRECTORY_SEPARATOR . 'oz_exe_tree_' . \bin2hex(\random_bytes(4));

				\mkdir($root, 0o775, true);

				return $root;
			}
		}

		Assert::fail('No temporary directory here can hold an executable file.');
	}

	/**
	 * A file of the tree, its directory created, with that mode.
	 */
	public static function file(string $root, string $relative, int $mode = 0o755): string
	{
		$path = $root . \DIRECTORY_SEPARATOR . $relative;

		if (!\is_dir(\dirname($path))) {
			\mkdir(\dirname($path), 0o775, true);
		}

		\file_put_contents($path, "#!/bin/sh\nexit 99\n");
		\chmod($path, $mode);

		return $path;
	}

	/**
	 * Removes a tree, or anything else at that path.
	 */
	public static function remove(string $path): void
	{
		if (\is_link($path) || \is_file($path)) {
			\unlink($path);

			return;
		}

		if (!\is_dir($path)) {
			return;
		}

		foreach (\scandir($path) ?: [] as $entry) {
			if ('.' !== $entry && '..' !== $entry) {
				self::remove($path . \DIRECTORY_SEPARATOR . $entry);
			}
		}

		\rmdir($path);
	}
}
