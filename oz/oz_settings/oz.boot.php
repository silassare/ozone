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

use OZONE\Core\Auth\Auth;
use OZONE\Core\Auth\Auth2FA;
use OZONE\Core\Collections\EntityCollections;
use OZONE\Core\FS\TempFS;
use OZONE\Core\Hooks\MainBootHookReceiver;
use OZONE\Core\Sessions\Session;

return [
	MainBootHookReceiver::class => true,
	EntityCollections::class    => true,
	Session::class              => true,
	Auth::class                 => true,
	Auth2FA::class              => true,
	TempFS::class               => true,
];
