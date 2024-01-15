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
	 *
	 * @return $this
	 */
	public function setName(string $name): self;

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
	 *
	 * @return $this
	 */
	public function setQueue(string $queue): self;

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
	 *
	 * @return $this
	 */
	public function setPriority(int $priority): self;

	/**
	 * Sets the job created at time.
	 *
	 * @param int $created_at
	 *
	 * @return $this
	 */
	public function setCreatedAt(int $created_at): self;

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
	 *
	 * @return $this
	 */
	public function setUpdatedAt(int $updated_at): self;

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
	 *
	 * @return $this
	 */
	public function setState(JobState $state): self;

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
	 *
	 * @return $this
	 */
	public function setStartedAt(?float $started_at): self;

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
	 *
	 * @return $this
	 */
	public function setEndedAt(?float $ended_at): self;

	/**
	 * Gets the job result.
	 *
	 * @return array
	 */
	public function getResult(): array;

	/**
	 * Sets the job result.
	 *
	 * @param array $result
	 *
	 * @return $this
	 */
	public function setResult(array $result): self;

	/**
	 * Gets the job errors.
	 *
	 * @return array
	 */
	public function getErrors(): array;

	/**
	 * Sets the job errors.
	 *
	 * @param array $errors
	 *
	 * @return $this
	 */
	public function setErrors(array $errors): self;

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
	 *
	 * @return $this
	 */
	public function setTryCount(int $try_count): self;

	/**
	 * Increments the job try count.
	 *
	 * @return $this
	 */
	public function incrementTryCount(): self;

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
	 *
	 * @return $this
	 */
	public function setRetryMax(int $retry_max): self;

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
	 *
	 * @return $this
	 */
	public function setRetryDelay(int $retry_delay): self;

	/**
	 * Dispatches the job to the queue in the given store.
	 *
	 * @param string $store_name
	 *
	 * @return JobContractInterface
	 */
	public function dispatch(string $store_name = Queue::DEFAULT_STORE): JobContractInterface;
}
