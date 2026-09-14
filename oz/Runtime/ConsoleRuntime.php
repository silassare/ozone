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

use Override;
use OZONE\Core\Runtime\Interfaces\RuntimeInterface;

/**
 * Class ConsoleRuntime.
 *
 * The `oz` command line: one command per process, and no HTTP client to answer.
 *
 * It is a runtime of its own rather than a flag on {@see CgiRuntime} because "there is nobody to
 * send a response to" used to be read from `PHP_SAPI === 'cli'`, which is also true of RoadRunner,
 * Swoole and ReactPHP workers -- all of them serving real HTTP clients.
 */
final class ConsoleRuntime implements RuntimeInterface
{
	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public static function getName(): string
	{
		return 'cli';
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public static function isSupported(): bool
	{
		return 'cli' === \PHP_SAPI || 'phpdbg' === \PHP_SAPI;
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function isConsole(): bool
	{
		return true;
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function isPersistent(): bool
	{
		return false;
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function flushRequest(): void
	{
		// Nothing to hand over: the terminal reads the output as it is written.
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function terminate(int $code = 0): never
	{
		exit($code);
	}
}
