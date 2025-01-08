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

use OZONE\Core\FS\Drivers\PrivateLocalStorage;
use OZONE\Core\FS\Drivers\PublicLocalStorage;
use OZONE\Core\FS\FS;

return [
	FS::DEFAULT_STORAGE => PublicLocalStorage::class,
	FS::PUBLIC_STORAGE  => PublicLocalStorage::class,
	FS::PRIVATE_STORAGE => PrivateLocalStorage::class,
];
