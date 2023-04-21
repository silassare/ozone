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

namespace OZONE\OZ\App\Interfaces;

use Throwable;

/**
 * Interface AppInterface.
 */
interface AppInterface
{
	/**
	 * AppInterface constructor.
	 */
	public function __construct();

	/**
	 * Called when ozone is booting.
	 */
	public function boot(): void;

	/**
	 * Unhandled throwable hook. Is called when an unhandled throwable occurs.
	 *
	 * @param Throwable $t the throwable (exception/error)
	 */
	public function onUnhandledThrowable(Throwable $t);

	/**
	 * Unhandled error hook. Is called when an unhandled error occurs.
	 *
	 * @param int    $code    the error code
	 * @param string $message the error message
	 * @param string $file    the file where it occurs
	 * @param int    $line    the file line where it occurs
	 */
	public function onUnhandledError(int $code, string $message, string $file, int $line);
}
