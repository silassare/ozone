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
	 */
	public function getRef(): string;

	/**
	 * Gets job name.
	 */
	public function getName(): string;

	/**
	 * Sets job name.
	 */
	public function setName(string $name): static;

	/**
	 * Gets payload.
	 */
	public function getPayload(): array;

	/**
	 * Gets the job worker name.
	 */
	public function getWorker(): string;

	/**
	 * Gets the job queue name.
	 */
	public function getQueue(): string;

	/**
	 * Sets the job queue name.
	 */
	public function setQueue(string $queue): static;

	/**
	 * Gets the job priority.
	 */
	public function getPriority(): int;

	/**
	 * Opt in to payload encryption.
	 *
	 * When set, the job store encrypts the payload before writing to the backing store
	 * and decrypts it on load. Uses {@link \OZONE\Core\Crypt\DoCrypt} with the application
	 * secret from {@link \OZONE\Core\App\Keys::secret()}.
	 */
	public function encrypted(): static;

	/**
	 * Checks whether the payload should be encrypted at rest.
	 */
	public function shouldEncryptPayload(): bool;

	/**
	 * Sets the job priority.
	 */
	public function setPriority(int $priority): static;

	/**
	 * Sets the job created at time.
	 */
	public function setCreatedAt(int $created_at): static;

	/**
	 * Gets the job created at time.
	 */
	public function getCreatedAt(): int;

	/**
	 * Sets the job updated at time.
	 */
	public function setUpdatedAt(int $updated_at): static;

	/**
	 * Gets the job updated at time.
	 */
	public function getUpdatedAt(): int;

	/**
	 * Gets the job state.
	 */
	public function getState(): JobState;

	/**
	 * Sets the job state.
	 */
	public function setState(JobState $state): static;

	/**
	 * Gets the job started at time.
	 */
	public function getStartedAt(): ?float;

	/**
	 * Sets the job started at time.
	 */
	public function setStartedAt(?float $started_at): static;

	/**
	 * Gets the job ended at time.
	 */
	public function getEndedAt(): ?float;

	/**
	 * Sets the job ended at time.
	 */
	public function setEndedAt(?float $ended_at): static;

	/**
	 * Gets the job result.
	 */
	public function getResult(): JSONResult;

	/**
	 * Sets the job result.
	 */
	public function setResult(JSONResult $result): static;

	/**
	 * Gets the job try count.
	 */
	public function getTryCount(): int;

	/**
	 * Sets the job try count.
	 */
	public function setTryCount(int $try_count): static;

	/**
	 * Increments the job try count.
	 */
	public function incrementTryCount(): static;

	/**
	 * Gets the job max retry count.
	 */
	public function getRetryMax(): int;

	/**
	 * Sets the job max retry count.
	 */
	public function setRetryMax(int $retry_max): static;

	/**
	 * Gets the job retry delay.
	 */
	public function getRetryDelay(): int;

	/**
	 * Sets the job retry delay.
	 */
	public function setRetryDelay(int $retry_delay): static;

	/**
	 * Dispatches the job to the queue in the given store.
	 */
	public function dispatch(string $store_name = Queue::DEFAULT_STORE): JobContractInterface;

	/**
	 * Gets the Unix timestamp before which the job must not be picked up.
	 *
	 * A null value means the job is immediately eligible.
	 */
	public function getRunAfter(): ?int;

	/**
	 * Sets the Unix timestamp before which the job must not be picked up.
	 */
	public function setRunAfter(?int $run_after): static;

	/**
	 * Gets the ordered list of serialized job specs to dispatch after successful completion.
	 *
	 * Each entry is a plain array that can be passed to {@link JobsManager::dispatchChained()}.
	 */
	public function getChain(): array;

	/**
	 * Sets the ordered list of serialized job specs to dispatch after successful completion.
	 */
	public function setChain(array $chain): static;

	/**
	 * Gets the ref of the batch this job belongs to.
	 */
	public function getBatchId(): ?string;

	/**
	 * Sets the ref of the batch this job belongs to.
	 */
	public function setBatchId(?string $batch_id): static;

	/**
	 * Returns true if this job is in the dead-letter state.
	 */
	public function isDeadLetter(): bool;
}
