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

/**
 * Interface BootHookReceiverInterface.
 *
 * Note that on boot, the context and the database are not yet initialized.
 * Just use this interface to register your own events handlers.
 */
interface BootHookReceiverInterface
{
	/**
	 * Called on boot.
	 */
	public static function boot();
}
