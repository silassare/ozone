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

namespace OZONE\Core\Collections\Interfaces;

use Gobl\DBAL\Interfaces\RDBMSInterface;
use OZONE\Core\Hooks\Events\DbReadyHook;

/**
 * Class EntityCollectionsProviderInterface.
 */
interface EntityCollectionsProviderInterface
{
	/**
	 * Called on {@see DbReadyHook}.
	 */
	public static function register(RDBMSInterface $db): void;
}
