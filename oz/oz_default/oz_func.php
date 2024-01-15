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
use OZONE\Core\App\Db;
use OZONE\Core\App\Interfaces\AppInterface;
use OZONE\Core\Exceptions\RuntimeException;
use OZONE\Core\Logger\Logger;
use OZONE\Core\OZone;

if (!\function_exists('oz_logger')) {
	/**
	 * Write to log file.
	 *
	 * @param mixed $value
	 */
	function oz_logger(mixed $value): void
	{
		Logger::log($value);
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
			oz_logger($e);
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

	Logger::registerHandlers();
}
