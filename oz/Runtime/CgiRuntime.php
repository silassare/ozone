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
use OZONE\Core\Utils\Utils;

/**
 * Class CgiRuntime.
 *
 * One process, one request: PHP-FPM, mod_php, the built-in server. The default, and what OZone
 * has always done. The `oz` command line is {@see ConsoleRuntime} instead.
 */
final class CgiRuntime implements RuntimeInterface
{
	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public static function getName(): string
	{
		return 'cgi';
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public static function isSupported(): bool
	{
		// The fallback: it is always able to run, which is why detection tries it last.
		return true;
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
		return false;
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function flushRequest(): void
	{
		// Lets FPM answer the client while this process carries on with FinishHook (garbage
		// collection, and whatever the project registered).
		if (\function_exists('fastcgi_finish_request')) {
			fastcgi_finish_request();
		} elseif (!\in_array(\PHP_SAPI, ['cli', 'phpdbg'], true)) {
			Utils::closeOutputBuffers(0, true);
		}
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
