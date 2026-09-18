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

namespace OZONE\Core\Cli\Server\Interfaces;

use OZONE\Core\Cli\Server\LocalHost;
use OZONE\Core\Cli\Server\SshHost;

/**
 * Interface HostInterface.
 *
 * The machine `oz server provision` and `oz deploy` act on: this one ({@see LocalHost}) or a server
 * reached over SSH ({@see SshHost}). Everything they run or read on the target goes through it -- the
 * commands, the package manager and binaries it has, the provision manifest, the deploy root's layout --
 * so nothing of the machine running `oz` leaks into what is done to the target.
 *
 * Paths are the target's (POSIX, absolute).
 */
interface HostInterface extends ShellRunnerInterface
{
	/**
	 * The host, for a person to read: "this machine", or the SSH destination.
	 */
	public function describe(): string;

	/**
	 * Whether the host is the machine running `oz`.
	 */
	public function isLocal(): bool;

	/**
	 * Whether an executable is on the host's PATH.
	 */
	public function hasBinary(string $binary): bool;

	/**
	 * Whether commands run as root on the host.
	 */
	public function isRoot(): bool;

	/**
	 * Whether a path exists (a symbolic link is followed).
	 */
	public function exists(string $path): bool;

	/**
	 * Whether a path is a directory (a symbolic link is followed).
	 */
	public function isDir(string $path): bool;

	/**
	 * Whether a path is a symbolic link.
	 */
	public function isLink(string $path): bool;

	/**
	 * The target of a symbolic link, as written; null when the path is no link.
	 */
	public function readLink(string $path): ?string;

	/**
	 * The names of the directories directly inside a directory, sorted; empty when there is none.
	 *
	 * @return list<string>
	 */
	public function listDirs(string $path): array;

	/**
	 * The content of a file; null when it does not exist or cannot be read.
	 */
	public function readFile(string $path): ?string;

	/**
	 * Writes a file, creating its directory; false on failure.
	 */
	public function writeFile(string $path, string $content): bool;

	/**
	 * Copies a file of the machine running `oz` to the host, creating the directory; false on failure.
	 */
	public function upload(string $local_path, string $path): bool;
}
