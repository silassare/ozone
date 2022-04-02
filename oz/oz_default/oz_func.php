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

use OZONE\OZ\Exceptions\BaseException;
use OZONE\OZ\OZone;

if (!\function_exists('oz_logger')) {
	/**
	 * Write to log file.
	 *
	 * @param mixed $value
	 */
	function oz_logger(mixed $value): void
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

		$log = \str_replace(['\\n', '\\t', '\\/'], ["\n", "\t", '/'], $log);
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
	 * Called when we should shutdown and only admin
	 * should know what is going wrong.
	 */
	function oz_critical_die_message(): void
	{
		BaseException::dieWithAnUnhandledErrorOccurred();
	}

	/**
	 * Handle unhandled exception.
	 *
	 * @param \Throwable $t
	 */
	function oz_exception_handler(Throwable $t): void
	{
		oz_logger($t);

		if (OZ_OZONE_IS_CLI) {
			exit(\PHP_EOL . $t->getMessage() . \PHP_EOL);
		}

		OZone::getRunningApp()?->onUnhandledThrowable($t);

		oz_critical_die_message();
	}

	/**
	 * Handle unhandled error.
	 *
	 * @param int    $code    the error code
	 * @param string $message the error message
	 * @param string $file    the file where it occurs
	 * @param int    $line    the file line where it occurs
	 */
	function oz_error_handler(int $code, string $message, string $file, int $line): void
	{
		oz_error_logger($code, $message, $file, $line, true);
	}

	/**
	 * Log error.
	 *
	 * @param int    $code         the error code
	 * @param string $message      the error message
	 * @param string $file         the file where it occurs
	 * @param int    $line         the file line where it occurs
	 * @param bool   $die_on_fatal when true we will interrupt on fatal error
	 */
	function oz_error_logger(int $code, string $message, string $file, int $line, bool $die_on_fatal = false): void
	{
		oz_logger("\n\tFile    : {$file}"
				  . "\n\tLine    : {$line}"
				  . "\n\tCode    : {$code}"
				  . "\n\tMessage : {$message}");

		if (!OZ_OZONE_IS_CLI) {
			OZone::getRunningApp()?->onUnhandledError($code, $message, $file, $line);
		}

		if ($die_on_fatal) {
			$fatalist = [\E_ERROR, \E_PARSE, \E_CORE_ERROR, \E_COMPILE_ERROR, \E_USER_ERROR];

			if (\in_array($code, $fatalist, true)) {
				oz_critical_die_message();
			}
		}
	}

	/**
	 * Try to log error after shutdown.
	 */
	function oz_error_shutdown_function(): void
	{
		$error = \error_get_last();

		if (null !== $error) {
			oz_logger(
				'::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::'
				. \PHP_EOL
				. 'OZone shutdown error'
				. \PHP_EOL . '::::::::::::::::::::::::'
			);
			oz_error_logger($error['type'], $error['message'], $error['file'], $error['line'], true);
		}

		if (\defined('OZ_LOG_EXECUTION_TIME') && OZ_LOG_EXECUTION_TIME) {
			oz_logger('::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::'
					  . \PHP_EOL
					  . 'OZone execution time'
					  . \PHP_EOL . '::::::::::::::::::::::::'
					  . \PHP_EOL . oz_execution_time() . 's');
		}
	}

	/**
	 * @return string
	 */
	function oz_execution_time(): string
	{
		return \number_format(\microtime(true) - OZ_OZONE_START_TIME, 3);
	}

	\set_exception_handler('oz_exception_handler');
	\set_error_handler('oz_error_handler');
	\register_shutdown_function('oz_error_shutdown_function');
}
