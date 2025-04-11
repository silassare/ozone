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
use OZONE\Core\Logger\LogWriter;

return [
	/**
	 * The log writer class to use for logging.
	 */
	'OZ_LOG_WRITER' => LogWriter::class,

	/**
	 * The maximum size of the log file in bytes.
	 */
	'OZ_LOG_MAX_FILE_SIZE' => 5_000_000, // 5MB

	/**
	 * Enable or disable logging of execution time.
	 */
	'OZ_LOG_EXECUTION_TIME_ENABLED' => false,
];
