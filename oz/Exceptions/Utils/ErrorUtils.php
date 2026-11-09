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
use OZONE\Core\Logger\Logger;
use OZONE\Core\Logger\LogWriter;
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
	public const string SENSITIVE_KEY_REG = '~(pass(word|wd|phrase)?|secret|token|api_?key|authorization|cookie|credential)~i';

	public const string REDACTED = '[redacted]';

	/**
	 * The error codes PHP does not call an error handler for, and that end the process.
	 */
	private const array FATAL_CODES = [\E_ERROR, \E_PARSE, \E_CORE_ERROR, \E_COMPILE_ERROR, \E_USER_ERROR];

	/**
	 * The error codes that mean the run ended where the error was raised.
	 *
	 * What {@see self::shutdownErrorFunction()} reads to label what it found, which is a different
	 * question from whether to interrupt. PHP does hand `E_RECOVERABLE_ERROR` to an error handler
	 * and the handler may carry on, so it is not in {@see self::FATAL_CODES}; with no handler
	 * installed PHP ends the run on it instead, and that is the only way it reaches the shutdown
	 * function -- as a fatal, not as a stale last error.
	 */
	private const array RUN_ENDING_CODES = [...self::FATAL_CODES, \E_RECOVERABLE_ERROR];

	/**
	 * Whether the autoloader is what registered the handlers.
	 */
	private static bool $registered_early = false;

	/**
	 * The last diagnostic the handler left to PHP because its caller had silenced it.
	 *
	 * That one does land in `error_get_last()`, deliberately, which is where
	 * {@see self::shutdownErrorFunction()} looks: it must not report what the handler kept out of
	 * the log, and the type alone does not say which it is -- a silenced warning and a warning
	 * raised before the handlers went up are the same code.
	 */
	private static ?string $silenced = null;

	/**
	 * Masks the values of secret-looking keys, recursively, when redaction is enabled
	 * (see {@see self::isRedactionEnabled()}).
	 *
	 * A safety net for exception data that is logged or sent to the client: code
	 * should not put secrets there in the first place.
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
	 * Whether the handlers were registered by the autoloader, before a project's boot file ran.
	 *
	 * False means they went up at the top of {@see OZone::bootstrap()} or {@see Cli::run()} instead,
	 * so an error raised while the project booted reached PHP's own error log and nothing of ours.
	 * `oz doctor check` reports it: the way back is for the project's boot file to define
	 * `OZ_PROJECT_DIR` before it requires `vendor/autoload.php`, which is what the generated one does.
	 */
	public static function registeredByTheAutoloader(): bool
	{
		return self::$registered_early;
	}

	/**
	 * Register error handlers and shutdown function.
	 *
	 * @param bool $early true when the autoloader is registering them, before a project's boot file
	 *
	 * @psalm-suppress InvalidArgument
	 */
	public static function registerHandlers(bool $early = false): void
	{
		static $registered = false;

		if ($registered) {
			return;
		}

		$registered             = true;
		self::$registered_early = $early;

		self::loadTheLogger();

		\set_exception_handler(self::exceptionHandler(...));
		\set_error_handler(static function (int $code, string $message, string $file, int $line) {
			// Silenced by its caller with `@`, or below what `error_reporting()` asks for: not ours
			// to log. `false`, not `null`, so PHP's own handler still records it as the last error:
			// `@` and then `error_get_last()` is how a caller reads why a call it expected to be
			// able to fail failed -- php-utils' `@mkdir()`, the S3 stream wrapper's `@fopen()` --
			// and a framework that swallowed the diagnostic would take that away from every project
			// too. Its own handler honours `error_reporting()` for displaying and logging, so
			// nothing of it is printed or written either.
			if (0 === (\error_reporting() & $code)) {
				self::$silenced = self::signature($code, $message, $file, $line);

				return false;
			}

			try {
				self::errorHandler($code, $message, $file, $line, true);
			} catch (Throwable $t) {
				// Reporting the diagnostic failed. PHP does not re-enter an error handler, so
				// nothing of ours would see this one -- and letting it out throws it from the line
				// that raised the diagnostic, where any `catch (Throwable)` reads it as that line's
				// own failure and the diagnostic is gone with it. That is how a `project build`
				// reported a truncated release as a success: with no descriptor left, the handler
				// autoloaded the logger, got a "class not found", and the settings compile caught it
				// as its own. Written where a diagnostic of a broken process can still go.
				self::sayOnStderr(\sprintf(
					'OZone could not report an error (%s: %s): %s in %s:%d',
					$t::class,
					$t->getMessage(),
					$message,
					$file,
					$line
				));
			}

			return null;
		});
		\register_shutdown_function(self::shutdownErrorFunction(...));
	}

	/**
	 * Loads what writing to the log takes, before an error can need it.
	 *
	 * `oz_logger()` resolves its classes on first use, and first use is the moment an error is
	 * raised: the process may then be out of exactly what loading a file takes -- a descriptor,
	 * memory -- and that failure happens inside the error handler, which PHP does not re-enter, so
	 * nothing of ours is left to report it. The reads cost nothing here, where the process is still
	 * whole.
	 *
	 * The settings the writer reads (`oz.logs`) are deliberately not warmed with them: a project's
	 * own settings sources are not registered yet when the autoloader registers the handlers, and
	 * reading the group now would hold OZone's defaults for the rest of the process.
	 */
	private static function loadTheLogger(): void
	{
		\class_exists(Logger::class);
		\class_exists(LogWriter::class);
		\class_exists(LogLevel::class);
		\class_exists(BaseException::class);
	}

	/**
	 * Writes a line where a diagnostic can still go once reporting one has failed.
	 *
	 * Through `php://stderr` and not the `STDERR` constant, which is the CLI SAPI's alone and not
	 * FrankenPHP's. It loads nothing and reads no settings, which is the whole point: the caller is
	 * here because something ordinary could not be done.
	 */
	private static function sayOnStderr(string $line): void
	{
		$stderr = \fopen('php://stderr', 'wb');

		if (false !== $stderr) {
			\fwrite($stderr, $line . \PHP_EOL);
			\fclose($stderr);
		}
	}

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
	 * Reporting the throwable is tried and never allowed to replace it: throwing out of an exception
	 * handler is a fatal error, so a logger that cannot write would end the process with its own
	 * message in place of the one the process was already ending for.
	 */
	private static function exceptionHandler(Throwable $t): void
	{
		try {
			oz_logger($t);

			self::optionalApp()
				?->onUnhandledThrowable($t);
		} catch (Throwable $failure) {
			self::sayOnStderr(\sprintf(
				'OZone could not report an unhandled %s (%s: %s): %s in %s:%d',
				$t::class,
				$failure::class,
				$failure->getMessage(),
				$t->getMessage(),
				$t->getFile(),
				$t->getLine()
			));
		}

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
		return match ($code) {
			\E_ERROR, \E_CORE_ERROR, \E_COMPILE_ERROR, \E_USER_ERROR, \E_RECOVERABLE_ERROR => LogLevel::ERROR,
			\E_WARNING, \E_CORE_WARNING, \E_COMPILE_WARNING, \E_USER_WARNING,
			\E_DEPRECATED, \E_USER_DEPRECATED => LogLevel::WARNING,
			\E_NOTICE, \E_USER_NOTICE         => LogLevel::NOTICE,
			default                           => LogLevel::DEBUG
		};
	}

	/**
	 * What tells one recorded diagnostic from another: a silenced one is recognised at shutdown by
	 * the same four values PHP kept.
	 */
	private static function signature(int $code, string $message, string $file, int $line): string
	{
		return $code . '|' . $file . '|' . $line . '|' . $message;
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

		if ($die_on_fatal && \in_array($code, self::FATAL_CODES, true) && !self::mustNotUnwind()) {
			self::criticalDieMessage();
		}
	}

	/**
	 * Try to log error after shutdown.
	 *
	 * Only an error the error handler never saw can reach here. The handler returns `null`, which
	 * tells PHP the error is handled and keeps it out of `error_get_last()`, so what is left is
	 * either a fatal -- the handler is not called for one -- or an error raised while no handler of
	 * ours was installed: before {@see self::registerHandlers()} ran, or inside a block that swapped
	 * in its own. Which of the two it is goes in the line, because a non-fatal here did **not** happen
	 * at shutdown, and reading it as if it did sends the next reader to the wrong end of the run.
	 *
	 * PHP keeps only the last diagnostic, and some come in pairs: a failed `include` reports
	 * `Failed to open stream: <reason>` and then `Failed opening '...' for inclusion`, and only the
	 * second is here. The first, the one that says why, went to PHP's own error log, since ours was
	 * not handling errors yet.
	 */
	private static function shutdownErrorFunction(): void
	{
		$error = \error_get_last();

		$silenced = null !== $error && self::$silenced === self::signature(
			$error['type'],
			$error['message'],
			$error['file'],
			$error['line']
		);

		if ($silenced) {
			// The last thing PHP recorded is the diagnostic its caller silenced, which the handler
			// left to PHP on purpose. Nothing went wrong that the caller did not already deal with,
			// and reporting it here would put back exactly what was kept out of the log.
			$error = null;
		}

		// Throwing out of a shutdown function is a fatal error too, and this one runs after the work
		// is done: a logger that cannot write must not turn a finished run into a crash.
		try {
			if (null !== $error) {
				$code = $error['type'];
				oz_logger()->log(
					self::errorCodeToLogLevel($code),
					'::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::'
						. \PHP_EOL
						. (\in_array($code, self::RUN_ENDING_CODES, true)
							? 'OZone shutdown error'
							: 'OZone error raised while no error handler of ours was installed:'
								. ' before they were registered, or inside a block that swapped in its own.'
								. ' It did not happen at shutdown, PHP only kept it as the last error.'
								. ' Where a diagnostic comes in a pair only the second is kept, so the reason'
								. ' is in PHP\'s own error log, not here.')
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
		} catch (Throwable $failure) {
			self::sayOnStderr(\sprintf(
				'OZone could not write its shutdown log (%s: %s)%s',
				$failure::class,
				$failure->getMessage(),
				null === $error ? '' : \sprintf(
					': the last error was %s in %s:%d',
					$error['message'],
					$error['file'],
					$error['line']
				)
			));
		}
	}
}
