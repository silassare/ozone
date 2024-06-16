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

use Error;
use Exception;
use JsonSerializable;
use OZONE\Core\App\Interfaces\AppInterface;
use OZONE\Core\Exceptions\BaseException;
use OZONE\Core\OZone;
use Throwable;

/**
 * Class Logger.
 */
class Logger
{
	/**
	 * Write to log file.
	 *
	 * @param mixed $value
	 */
	public static function log(mixed $value): void
	{
		if (\defined('OZ_LOG_DIR')) {
			$dir = OZ_LOG_DIR;
		} else {
			$dir = \getcwd();
		}

		$prev_sep = "\n========previous========\n";
		$date     = \date('Y-m-d H:i:s');
		$log_file = $dir . 'debug.log';

		if (\is_scalar($value)) {
			$log = (string) $value;
		} elseif (\is_array($value)) {
			$log = \var_export($value, true);
		} elseif ($value instanceof Exception || $value instanceof Error) {
			$e   = $value;
			$log = BaseException::throwableToString($e);

			while ($e = $e->getPrevious()) {
				$log .= $prev_sep . BaseException::throwableToString($e);
			}
		} elseif ($value instanceof JsonSerializable) {
			$log = \json_encode($value, \JSON_PRETTY_PRINT);
		} else {
			$log = \get_debug_type($value);
		}

		$log = \str_replace(['\n', '\t', '\/'], ["\n", "\t", '/'], $log);
		$log = "================================================================================\n"
			. $date . "\n"
			. "========================\n"
			. $log . "\n\n";

		$mode = (\file_exists($log_file) && \filesize($log_file) <= 254000) ? 'a' : 'w';

		if ($fp = \fopen($log_file, $mode)) {
			\fwrite($fp, $log);
			\fclose($fp);

			if ('w' === $mode) {
				\chmod($log_file, 0660);
			}
		}
	}

	/**
	 * @return string
	 */
	public static function executionTime(): string
	{
		return \number_format(\microtime(true) - OZ_OZONE_START_TIME, 3);
	}

	/**
	 * Register error handlers and shutdown function.
	 *
	 * @psalm-suppress InvalidArgument
	 */
	public static function registerHandlers(): void
	{
		\set_exception_handler(self::exceptionHandler(...));
		\set_error_handler(static function (int $code, string $message, string $file, int $line) {
			self::errorHandler($code, $message, $file, $line, true);

			return null;
		});
		\register_shutdown_function(self::shutdownErrorFunction(...));
	}

	/**
	 * Called when we should shutdown and only admin
	 * should know what is going wrong.
	 */
	protected static function criticalDieMessage(): void
	{
		BaseException::dieWithAnUnhandledErrorOccurred();
	}

	/**
	 * Get the running app or null.
	 *
	 * @return null|AppInterface
	 */
	protected static function optionalApp(): ?AppInterface
	{
		try {
			$app = app();
		} catch (Throwable) {
			$app = null;
		}

		return $app;
	}

	/**
	 * Handle unhandled exception.
	 *
	 * @param Throwable $t
	 */
	protected static function exceptionHandler(Throwable $t): void
	{
		self::log($t);

		self::optionalApp()
			?->onUnhandledThrowable($t);

		if (OZone::isCliMode()) {
			exit(\PHP_EOL . $t->getMessage() . \PHP_EOL);
		}

		self::criticalDieMessage();
	}

	/**
	 * Handle unhandled error.
	 *
	 * @param int    $code         the error code
	 * @param string $message      the error message
	 * @param string $file         the file where it occurs
	 * @param int    $line         the file line where it occurs
	 * @param bool   $die_on_fatal when true we will interrupt on fatal error
	 */
	protected static function errorHandler(int $code, string $message, string $file, int $line, bool $die_on_fatal = false): void
	{
		self::log("\n\tFile    : {$file}"
			. "\n\tLine    : {$line}"
			. "\n\tCode    : {$code}"
			. "\n\tMessage : {$message}");

		self::optionalApp()
			?->onUnhandledError($code, $message, $file, $line);

		if ($die_on_fatal) {
			$fatalist = [\E_ERROR, \E_PARSE, \E_CORE_ERROR, \E_COMPILE_ERROR, \E_USER_ERROR];

			if (\in_array($code, $fatalist, true)) {
				self::criticalDieMessage();
			}
		}
	}

	/**
	 * Try to log error after shutdown.
	 */
	protected static function shutdownErrorFunction(): void
	{
		$error = \error_get_last();

		if (null !== $error) {
			self::log(
				'::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::'
				. \PHP_EOL
				. 'OZone shutdown error'
				. \PHP_EOL . '::::::::::::::::::::::::'
			);
			self::errorHandler($error['type'], $error['message'], $error['file'], $error['line'], true);
		}

		if (\defined('OZ_LOG_EXECUTION_TIME') && OZ_LOG_EXECUTION_TIME) {
			self::log('::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::'
				. \PHP_EOL
				. 'OZone execution time'
				. \PHP_EOL . '::::::::::::::::::::::::'
				. \PHP_EOL . self::executionTime() . 's');
		}
	}
}
