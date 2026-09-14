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

namespace OZONE\Core\FS;

use OZONE\Core\Exceptions\RuntimeException;
use PHPUtils\FS\FSUtils;
use PHPUtils\FS\PathUtils;

/**
 * Class FilesManager.
 */
class FilesManager extends FSUtils
{
	/**
	 * Directories default permissions.
	 *
	 * Owner can rwx
	 * Group can rwx
	 * Other can ---
	 */
	public const DIRECTORY_PERMISSIONS = 0o770;

	/**
	 * Files default permissions.
	 *
	 * Owner can rw
	 * Group can rw
	 * Other can ---
	 */
	public const FILE_PERMISSIONS = 0o660;

	/**
	 * FilesManager constructor.
	 *
	 * @param string $root the directory root path
	 */
	public function __construct(string $root = '.')
	{
		parent::__construct(PathUtils::resolve(OZ_PROJECT_DIR, $root));
	}

	/**
	 * Apply a structure to the current directory.
	 *
	 * The structure is an array of files and directories.
	 * Each file or directory is represented by an array with the following keys:
	 * - type: string, the type of the file or directory, can be 'file' or 'dir'
	 * - content: string, the content of the file
	 * - copy: string, the path of the file to copy
	 * - children: array, the children of the directory
	 * - permissions: int, the permissions of the file or directory.
	 *
	 * @param array $structure
	 */
	public function apply(array $structure): static
	{
		foreach ($structure as $key => $options) {
			$type = $options['type'] ?? null;
			$perm = $options['permissions'] ?? null;

			switch ($type) {
				case 'dir':
					$root = $this->getRoot();

					$this->cd($key, true);

					if (!empty($options['children'])) {
						$this->apply($options['children']);
					}

					$this->cd($root);

					break;

				case 'file':
					if (isset($options['copy'])) {
						$this->cp($options['copy'], $key);
					} else {
						$this->wf($key, $options['content'] ?? '');
					}

					break;

				default:
					throw new RuntimeException('Invalid directory structure. Unknown type: ' . $type, [
						$key => $options,
					]);
			}

			if (null !== $perm) {
				\chmod($this->resolve($key), $perm);
			}
		}

		return $this;
	}

	/**
	 * Writes a file atomically: a reader sees either the old content or the new one, never a
	 * half-written file.
	 *
	 * `wf()` opens the destination and writes into it, so a concurrent reader can catch it
	 * truncated. That is tolerable for a cache under `.ozone/`, and not for anything under `data/`,
	 * which may be on a volume shared between instances. The temporary file is created in the same
	 * directory on purpose: `rename()` is only atomic within one filesystem.
	 *
	 * @param string $path    the file to write
	 * @param string $content the content
	 *
	 * @return static
	 *
	 * @throws RuntimeException when the file cannot be written
	 */
	public function writeAtomic(string $path, string $content): static
	{
		$abs_path = $this->resolve($path);
		$tmp_path = $abs_path . '.' . \bin2hex(\random_bytes(6)) . '.tmp';

		if (false === \file_put_contents($tmp_path, $content)) {
			throw new RuntimeException(\sprintf('Unable to write at: "%s".', $tmp_path));
		}

		\chmod($tmp_path, self::FILE_PERMISSIONS);

		if (!\rename($tmp_path, $abs_path)) {
			@\unlink($tmp_path);

			throw new RuntimeException(\sprintf('Unable to replace: "%s".', $abs_path));
		}

		return $this;
	}

	/**
	 * Returns a relative path from a given path.
	 *
	 * @param string $path
	 * @param string $from
	 *
	 * @return string
	 */
	public function relativePath(string $path, string $from = '.'): string
	{
		$from = $this->resolve($from);
		$to   = $this->resolve($path);

		$explode_from = \explode(DS, $from);
		$explode_to   = \explode(DS, $to);

		while (!empty($explode_from) && !empty($explode_to) && $explode_from[0] === $explode_to[0]) {
			\array_shift($explode_from);
			\array_shift($explode_to);
		}

		return \str_repeat('..' . DS, \count($explode_from)) . \implode(DS, $explode_to);
	}
}
