<?php

/**
 * Copyright (c) 2017-present, Emile Silas Sare
 *
 * This file is part of OZone package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace OZONE\Core\Exceptions\Utils;

use OZONE\Core\App\Interfaces\AppInterface;
use OZONE\Core\App\Settings;
use OZONE\Core\Exceptions\BaseException;
use OZONE\Core\OZone;
use Psr\Log\LogLevel;
use Throwable;

/**
 * Class ErrorUtils.
 */
class ErrorUtils
{
	/**
	 * Register error handlers and shutdown function.
	 *
	 * @psalm-suppress InvalidArgument
	 */
	public static function registerHandlers(): void
	{
		static $registered = false;

		if ($registered) {
			return;
		}

		$registered = true;
		\set_exception_handler(self::exceptionHandler(...));
		\set_error_handler(static function (int $code, string $message, string $file, int $line) {
			self::errorHandler($code, $message, $file, $line, true);

			return null;
		});
		\register_shutdown_function(self::shutdownErrorFunction(...));
	}

	/**
	 * @return string
	 */
	private static function executionTime(): string
	{
		return \number_format(\microtime(true) - OZ_OZONE_START_TIME, 3);
	}

	/**
	 * Called when we should shutdown and only admin
	 * should know what is going wrong.
	 */
	private static function criticalDieMessage(): void
	{
		BaseException::dieWithAnUnhandledErrorOccurred();
	}

	/**
	 * Get the running app or null.
	 *
	 * @return null|AppInterface
	 */
	private static function optionalApp(): ?AppInterface
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
	private static function exceptionHandler(Throwable $t): void
	{
		oz_logger($t);

		self::optionalApp()
			?->onUnhandledThrowable($t);

		if (OZone::isCliMode()) {
			exit(\PHP_EOL . $t->getMessage() . \PHP_EOL);
		}

		self::criticalDieMessage();
	}

	private static function errorCodeToLogLevel(int $code): string
	{
		return match ($code) {
			\E_ERROR, \E_CORE_ERROR, \E_COMPILE_ERROR, \E_USER_ERROR, \E_RECOVERABLE_ERROR => LogLevel::ERROR,
			\E_WARNING, \E_CORE_WARNING, \E_COMPILE_WARNING, \E_USER_WARNING, \E_STRICT, \E_DEPRECATED, \E_USER_DEPRECATED => LogLevel::WARNING,
			\E_NOTICE, \E_USER_NOTICE => LogLevel::NOTICE,
			default => LogLevel::DEBUG
		};
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
	private static function errorHandler(
		int $code,
		string $message,
		string $file,
		int $line,
		bool $die_on_fatal = false
	): void {
		oz_logger()->log(self::errorCodeToLogLevel($code), "\n\tFile    : {$file}"
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
	private static function shutdownErrorFunction(): void
	{
		$error = \error_get_last();

		if (null !== $error) {
			$code = $error['type'];
			oz_logger()->log(
				self::errorCodeToLogLevel($code),
				'::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::'
				. \PHP_EOL
				. 'OZone shutdown error'
				. \PHP_EOL . '::::::::::::::::::::::::'
			);
			self::errorHandler($code, $error['message'], $error['file'], $error['line'], true);
		}

		if (Settings::get('oz.logs', 'OZ_LOG_EXECUTION_TIME_ENABLED')) {
			oz_logger()->info('::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::'
				. \PHP_EOL
				. 'OZone execution time'
				. \PHP_EOL . '::::::::::::::::::::::::'
				. \PHP_EOL . self::executionTime() . 's');
		}
	}
}
