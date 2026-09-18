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

namespace OZONE\Core\Exceptions\Utils;

use OZONE\Core\App\Interfaces\AppInterface;
use OZONE\Core\App\Settings;
use OZONE\Core\Cli\Cli;
use OZONE\Core\Exceptions\BaseException;
use OZONE\Core\OZone;
use OZONE\Core\Runtime\Runtime;
use Psr\Log\LogLevel;
use Throwable;

/**
 * Class ErrorUtils.
 */
class ErrorUtils
{
	/**
	 * Keys whose values are treated as secrets in exception data.
	 */
	public const SENSITIVE_KEY_REG = '~(pass(word|wd|phrase)?|secret|token|api_?key|authorization|cookie|credential)~i';

	public const REDACTED = '[redacted]';

	/**
	 * Masks the values of secret-looking keys, recursively, when redaction is enabled
	 * (see {@see self::isRedactionEnabled()}).
	 *
	 * A safety net for exception data that is logged or sent to the client: code
	 * should not put secrets there in the first place.
	 *
	 * @param array $data
	 *
	 * @return array
	 */
	public static function redactSensitiveData(array $data): array
	{
		return self::isRedactionEnabled() ? self::redact($data) : $data;
	}

	/**
	 * Whether sensitive data is redacted: `OZ_REDACT_SENSITIVE_DATA` (`oz.logs`) when it is
	 * a bool, else only in production mode.
	 */
	public static function isRedactionEnabled(): bool
	{
		try {
			$setting = Settings::get('oz.logs', 'OZ_REDACT_SENSITIVE_DATA');

			return \is_bool($setting) ? $setting : OZone::inProductionMode();
		} catch (Throwable) {
			// Settings or env not readable yet (early boot): fail safe.
			return true;
		}
	}

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
	 * @param array $data
	 *
	 * @return array
	 */
	private static function redact(array $data): array
	{
		foreach ($data as $key => $value) {
			if (\is_string($key) && \preg_match(self::SENSITIVE_KEY_REG, $key)) {
				$data[$key] = self::REDACTED;
			} elseif (\is_array($value)) {
				$data[$key] = self::redact($value);
			}
		}

		return $data;
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
	private static function criticalDieMessage(): never
	{
		BaseException::dieWithAnUnhandledErrorOccurred();
	}

	/**
	 * Whether stopping here would have to unwind, which PHP forbids where we are.
	 *
	 * A persistent runtime ends a request by throwing, and throwing out of an exception handler or
	 * a shutdown function is a fatal error -- so the exception handler reports the throwable on
	 * stderr and exits with status 1, and the error handlers only log. Reaching them at all means
	 * the loop failed outside a request: {@see OZone::handleRequest()} answers what it
	 * can and swallows the rest, precisely so a worker never dies of one bad request.
	 */
	private static function mustNotUnwind(): bool
	{
		return Runtime::isPersistent();
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

		if (self::mustNotUnwind()) {
			// Under a worker, `OZone::handleRequest()` answers every request's throwable, so one that
			// reaches this handler escaped the loop itself -- bootstrap, the loop, a bridge -- and the
			// process is ending. Say so where the server looks, and with a failing status: logged
			// only in `.ozone/logs/`, the crash read as a clean stop to whatever supervises the worker.
			// Through php://stderr: the STDERR constant is the CLI SAPI's alone (not FrankenPHP's).
			$stderr = \fopen('php://stderr', 'wb');

			if (false !== $stderr) {
				\fwrite($stderr, \sprintf(
					'%s: %s in %s:%d (the trace is in the OZone log)%s',
					$t::class,
					$t->getMessage(),
					$t->getFile(),
					$t->getLine(),
					\PHP_EOL
				));
			}

			exit(1);
		}

		if (OZone::isCliMode()) {
			// A command in JSON mode answers in JSON on stdout, whatever went wrong.
			if (Cli::inJsonMode()) {
				echo \json_encode([
					'error' => 1,
					'msg'   => $t->getMessage(),
					'data'  => [],
					'utime' => \time(),
				], \JSON_UNESCAPED_SLASHES), \PHP_EOL;
			}

			\fwrite(\STDERR, \PHP_EOL . $t->getMessage() . \PHP_EOL);

			Runtime::current()->terminate(1);
		}

		self::criticalDieMessage();
	}

	private static function errorCodeToLogLevel(int $code): string
	{
		/**
		 * \E_STRICT is deprecated in php 8.4, so we use const value to avoid deprecation warning.
		 */
		$const_e_strict = 2048;

		return match ($code) {
			\E_ERROR, \E_CORE_ERROR, \E_COMPILE_ERROR, \E_USER_ERROR, \E_RECOVERABLE_ERROR => LogLevel::ERROR,
			\E_WARNING, \E_CORE_WARNING, \E_COMPILE_WARNING, \E_USER_WARNING,
			$const_e_strict, \E_DEPRECATED, \E_USER_DEPRECATED => LogLevel::WARNING,
			\E_NOTICE, \E_USER_NOTICE                          => LogLevel::NOTICE,
			default                                            => LogLevel::DEBUG
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

			if (\in_array($code, $fatalist, true) && !self::mustNotUnwind()) {
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
