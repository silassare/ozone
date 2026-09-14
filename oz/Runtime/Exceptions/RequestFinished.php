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

namespace OZONE\Core\Runtime\Exceptions;

use Exception;

/**
 * Class RequestFinished.
 *
 * Unwinds to the worker loop when a request has been answered.
 *
 * Under the classic runtime a request ends with `exit`, so nothing after `Context::respond()` ever
 * runs. That is the published contract of `respond()` and `finish()` being `: never`, and code
 * everywhere is written against it -- application handlers, guards and views that respond and then
 * fall through to a throw or a `return` they expect to be unreachable, as much as the framework's
 * own (`MainBootHookReceiver` throws a `ForbiddenException` straight after responding with the
 * welcome page; `Navigator::redirectRoute()` ends in `respond()`). A persistent runtime cannot
 * `exit`, so it throws this instead: the stack unwinds exactly as far, and the worker loop catches
 * it as the normal end of a request. Skipping the exit without unwinding would run every one of
 * those unreachable lines, in an application OZone cannot see.
 *
 * It is deliberately not a `BaseException`: it is control flow, not an error, and must never be
 * converted into a response.
 */
final class RequestFinished extends Exception
{
	/**
	 * @param int $exit_code the exit code the classic runtime would have used
	 */
	public function __construct(private readonly int $exit_code = 0)
	{
		parent::__construct('Request finished.', $exit_code);
	}

	/**
	 * The exit code a non-persistent runtime would have exited with.
	 */
	public function getExitCode(): int
	{
		return $this->exit_code;
	}
}
