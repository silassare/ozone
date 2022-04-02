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

namespace OZONE\OZ\Hooks\Interfaces;

use OZONE\OZ\Cli\Cli;

/**
 * Interface BootHookReceiverInterface.
 */
interface BootHookReceiverInterface
{
	/**
	 * Called on boot.
	 */
	public static function boot();

	/**
	 * Called on boot in CLI mode.
	 */
	public static function bootCli(Cli $cli);
}
