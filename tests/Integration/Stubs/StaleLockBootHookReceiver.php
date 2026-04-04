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

namespace __PLH_NAMESPACE__;

use __PLH_NAMESPACE__\Workers\StaleLockTestWorker;
use OZONE\Core\Db\OZJob;
use OZONE\Core\Db\OZJobsQuery;
use OZONE\Core\Hooks\Events\InitHook;
use OZONE\Core\Hooks\Interfaces\BootHookReceiverInterface;
use OZONE\Core\Queue\JobsManager;
use OZONE\Core\Queue\JobState;
use OZONE\Core\Queue\Queue;

/**
 * Registers StaleLockTestWorker and simulates a stale locked job.
 *
 * Flow:
 *   1. Registers the worker during boot.
 *   2. Via InitHook: dispatches a job to the default queue.
 *   3. Immediately after dispatch, simulates a crashed subprocess by directly
 *      updating the job to state=RUNNING, locked=true, updated_at=1 (Unix epoch).
 *      The value 1 is guaranteed to be older than DbJobStore::LOCK_TTL (7200 s).
 *   4. When oz jobs run is called next, the iterator recovery pass detects the
 *      stale lock, resets the job to PENDING, and the worker executes normally.
 */
final class StaleLockBootHookReceiver implements BootHookReceiverInterface
{
	public static function boot(): void
	{
		JobsManager::registerWorker(StaleLockTestWorker::class);

		InitHook::listen(static function () {
			$flagFile = '__PLH_FLAG_FILE__';
			if (\is_file($flagFile)) {
				\unlink($flagFile);
			}

			// Dispatch the job (state = PENDING).
			$contract = Queue::get(Queue::DEFAULT)
				->push(new StaleLockTestWorker($flagFile))
				->dispatch();

			// Simulate a crashed subprocess: set state=RUNNING, locked=true,
			// updated_at=1 so the stale-lock check always fires.
			// Using a direct bulk UPDATE bypasses entity-level auto-column logic.
			(new OZJobsQuery())
				->whereRefIs($contract->getRef())
				->update([
					OZJob::COL_STATE      => JobState::RUNNING->value,
					OZJob::COL_LOCKED     => true,
					OZJob::COL_UPDATED_AT => '1',
				])
				->execute();
		});
	}
}
