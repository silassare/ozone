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

use OZONE\Core\Utils\JSONResult;

/**
 * Interface WorkerInterface.
 *
 * Contract for a queue execution unit.
 *
 * Implementations must be round-trippable via a string name and payload array so the job
 * runner can reconstruct them from a persisted {@link JobContractInterface} record:
 *
 * - {@link getName()} - unique string identifier used to look up this class in the worker
 *   registry when a job is dequeued.
 * - {@link getPayload()} / {@link fromPayload()} - symmetrical: whatever `getPayload()`
 *   returns, `fromPayload()` must reconstruct an equivalent worker instance from it.
 * - {@link work()} - performs the actual job; any output should be accessible via
 *   {@link getResult()} afterwards so {@link JobsManager} can persist it on the job record.
 * - {@link isAsync()} - hint for the job runner; when true, the runner spawns a
 *   dedicated background subprocess for this job (via the `--job <ref> --force`
 *   CLI dispatch) so the queue worker process stays free to pick up the next job
 *   immediately. The lock is held by the parent process and transferred to the
 *   subprocess, which releases it in {@link JobsManager::finish()}.
 */
interface WorkerInterface
{
	/**
	 * Gets the worker name.
	 *
	 * @return string
	 */
	public static function getName(): string;

	/**
	 * Instructs the worker to do the job.
	 *
	 * @param JobContractInterface $job_contract
	 *
	 * @return $this
	 */
	public function work(JobContractInterface $job_contract): self;

	/**
	 * Is the worker working asynchronously?
	 *
	 * @return bool
	 */
	public function isAsync(): bool;

	/**
	 * Gets the result.
	 *
	 * @return JSONResult
	 */
	public function getResult(): JSONResult;

	/**
	 * Gets the worker instance from payload.
	 *
	 * @param array $payload
	 *
	 * @return static
	 */
	public static function fromPayload(array $payload): self;

	/**
	 * Returns the payload.
	 *
	 * The payload is the data that will be passed to the worker later.
	 *
	 * @return array
	 */
	public function getPayload(): array;
}
