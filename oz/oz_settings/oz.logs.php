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
	 * The size, in bytes, beyond which the log file is rotated (`ozone.root.log` ->
	 * `ozone.root.1.log`, ...).
	 */
	'OZ_LOG_MAX_FILE_SIZE' => 5_000_000, // 5MB

	/**
	 * How many rotated log files are kept; 0 discards the old file at each rotation.
	 *
	 * @default 5
	 */
	'OZ_LOG_MAX_FILES' => 5,

	/**
	 * Enable or disable logging of execution time.
	 */
	'OZ_LOG_EXECUTION_TIME_ENABLED' => false,

	/**
	 * The log level to use. Messages with a level equal to or higher than this will be logged.
	 * Valid levels are: 'emergency', 'alert', 'critical', 'error', 'warning', 'notice', 'info', 'debug'.
	 */
	'OZ_LOG_LEVEL' => 'debug',

	/**
	 * Mask secret-looking values (passwords, tokens, API keys, ...) in exception data, both in
	 * logs and in error responses sent to clients (`ErrorUtils::redactSensitiveData()`).
	 *
	 * - null: enabled in production (`ENV_MODE=production`), disabled otherwise;
	 * - true / false: always enabled / disabled.
	 *
	 * `ENV_MODE` defaults to development when unset: keep this enabled on any server others
	 * can reach.
	 *
	 * @default null
	 */
	'OZ_REDACT_SENSITIVE_DATA' => null,
];
