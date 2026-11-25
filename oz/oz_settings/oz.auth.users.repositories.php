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

use OZONE\Core\Auth\AuthUsersRepository;
use OZONE\Core\Auth\Interfaces\AuthUserInterface;

/**
 * Map auth user type name {@see AuthUserInterface::getAuthUserType()}
 * type name to the repository class.
 */
return [
	AuthUsersRepository::DEFAULT_AUTH_USER_TYPE  => AuthUsersRepository::class,
];
