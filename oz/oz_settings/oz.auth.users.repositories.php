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

use OZONE\Core\Users\UsersRepository;

/**
 * Map auth user type name {@see \OZONE\Core\Auth\Interfaces\AuthUserInterface::getAuthUserTypeName()}
 * type name to the repository class.
 */
return [
	UsersRepository::OZ_USERS  => UsersRepository::class,
];
