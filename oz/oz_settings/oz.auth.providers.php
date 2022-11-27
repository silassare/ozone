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

use OZONE\OZ\Auth\Providers\AuthEmail;
use OZONE\OZ\Auth\Providers\AuthFile;
use OZONE\OZ\Auth\Providers\AuthPhone;

return [
	AuthPhone::NAME => AuthPhone::class,
	AuthEmail::NAME => AuthEmail::class,
	AuthFile::NAME => AuthFile::class,
];
