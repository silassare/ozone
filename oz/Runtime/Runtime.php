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

namespace OZONE\Core\Runtime;

use OZONE\Core\OZone;
use OZONE\Core\Runtime\Interfaces\ResponseSinkInterface;
use OZONE\Core\Runtime\Interfaces\RuntimeInterface;

/**
 * Class Runtime.
 *
 * Which runtime is serving this process.
 *
 * Detected once, and overridable: a worker loop OZone cannot recognise -- ReactPHP, an in-house
 * one -- declares itself with `Runtime::set(new WorkerRuntime('name'))` before serving, or with
 * `OZ_RUNTIME=worker` in the environment. The bridges of `Runtime\Bridges\` (FrankenPHP,
 * RoadRunner, Swoole) declare it for you.
 *
 * The runtime decides how a request *ends*; where its response *goes* is the request's, given to
 * {@see OZone::handleRequest()} as a response sink by a server that does not read PHP's
 * output ({@see ResponseSinkInterface}).
 */
final class Runtime
{
	private static ?RuntimeInterface $current = null;

	/**
	 * The runtime of this process.
	 */
	public static function current(): RuntimeInterface
	{
		return self::$current ??= self::detect();
	}

	/**
	 * Declares the runtime, before serving.
	 *
	 * @param RuntimeInterface $runtime
	 */
	public static function set(RuntimeInterface $runtime): void
	{
		self::$current = $runtime;
	}

	/**
	 * Forgets the detected runtime, so the next call detects again.
	 *
	 * @internal for tests
	 */
	public static function reset(): void
	{
		self::$current = null;
	}

	/**
	 * Whether this process serves more than one request.
	 */
	public static function isPersistent(): bool
	{
		return self::current()->isPersistent();
	}

	/**
	 * Whether this process has no HTTP client to answer.
	 */
	public static function isConsole(): bool
	{
		return self::current()->isConsole();
	}

	/**
	 * Works out what is running this process.
	 *
	 * A worker only when something says so -- FrankenPHP's worker-mode function, RoadRunner's
	 * environment, or an explicit `OZ_RUNTIME=worker`. Guessing wrong in the other direction would
	 * be worse than useless: a process that thinks it is persistent when it is not never exits, and
	 * one that thinks it is not kills the worker on its first response.
	 *
	 * The worker check comes first on purpose. RoadRunner, Swoole and ReactPHP run under the `cli`
	 * SAPI, so asking `PHP_SAPI` first would call every one of them a command line -- and a
	 * command line answers a 404 by printing it and exiting the process.
	 */
	public static function detect(): RuntimeInterface
	{
		$loop = WorkerRuntime::detectLoop();

		if (null !== $loop) {
			return new WorkerRuntime($loop);
		}

		if (ConsoleRuntime::isSupported()) {
			return new ConsoleRuntime();
		}

		return new CgiRuntime();
	}
}
