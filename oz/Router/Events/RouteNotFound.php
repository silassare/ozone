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

namespace OZONE\OZ\Router\Events;

use OZONE\OZ\Hooks\Hook;

/**
 * Class RouteNotFound.
 *
 * This event is triggered when no route was found for the requested resource.
 */
final class RouteNotFound extends Hook
{
}
