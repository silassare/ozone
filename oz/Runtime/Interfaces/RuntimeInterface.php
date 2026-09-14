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

namespace OZONE\Core\Runtime\Interfaces;

use OZONE\Core\OZone;
use OZONE\Core\Runtime\Exceptions\RequestFinished;

/**
 * Interface RuntimeInterface.
 *
 * How a request ends, which is the one thing a persistent process does differently.
 *
 * Everything else about handling a request is identical under PHP-FPM and under a worker; the
 * difference is that one may `exit` and the other may not. Keeping that difference behind this
 * interface is what lets `Context::finish()` keep its job -- flush, dispatch `FinishHook` -- without
 * knowing which it is running under.
 */
interface RuntimeInterface
{
	/**
	 * A short name, for diagnostics (`oz doctor`, logs).
	 */
	public static function getName(): string;

	/**
	 * Whether this runtime is the one running, as far as it can tell.
	 */
	public static function isSupported(): bool;

	/**
	 * Whether the process has no HTTP client to answer.
	 *
	 * True for the `oz` command line only. It is the runtime's question and not `PHP_SAPI`'s:
	 * RoadRunner, Swoole and ReactPHP workers all run under the `cli` SAPI while serving real
	 * clients, and answering them with a plain-text `exit` would be a bug.
	 */
	public function isConsole(): bool;

	/**
	 * Whether the process serves more than one request.
	 *
	 * Per-request state has to be released between requests when it does
	 * ({@see OZone::endRequest()}).
	 */
	public function isPersistent(): bool;

	/**
	 * Sends what is buffered to the client, and lets the process carry on with the response sent.
	 *
	 * Under PHP-FPM that is `fastcgi_finish_request()`; a worker flushes its buffers instead.
	 */
	public function flushRequest(): void;

	/**
	 * Ends the current request.
	 *
	 * Never returns: a non-persistent runtime exits, a persistent one unwinds to its loop with
	 * {@see RequestFinished}. Nothing written after a call to this
	 * runs under either.
	 *
	 * @param int $code the exit code
	 */
	public function terminate(int $code = 0): never;
}
