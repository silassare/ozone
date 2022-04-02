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

namespace OZONE\OZ\App;

use OZONE\OZ\App\Interfaces\AppInterface;
use Throwable;

/**
 * Class AppBase.
 */
class AppBase implements AppInterface
{
	/**
	 * AppBase constructor.
	 */
	public function __construct()
	{
	}

	/**
	 * {@inheritDoc}
	 */
	public function boot(): void
	{
	}

	/**
	 * {@inheritDoc}
	 */
	public function onUnhandledThrowable(Throwable $t): void
	{
	}

	/**
	 * {@inheritDoc}
	 */
	public function onUnhandledError(int $code, string $message, string $file, int $line): void
	{
	}
}
