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

namespace OZONE\OZ\Router\Interfaces;

use OZONE\OZ\Forms\FormData;
use PHPUtils\Interfaces\ArrayCapableInterface;

/**
 * Interface RouteGuardInterface.
 */
interface RouteGuardInterface extends ArrayCapableInterface
{
	/**
	 * Check if user has access and returns authorization form data if any was provided.
	 */
	public function assertHasAccess(): ?FormData;
}