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

use OZONE\Core\Queue\JobState;
use OZONE\Core\Queue\Queue;
use OZONE\Core\Utils\JSONResult;

/**
 * Interface JobInterface.
 */
interface JobInterface
{
	/**
	 * Gets job ref.
	 *
	 * @return string
	 */
	public function getRef(): string;

	/**
	 * Gets job name.
	 *
	 * @return string
	 */
	public function getName(): string;

	/**
	 * Sets job name.
	 *
	 * @param string $name
	 */
	public function setName(string $name): static;

	/**
	 * Gets payload.
	 *
	 * @return array
	 */
	public function getPayload(): array;

	/**
	 * Gets the job worker name.
	 *
	 * @return string
	 */
	public function getWorker(): string;

	/**
	 * Gets the job queue name.
	 *
	 * @return string
	 */
	public function getQueue(): string;

	/**
	 * Sets the job queue name.
	 *
	 * @param string $queue
	 */
	public function setQueue(string $queue): static;

	/**
	 * Gets the job priority.
	 *
	 * @return int
	 */
	public function getPriority(): int;

	/**
	 * Sets the job priority.
	 *
	 * @param int $priority
	 */
	public function setPriority(int $priority): static;

	/**
	 * Sets the job created at time.
	 *
	 * @param int $created_at
	 */
	public function setCreatedAt(int $created_at): static;

	/**
	 * Gets the job created at time.
	 *
	 * @return int
	 */
	public function getCreatedAt(): int;

	/**
	 * Sets the job updated at time.
	 *
	 * @param int $updated_at
	 */
	public function setUpdatedAt(int $updated_at): static;

	/**
	 * Gets the job updated at time.
	 *
	 * @return int
	 */
	public function getUpdatedAt(): int;

	/**
	 * Gets the job state.
	 *
	 * @return JobState
	 */
	public function getState(): JobState;

	/**
	 * Sets the job state.
	 *
	 * @param JobState $state
	 */
	public function setState(JobState $state): static;

	/**
	 * Gets the job started at time.
	 *
	 * @return null|float
	 */
	public function getStartedAt(): ?float;

	/**
	 * Sets the job started at time.
	 *
	 * @param null|float $started_at
	 */
	public function setStartedAt(?float $started_at): static;

	/**
	 * Gets the job ended at time.
	 *
	 * @return null|float
	 */
	public function getEndedAt(): ?float;

	/**
	 * Sets the job ended at time.
	 *
	 * @param null|float $ended_at
	 */
	public function setEndedAt(?float $ended_at): static;

	/**
	 * Gets the job result.
	 *
	 * @return JSONResult
	 */
	public function getResult(): JSONResult;

	/**
	 * Sets the job result.
	 *
	 * @param JSONResult $result
	 */
	public function setResult(JSONResult $result): static;

	/**
	 * Gets the job try count.
	 *
	 * @return int
	 */
	public function getTryCount(): int;

	/**
	 * Sets the job try count.
	 *
	 * @param int $try_count
	 */
	public function setTryCount(int $try_count): static;

	/**
	 * Increments the job try count.
	 */
	public function incrementTryCount(): static;

	/**
	 * Gets the job max retry count.
	 *
	 * @return int
	 */
	public function getRetryMax(): int;

	/**
	 * Sets the job max retry count.
	 *
	 * @param int $retry_max
	 */
	public function setRetryMax(int $retry_max): static;

	/**
	 * Gets the job retry delay.
	 *
	 * @return int
	 */
	public function getRetryDelay(): int;

	/**
	 * Sets the job retry delay.
	 *
	 * @param int $retry_delay
	 */
	public function setRetryDelay(int $retry_delay): static;

	/**
	 * Dispatches the job to the queue in the given store.
	 *
	 * @param string $store_name
	 *
	 * @return JobContractInterface
	 */
	public function dispatch(string $store_name = Queue::DEFAULT_STORE): JobContractInterface;

	/**
	 * Gets the Unix timestamp before which the job must not be picked up.
	 *
	 * A null value means the job is immediately eligible.
	 *
	 * @return null|int
	 */
	public function getRunAfter(): ?int;

	/**
	 * Sets the Unix timestamp before which the job must not be picked up.
	 *
	 * @param null|int $run_after
	 */
	public function setRunAfter(?int $run_after): static;

	/**
	 * Gets the ordered list of serialized job specs to dispatch after successful completion.
	 *
	 * Each entry is a plain array that can be passed to {@link JobsManager::dispatchChained()}.
	 *
	 * @return array
	 */
	public function getChain(): array;

	/**
	 * Sets the ordered list of serialized job specs to dispatch after successful completion.
	 *
	 * @param array $chain
	 */
	public function setChain(array $chain): static;

	/**
	 * Gets the ref of the batch this job belongs to.
	 *
	 * @return null|string
	 */
	public function getBatchId(): ?string;

	/**
	 * Sets the ref of the batch this job belongs to.
	 *
	 * @param null|string $batch_id
	 */
	public function setBatchId(?string $batch_id): static;

	/**
	 * Returns true if this job is in the dead-letter state.
	 *
	 * @return bool
	 */
	public function isDeadLetter(): bool;
}
