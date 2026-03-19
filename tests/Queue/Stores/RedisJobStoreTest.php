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

/**
 * Redis-backed job store contract tests.
 *
 * Skipped automatically when the Redis store is not registered.
 * To enable: set OZ_REDIS_ENABLED=true in your .env and ensure ext-redis is loaded.
 *
 * @internal
 *
 * @coversNothing
 */
final class RedisJobStoreTest extends AbstractJobStoreTest
{
	protected function setUp(): void
	{
		$stores = JobsManager::getStores();

		if (!isset($stores[RedisJobStore::NAME])) {
			self::markTestSkipped(
				'Redis store is not registered. Set OZ_REDIS_ENABLED=true and ensure ext-redis is available.'
			);
		}

		parent::setUp();
	}

	protected function makeStore(): JobStoreInterface
	{
		return JobsManager::getStore(RedisJobStore::NAME);
	}
}
