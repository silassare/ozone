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

use Gobl\DBAL\Interfaces\RDBMSInterface;
use OZONE\Core\App\Context;
use OZONE\Core\App\Db;
use OZONE\Core\App\Interfaces\AppInterface;
use OZONE\Core\Exceptions\RuntimeException;
use OZONE\Core\Logger\Logger;
use OZONE\Core\OZone;
use Psr\Log\LogLevel;

if (!\function_exists('oz_logger')) {
	/**
	 * Get the logger instance or log a value.
	 *
	 * @param mixed $value
	 *
	 * @return Logger
	 */
	function oz_logger(mixed $value = null): Logger
	{
		static $logger = null;

		if (null === $logger) {
			$logger = new Logger();
		}

		if (null !== $value) {
			$level = $value instanceof Throwable ? LogLevel::ERROR : LogLevel::DEBUG;
			$logger->log($level, Logger::describe($value));
		}

		return $logger;
	}

	/**
	 * Write a stack trace to log file.
	 *
	 * This is useful for debugging and prevent possible throwable silencing
	 * that may occur in upper catch blocks and lead to unexpected behavior.
	 *
	 * This is one of the most common mistakes:
	 *
	 * ```
	 * try {
	 *    functionWithImportantLogicThatMayThrow();
	 * } catch (Throwable) {
	 *   // the error is silenced here
	 * }
	 *
	 * // to solve this problem, you can use oz_trace like this:
	 * function functionWithImportantLogicThatMayThrow(): void {
	 *   try {
	 *    // some code
	 *  } catch (Throwable $t) {
	 *   oz_trace('functionWithImportantLogicThatMayThrow failed', ['data' => 'some data'], $t);
	 *   throw $t; // rethrow the error that can be silenced in upper catch blocks
	 * }
	 *```
	 *
	 * @param string         $message  The error message
	 * @param null|array     $data     The error data
	 * @param null|Throwable $previous The previous exception
	 */
	function oz_trace(string $message, ?array $data = [], ?Throwable $previous = null): void
	{
		try {
			throw new RuntimeException($message, $data, $previous);
		} catch (RuntimeException $e) {
			oz_logger()->debug($e);
		}
	}

	/**
	 * Alias for {@see OZone::app()}.
	 *
	 * @return AppInterface
	 */
	function app(): AppInterface
	{
		return OZone::app();
	}

	/**
	 * Get the value of an environment variable.
	 *
	 * @param string                     $key
	 * @param null|bool|float|int|string $default
	 *
	 * @return null|bool|float|int|string
	 */
	function env(string $key, mixed $default = null): null|bool|float|int|string
	{
		return app()->getEnv()->get($key, $default);
	}

	/**
	 * Alias for {@see Db::get()}.
	 *
	 * @return RDBMSInterface
	 */
	function db(): RDBMSInterface
	{
		return Db::get();
	}

	/**
	 * Alias for {@see Context::current()}.
	 *
	 * @return Context
	 */
	function context(): Context
	{
		return Context::current();
	}
}
