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

use OZONE\Core\Auth\Providers\EmailVerificationProvider;
use OZONE\Core\Auth\Providers\FileAccessAuthProvider;
use OZONE\Core\Auth\Providers\PhoneVerificationAuthProvider;
use OZONE\Core\Auth\Providers\UserAuthProvider;

return [
	PhoneVerificationAuthProvider::NAME => PhoneVerificationAuthProvider::class,
	EmailVerificationProvider::NAME     => EmailVerificationProvider::class,
	FileAccessAuthProvider::NAME        => FileAccessAuthProvider::class,
	UserAuthProvider::NAME              => UserAuthProvider::class,
];
