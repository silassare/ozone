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

namespace OZONE\Core\Logger;

use JsonSerializable;
use OZONE\Core\Exceptions\BaseException;
use Psr\Log\LoggerInterface;
use Psr\Log\LoggerTrait;
use Stringable;
use Throwable;

/**
 * Class Logger.
 */
class Logger implements LoggerInterface
{
	use LoggerTrait;

	/**
	 * {@inheritDoc}
	 */
	public function log($level, string|Stringable $message, array $context = []): void
	{
		$message = self::addTime((string) $message);

		if (\defined('OZ_LOG_DIR')) {
			$dir = OZ_LOG_DIR;
		} else {
			$dir = \getcwd();
		}

		$log_file = $dir . 'debug.log';

		$mode = (\file_exists($log_file) && \filesize($log_file) <= 254000) ? 'a' : 'w';

		if ($fp = \fopen($log_file, $mode)) {
			\fwrite($fp, $message);
			\fclose($fp);

			if ('w' === $mode) {
				\chmod($log_file, 0660);
			}
		}
	}

	/**
	 * Returns a string representation of a value to be logged.
	 *
	 * @param mixed $value
	 *
	 * @return string
	 */
	public static function describe(mixed $value): string
	{
		$prev_sep  = "\n========previous========\n";

		if (\is_scalar($value)) {
			$log = (string) $value;
		} elseif (\is_array($value)) {
			$log = \var_export($value, true);
		} elseif ($value instanceof Throwable) {
			$e         = $value;
			$log       = BaseException::throwableToString($e);

			while ($e = $e->getPrevious()) {
				$log .= $prev_sep . BaseException::throwableToString($e);
			}
		} elseif ($value instanceof JsonSerializable) {
			/** @noinspection JsonEncodingApiUsageInspection */
			$log = \json_encode($value, \JSON_PRETTY_PRINT);
		} else {
			$log = \get_debug_type($value);
		}

		return \str_replace(['\n', '\t', '\/'], ["\n", "\t", '/'], $log);
	}

	/**
	 * Add time to message.
	 *
	 * @param string $message
	 *
	 * @return string
	 */
	private static function addTime(string $message): string
	{
		$date      = \date('Y-m-d H:i:s');

		return "================================================================================\n"
		. $date . "\n"
		. "========================\n"
		. $message . "\n\n";
	}
}
