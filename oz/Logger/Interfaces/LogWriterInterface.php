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

namespace OZONE\Core\Logger\Interfaces;

use Stringable;
use Throwable;

interface LogWriterInterface
{
	/**
	 * Gets the log writer instance.
	 *
	 * @return static
	 */
	public static function get(): static;

	/**
	 * Writes a log message.
	 *
	 * @param string                      $level   the log level
	 * @param string|Stringable|Throwable $message the log message
	 * @param array                       $context the context for the log message
	 */
	public function write(string $level, string|Stringable|Throwable $message, array $context = []): void;
}
