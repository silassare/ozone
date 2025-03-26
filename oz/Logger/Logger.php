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
use Psr\Log\LogLevel;
use Stringable;
use Throwable;

/**
 * Class Logger.
 */
class Logger implements LoggerInterface
{
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
	 * {@inheritDoc}
	 */
	public function log($level, string|Stringable|Throwable $message, array $context = []): void
	{
		if ($message instanceof Throwable) {
			$message = self::describe($message);
		}

		$date  = \date('Y-m-d H:i:s');
		$level = \strtoupper($level);

		$message = <<<LOG
================================================================================
[{$level}] {$date}
================================================================================
{$message}


LOG;

		if (\defined('OZ_LOG_DIR')) {
			$dir = OZ_LOG_DIR;
		} else {
			$dir = \getcwd();
		}

		$log_file = $dir . 'debug.log';

		$mode = (\file_exists($log_file) && \filesize($log_file) <= OZ_LOG_MAX_FILE_SIZE) ? 'a' : 'w';

		if ($fp = \fopen($log_file, $mode)) {
			\fwrite($fp, $message);
			\fclose($fp);

			if ('w' === $mode) {
				\chmod($log_file, 0660);
			}
		}
	}

	/**
	 * {@inheritDoc}
	 */
	public function emergency(string|Stringable|Throwable $message, array $context = []): void
	{
		$this->log(LogLevel::EMERGENCY, $message, $context);
	}

	/**
	 * {@inheritDoc}
	 */
	public function alert(string|Stringable|Throwable $message, array $context = []): void
	{
		$this->log(LogLevel::ALERT, $message, $context);
	}

	/**
	 * {@inheritDoc}
	 */
	public function critical(string|Stringable|Throwable $message, array $context = []): void
	{
		$this->log(LogLevel::CRITICAL, $message, $context);
	}

	/**
	 * {@inheritDoc}
	 */
	public function error(string|Stringable|Throwable $message, array $context = []): void
	{
		$this->log(LogLevel::ERROR, $message, $context);
	}

	/**
	 * {@inheritDoc}
	 */
	public function warning(string|Stringable|Throwable $message, array $context = []): void
	{
		$this->log(LogLevel::WARNING, $message, $context);
	}

	/**
	 * {@inheritDoc}
	 */
	public function notice(string|Stringable|Throwable $message, array $context = []): void
	{
		$this->log(LogLevel::NOTICE, $message, $context);
	}

	/**
	 * {@inheritDoc}
	 */
	public function info(string|Stringable|Throwable $message, array $context = []): void
	{
		$this->log(LogLevel::INFO, $message, $context);
	}

	/**
	 * {@inheritDoc}
	 */
	public function debug(string|Stringable|Throwable $message, array $context = []): void
	{
		$this->log(LogLevel::DEBUG, $message, $context);
	}

	protected static function write($level, string $message, array $context = []) {}
}
