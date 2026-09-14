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
use OZONE\Core\OZone;
use OZONE\Core\Runtime\Exceptions\RequestFinished;
use OZONE\Core\Runtime\Interfaces\RuntimeInterface;
use OZONE\Core\Utils\Utils;

/**
 * Class WorkerRuntime.
 *
 * One process, many requests: FrankenPHP worker mode, RoadRunner, Swoole, ReactPHP.
 *
 * The process must survive the request, so {@see self::terminate()} unwinds instead of exiting and
 * the loop catches {@see RequestFinished}. Everything that belongs to one request is then released
 * by {@see OZone::endRequest()}, which `OZone::handleRequest()` calls for you.
 *
 * @param string $name what the loop calls itself, for diagnostics
 */
final class WorkerRuntime implements RuntimeInterface
{
	public function __construct(private readonly string $name = 'worker') {}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public static function getName(): string
	{
		return 'worker';
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public static function isSupported(): bool
	{
		return null !== self::detectLoop();
	}

	/**
	 * The worker loop running this process, or null.
	 *
	 * Detection is deliberately narrow: a signal that the process *is* a worker, never one that a
	 * library merely happens to be installed. `ext-swoole` being loaded says nothing about how this
	 * script was started, so Swoole is opt-in through `OZ_RUNTIME=worker`.
	 */
	public static function detectLoop(): ?string
	{
		// FrankenPHP defines the function in classic mode too, where each request runs the script
		// once; only a worker script is started with FRANKENPHP_WORKER set.
		if (\function_exists('frankenphp_handle_request') && self::isFrankenPhpWorker()) {
			return 'frankenphp';
		}

		// RoadRunner sets it for the PHP worker it starts.
		if (false !== \getenv('RR_MODE')) {
			return 'roadrunner';
		}

		$declared = \getenv('OZ_RUNTIME');

		if (\is_string($declared) && 'worker' === \strtolower(\trim($declared))) {
			return 'declared';
		}

		return null;
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function isConsole(): bool
	{
		return false;
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function isPersistent(): bool
	{
		return true;
	}

	/**
	 * The loop name this instance was built for.
	 */
	public function getLoopName(): string
	{
		return $this->name;
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function flushRequest(): void
	{
		// No fastcgi_finish_request() here: the loop owns the connection, and the response is sent
		// when the request handler returns. Flushing the buffers is all that is ours to do.
		if (!\in_array(\PHP_SAPI, ['cli', 'phpdbg'], true)) {
			Utils::closeOutputBuffers(0, true);
		}
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function terminate(int $code = 0): never
	{
		// Unwinds exactly as far as `exit` would, without taking the process with it.
		throw new RequestFinished($code);
	}

	/**
	 * Whether FrankenPHP started this script as a worker (`FRANKENPHP_WORKER`, set in `$_SERVER`).
	 */
	private static function isFrankenPhpWorker(): bool
	{
		$flag = $_SERVER['FRANKENPHP_WORKER'] ?? \getenv('FRANKENPHP_WORKER');

		return \is_string($flag) && '' !== $flag && '0' !== $flag;
	}
}
