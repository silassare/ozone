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

use OZONE\Core\Auth\Providers\AuthUserAuthorizationProvider;
use OZONE\Core\Auth\Providers\EmailOwnershipVerificationProvider;
use OZONE\Core\Auth\Providers\FileAccessAuthorizationProvider;
use OZONE\Core\Auth\Providers\PhoneOwnershipVerificationProvider;

return [
	PhoneOwnershipVerificationProvider::NAME                      => PhoneOwnershipVerificationProvider::class,
	EmailOwnershipVerificationProvider::NAME                      => EmailOwnershipVerificationProvider::class,
	FileAccessAuthorizationProvider::NAME                         => FileAccessAuthorizationProvider::class,
	AuthUserAuthorizationProvider::NAME                           => AuthUserAuthorizationProvider::class,
];
