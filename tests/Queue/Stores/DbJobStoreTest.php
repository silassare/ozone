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

use OZONE\Core\Db\OZJob;
use OZONE\Core\Db\OZJobsQuery;
use OZONE\Core\Queue\Interfaces\JobContractInterface;
use OZONE\Core\Queue\Interfaces\JobStoreInterface;
use OZONE\Core\Queue\JobsManager;
use OZONE\Core\Queue\JobState;
use OZONE\Core\Queue\Stores\DbJobStore;

/**
 * DB-backed job store contract tests.
 *
 * @internal
 *
 * @covers \OZONE\Core\Queue\Stores\DbJobStore
 */
final class DbJobStoreTest extends AbstractJobStoreTest
{
	/**
	 * The regression: a job carries its own clock, so being old is not being abandoned.
	 *
	 * `job_updated_at` used to be written back as it was read, frozen at the job's creation, so
	 * every job older than the lease was stale the instant a worker took it: reclaimed while it
	 * ran, started again beside itself, for ever.
	 */
	public function testAnOldJobTakenByAWorkerIsNotStale(): void
	{
		$contract = $this->addJob();

		$this->strandJob($contract);

		// A worker takes it and writes it, as runJob() does on its way to RUNNING.
		$taken = $this->store->getOrFail($contract->getRef());
		$taken->setState(JobState::RUNNING);
		$this->store->update($taken);

		self::assertTrue($this->store->isLocked($taken));
		self::assertSame([], $this->store->listStale($this->testQueue));
	}

	protected function makeStore(): JobStoreInterface
	{
		return JobsManager::getStore(DbJobStore::NAME);
	}

	/**
	 * Taken, and last written long enough ago to be past any lease: written straight to the row,
	 * since no store API hands out an old timestamp any more.
	 */
	protected function strandJob(JobContractInterface $job): void
	{
		(new OZJobsQuery())
			->whereRefIs($job->getRef())
			->update([
				OZJob::COL_STATE      => JobState::RUNNING->value,
				OZJob::COL_LOCKED     => true,
				OZJob::COL_UPDATED_AT => '1',
			])
			->execute();
	}
}
