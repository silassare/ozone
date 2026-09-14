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

namespace OZONE\Tests\Queue\Stores;

use OZONE\Core\Queue\Interfaces\JobStoreInterface;
use OZONE\Core\Queue\JobsManager;
use OZONE\Core\Queue\Stores\RedisJobStore;
use OZONE\Tests\Support\RequiresRedisTrait;

/**
 * Redis-backed job store contract tests.
 *
 * Needs ext-redis, a Redis server and `OZ_REDIS_ENABLED=true`; run with `make test-redis`.
 *
 * @internal
 *
 * @group redis
 *
 * @covers \OZONE\Core\Queue\Stores\RedisJobStore
 */
final class RedisJobStoreTest extends AbstractJobStoreTest
{
	use RequiresRedisTrait;

	protected function setUp(): void
	{
		self::requireRedis();

		if (!isset(JobsManager::getStores()[RedisJobStore::NAME])) {
			self::redisUnavailable('Redis store is not registered. Set OZ_REDIS_ENABLED=true.');
		}

		parent::setUp();
	}

	protected function makeStore(): JobStoreInterface
	{
		return JobsManager::getStore(RedisJobStore::NAME);
	}
}
