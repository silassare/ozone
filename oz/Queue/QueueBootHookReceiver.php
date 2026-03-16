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

namespace OZONE\Core\Queue;

use Override;
use OZONE\Core\App\Settings;
use OZONE\Core\Cli\Cron\Workers\CronTaskWorker;
use OZONE\Core\Hooks\Interfaces\BootHookReceiverInterface;
use OZONE\Core\Queue\Stores\DbJobStore;
use OZONE\Core\Queue\Stores\RedisJobStore;
use OZONE\Core\Utils\RedisFactory;

/**
 * Class QueueBootHookReceiver.
 *
 * Boot hook receiver for the job queue system.
 *
 * Registers built-in workers and job stores with {@link JobsManager}:
 *
 * - {@link CronTaskWorker} is always registered so cron jobs can be enqueued
 *   and executed via the queue system.
 * - {@link DbJobStore} is always registered as the default persistent store.
 * - {@link RedisJobStore} is registered when the ext-redis PHP extension is
 *   available AND `OZ_REDIS_ENABLED` is set to `true` in the `oz.redis`
 *   settings group.
 *
 * Note: `boot()` runs before the database and context are initialised; stores
 * are simply registered here and connect lazily on first actual use.
 */
final class QueueBootHookReceiver implements BootHookReceiverInterface
{
	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public static function boot(): void
	{
		// Built-in workers
		JobsManager::registerWorker(CronTaskWorker::class);

		// Always-on DB-backed store
		JobsManager::registerStore(new DbJobStore());

		// Redis store: register only when explicitly enabled
		if (RedisFactory::isAvailable() && Settings::get('oz.redis', 'OZ_REDIS_ENABLED', false)) {
			JobsManager::registerStore(new RedisJobStore());
		}
	}
}
