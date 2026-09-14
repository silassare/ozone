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

namespace OZONE\Core\FS\Scan\Interfaces;

use OZONE\Core\Exceptions\RuntimeException;
use OZONE\Core\FS\FileStream;
use OZONE\Core\FS\Scan\FileScanResult;

/**
 * Interface FileScannerInterface.
 *
 * A virus scanner, set in `OZ_FILE_SCANNER` (`oz.files.scan`).
 */
interface FileScannerInterface
{
	/**
	 * Creates the scanner from the `oz.files.scan` settings.
	 *
	 * @return static
	 */
	public static function fromSettings(): static;

	/**
	 * Scans a file content.
	 *
	 * @param FileStream $content the content, scanned from its start
	 *
	 * @return FileScanResult
	 *
	 * @throws RuntimeException when the content could not be scanned
	 */
	public function scan(FileStream $content): FileScanResult;
}
