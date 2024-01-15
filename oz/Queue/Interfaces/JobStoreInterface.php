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

namespace OZONE\Core\Queue\Interfaces;

use Iterator;
use OZONE\Core\Queue\JobState;

/**
 * Class JobStoreInterface.
 */
interface JobStoreInterface
{
	/**
	 * Gets the store name.
	 *
	 * @return string
	 */
	public function getName(): string;

	/**
	 * Gets a job by ref.
	 *
	 * @param string $ref
	 *
	 * @return null|JobContractInterface
	 */
	public function get(string $ref): ?JobContractInterface;

	/**
	 * Gets a job by ref or fail.
	 *
	 * @param string $ref
	 *
	 * @return JobContractInterface
	 */
	public function getOrFail(string $ref): JobContractInterface;

	/**
	 * Add a job.
	 *
	 * @param JobInterface $job
	 *
	 * @return JobContractInterface
	 */
	public function add(JobInterface $job): JobContractInterface;

	/**
	 * Update a job.
	 *
	 * @param JobContractInterface $job_contract
	 *
	 * @return JobStoreInterface
	 */
	public function update(JobContractInterface $job_contract): self;

	/**
	 * Delete a job.
	 *
	 * @param JobContractInterface $job_contract
	 *
	 * @return $this
	 */
	public function delete(JobContractInterface $job_contract): self;

	/**
	 * Returns iterator for jobs in a queue.
	 *
	 * @param string                          $queue_name
	 * @param null|string                     $worker_name
	 * @param null|\OZONE\Core\Queue\JobState $state
	 * @param null|int                        $priority
	 *
	 * @return Iterator<JobContractInterface>
	 */
	public function iterator(
		string $queue_name,
		?string $worker_name = null,
		?JobState $state = null,
		?int $priority = null
	): Iterator;

	/**
	 * List jobs in a queue.
	 *
	 * @param null|string                     $queue_name
	 * @param null|string                     $worker_name
	 * @param null|\OZONE\Core\Queue\JobState $state
	 * @param null|int                        $priority
	 * @param int                             $page
	 * @param int                             $max
	 *
	 * @return JobContractInterface[]
	 */
	public function list(
		?string $queue_name = null,
		?string $worker_name = null,
		?JobState $state = null,
		?int $priority = null,
		int $page = 1,
		int $max = 100
	): array;

	/**
	 * Lock a job.
	 *
	 * @param JobContractInterface $job_contract
	 *
	 * @return bool
	 */
	public function lock(JobContractInterface $job_contract): bool;

	/**
	 * Unlock a job.
	 *
	 * @param JobContractInterface $job_contract
	 *
	 * @return bool
	 */
	public function unlock(JobContractInterface $job_contract): bool;
}
