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

namespace OZONE\OZ\FS;

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
}
