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
	public const DIRECTORY_PERMISSIONS = 0770;

	/**
	 * Files default permissions.
	 *
	 * Owner can rw
	 * Group can rw
	 * Other can ---
	 */
	public const FILE_PERMISSIONS = 0660;

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
	 *
	 * @return $this
	 */
	public function apply(array $structure): self
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
}
