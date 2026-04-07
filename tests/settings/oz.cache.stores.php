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
 * Unit-test named cache stores.
 *
 * Override all named stores to use the in-memory RuntimeCache
 * so that no database connection is required during unit testing.
 */
return [
	'oz:rate_limit'       => ['driver' => RuntimeCache::class],
	'oz:form:resume'      => ['driver' => RuntimeCache::class],
	'oz:form:sessions'    => ['driver' => RuntimeCache::class],
	'oz:fs:image:filters' => ['driver' => RuntimeCache::class],
];
