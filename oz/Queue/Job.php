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
use OZONE\Core\Queue\Interfaces\JobContractInterface;
use OZONE\Core\Utils\JSONResult;

/**
 * Class Job.
 *
 * A serializable work unit ready to be dispatched to a job store.
 *
 * Holds the fully-qualified worker class name, the payload array that will be passed to
 * {@link WorkerInterface::fromPayload()}, execution state, retry settings, and the result
 * produced after execution completes as a {@link JSONResult}.
 *
 * `Job` is a pure value object - it has no persistence knowledge. Call {@link dispatch()}
 * to add it to a store and receive a {@link JobContractInterface} with lifecycle methods.
 */
class Job implements Interfaces\JobInterface
{
	protected string $queue          = Queue::DEFAULT;
	protected string $name           = '';
	protected JobState $state        = JobState::PENDING;
	protected ?float $started_at     = null;
	protected ?float $ended_at       = null;
	protected int $created_at;
	protected int $updated_at;
	protected JSONResult $result;
	protected int $try_count         = 0;
	protected int $retry_max         = 3;
	protected int $priority          = 0;
	protected int $retry_delay       = 60; // 1 minute
	protected ?int $run_after        = null;
	protected array $chain           = [];
	protected ?string $batch_id      = null;

	/**
	 * Job constructor.
	 *
	 * @param string $ref     unique job reference
	 * @param string $worker  fully-qualified worker class name
	 * @param array  $payload input data passed to {@link WorkerInterface::fromPayload()}
	 */
	public function __construct(
		protected readonly string $ref,
		protected readonly string $worker,
		protected array $payload = [],
	) {
		$this->created_at = $this->updated_at = \time();
		$this->result     = new JSONResult();
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function getRef(): string
	{
		return $this->ref;
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function getName(): string
	{
		return $this->name;
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function setName(string $name): static
	{
		$this->name = $name;

		return $this;
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function getPayload(): array
	{
		return $this->payload;
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function getWorker(): string
	{
		return $this->worker;
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function getQueue(): string
	{
		return $this->queue;
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function setQueue(string $queue): static
	{
		$this->queue = $queue;

		return $this;
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function getPriority(): int
	{
		return $this->priority;
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function setPriority(int $priority): static
	{
		$this->priority = $priority;

		return $this;
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function setCreatedAt(int $created_at): static
	{
		$this->created_at = $created_at;

		return $this;
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function getCreatedAt(): int
	{
		return $this->created_at;
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function setUpdatedAt(int $updated_at): static
	{
		$this->updated_at = $updated_at;

		return $this;
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function getUpdatedAt(): int
	{
		return $this->updated_at;
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function getState(): JobState
	{
		return $this->state;
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function setState(JobState $state): static
	{
		$this->state = $state;

		return $this;
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function getStartedAt(): ?float
	{
		return $this->started_at;
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function setStartedAt(?float $started_at): static
	{
		$this->started_at = $started_at;

		return $this;
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function getEndedAt(): ?float
	{
		return $this->ended_at;
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function setEndedAt(?float $ended_at): static
	{
		$this->ended_at = $ended_at;

		return $this;
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function getResult(): JSONResult
	{
		return $this->result;
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function setResult(JSONResult $result): static
	{
		$this->result = $result;

		return $this;
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function getTryCount(): int
	{
		return $this->try_count;
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function setTryCount(int $try_count): static
	{
		$this->try_count = $try_count;

		return $this;
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function incrementTryCount(): static
	{
		++$this->try_count;

		return $this;
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function getRetryMax(): int
	{
		return $this->retry_max;
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function setRetryMax(int $retry_max): static
	{
		$this->retry_max = $retry_max;

		return $this;
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function getRetryDelay(): int
	{
		return $this->retry_delay;
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function setRetryDelay(int $retry_delay): static
	{
		$this->retry_delay = $retry_delay;

		return $this;
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function dispatch(string $store_name = Queue::DEFAULT_STORE): JobContractInterface
	{
		return JobsManager::getStore($store_name)
			->add($this);
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function getRunAfter(): ?int
	{
		return $this->run_after;
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function setRunAfter(?int $run_after): static
	{
		$this->run_after = $run_after;

		return $this;
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function getChain(): array
	{
		return $this->chain;
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function setChain(array $chain): static
	{
		$this->chain = $chain;

		return $this;
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function getBatchId(): ?string
	{
		return $this->batch_id;
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function setBatchId(?string $batch_id): static
	{
		$this->batch_id = $batch_id;

		return $this;
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function isDeadLetter(): bool
	{
		return JobState::DEAD_LETTER === $this->state;
	}
}
