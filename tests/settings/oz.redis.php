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

/**
 * Unit-test Redis settings.
 *
 * Enables the Redis store and cache driver when ext-redis is available.
 * Uses the local Redis instance (127.0.0.1:6379) by default.
 */
return [
	'OZ_REDIS_ENABLED' => \extension_loaded('redis'),
];
