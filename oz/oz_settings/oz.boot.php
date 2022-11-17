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

use OZONE\OZ\Auth\Auth;
use OZONE\OZ\Hooks\MainBootHookReceiver;
use OZONE\OZ\Sessions\Session;

return [
	MainBootHookReceiver::class => true,
	Session::class              => true,
	Auth::class                 => true,
];