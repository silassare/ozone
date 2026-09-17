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

namespace OZONE\Core\Testing\Traits;

use OZONE\Core\Utils\RedisFactory;
use Throwable;

/**
 * Trait RequiresRedisTrait.
 *
 * For tests of the `redis` group: they are skipped without a reachable Redis server, and
 * fail instead when `OZ_TEST_REDIS_REQUIRED` is set (`make test-services`), so a CI job that
 * requires Redis cannot silently pass without it.
 */
trait RequiresRedisTrait
{
	/**
	 * Skips (or fails) the test unless ext-redis is loaded and the server answers.
	 */
	protected static function requireRedis(): void
	{
		if (!RedisFactory::isAvailable()) {
			self::redisUnavailable('ext-redis is not loaded.');
		}

		try {
			RedisFactory::get()->ping();
		} catch (Throwable $t) {
			self::redisUnavailable('Redis is unreachable: ' . $t->getMessage());
		}
	}

	/**
	 * Skips the test, or fails it when Redis is required.
	 */
	protected static function redisUnavailable(string $reason): never
	{
		if (\getenv('OZ_TEST_REDIS_REQUIRED')) {
			self::fail($reason . ' (OZ_TEST_REDIS_REQUIRED is set)');
		}

		self::markTestSkipped($reason);
	}
}
