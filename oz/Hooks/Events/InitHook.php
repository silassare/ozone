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

namespace OZONE\OZ\Hooks\Events;

use OZONE\OZ\Hooks\Hook;

/**
 * Class InitHook.
 *
 * This event is triggered when ozone is fully initialized.
 * Just before processing the request.
 *
 * !This event is not triggered for sub-request.
 */
final class InitHook extends Hook
{
}
