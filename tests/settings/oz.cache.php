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

use OZONE\Core\Cache\Drivers\RuntimeCache;

/**
 * Unit-test cache settings.
 *
 * Override the persistent cache provider to use an in-memory RuntimeCache
 * so that no database connection is required during unit testing.
 */
return [
	'OZ_PERSISTENT_CACHE_PROVIDER' => RuntimeCache::class,
];
