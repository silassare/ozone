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

use OZONE\OZ\Cache\Drivers\DbCache;
use OZONE\OZ\Cache\Drivers\RuntimeCache;

return [
	// Cache Driver that destroy data after runtime
	'OZ_RUNTIME_CACHE_PROVIDER'    => RuntimeCache::class,
	// Shared cache driver system: Db, Redis, Memcached...
	'OZ_PERSISTENT_CACHE_PROVIDER' => DbCache::class,
];
