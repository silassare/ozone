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

namespace OZONE\Core\FS\Enums;

/**
 * Enum FileScanState.
 *
 * The virus scan state of a file content (`oz_files.file_scan_state`, see `oz.files.scan`).
 */
enum FileScanState: string
{
	/**
	 * Not subject to scanning: stored while scanning was disabled.
	 */
	case UNSCANNED = 'unscanned';

	/**
	 * Waiting for its scan job (async mode).
	 */
	case PENDING = 'pending';

	case CLEAN = 'clean';

	case INFECTED = 'infected';

	/**
	 * The last scan attempt failed (async mode); the queue retries it.
	 */
	case FAILED = 'failed';
}
