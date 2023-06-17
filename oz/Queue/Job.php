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

use OZONE\Core\Queue\Interfaces\JobContractInterface;

/**
 * Class Job.
 */
class Job implements Interfaces\JobInterface
{
	protected string   $queue       = Queue::DEFAULT;
	protected string   $name        = '';
	protected JobState $state       = JobState::PENDING;
	protected ?float   $started_at  = null;
	protected ?float   $ended_at    = null;
	protected int      $created_at;
	protected int      $updated_at;
	protected array    $result      = [];
	protected array    $errors      = [];
	protected int      $try_count   = 0;
	protected int      $retry_max   = 3;
	protected int      $priority    = 0;
	protected int      $retry_delay = 60; // 1 minute

	/**
	 * Job constructor.
	 *
	 * @param string $ref
	 * @param string $worker
	 * @param array  $payload
	 */
	public function __construct(
		protected readonly string $ref,
		protected readonly string $worker,
		protected readonly array $payload
	) {
		$this->created_at = $this->updated_at = \time();
	}

	/**
	 * {@inheritDoc}
	 */
	public function getRef(): string
	{
		return $this->ref;
	}

	/**
	 * {@inheritDoc}
	 */
	public function getName(): string
	{
		return $this->name;
	}

	/**
	 * {@inheritDoc}
	 */
	public function setName(string $name): self
	{
		$this->name = $name;

		return $this;
	}

	/**
	 * {@inheritDoc}
	 */
	public function getPayload(): array
	{
		return $this->payload;
	}

	/**
	 * {@inheritDoc}
	 */
	public function getWorker(): string
	{
		return $this->worker;
	}

	/**
	 * {@inheritDoc}
	 */
	public function getQueue(): string
	{
		return $this->queue;
	}

	/**
	 * {@inheritDoc}
	 */
	public function setQueue(string $queue): self
	{
		$this->queue = $queue;

		return $this;
	}

	/**
	 * {@inheritDoc}
	 */
	public function getPriority(): int
	{
		return $this->priority;
	}

	/**
	 * {@inheritDoc}
	 */
	public function setPriority(int $priority): self
	{
		$this->priority = $priority;

		return $this;
	}

	/**
	 * {@inheritDoc}
	 */
	public function setCreatedAt(int $created_at): self
	{
		$this->created_at = $created_at;

		return $this;
	}

	/**
	 * {@inheritDoc}
	 */
	public function getCreatedAt(): int
	{
		return $this->created_at;
	}

	/**
	 * {@inheritDoc}
	 */
	public function setUpdatedAt(int $updated_at): self
	{
		$this->updated_at = $updated_at;

		return $this;
	}

	/**
	 * {@inheritDoc}
	 */
	public function getUpdatedAt(): int
	{
		return $this->updated_at;
	}

	/**
	 * {@inheritDoc}
	 */
	public function getState(): JobState
	{
		return $this->state;
	}

	/**
	 * {@inheritDoc}
	 */
	public function setState(JobState $state): self
	{
		$this->state = $state;

		return $this;
	}

	/**
	 * {@inheritDoc}
	 */
	public function getStartedAt(): ?float
	{
		return $this->started_at;
	}

	/**
	 * {@inheritDoc}
	 */
	public function setStartedAt(?float $started_at): self
	{
		$this->started_at = $started_at;

		return $this;
	}

	/**
	 * {@inheritDoc}
	 */
	public function getEndedAt(): ?float
	{
		return $this->ended_at;
	}

	/**
	 * {@inheritDoc}
	 */
	public function setEndedAt(?float $ended_at): self
	{
		$this->ended_at = $ended_at;

		return $this;
	}

	/**
	 * {@inheritDoc}
	 */
	public function getResult(): array
	{
		return $this->result;
	}

	/**
	 * {@inheritDoc}
	 */
	public function setResult(array $result): self
	{
		$this->result = $result;

		return $this;
	}

	/**
	 * {@inheritDoc}
	 */
	public function getErrors(): array
	{
		return $this->errors;
	}

	/**
	 * {@inheritDoc}
	 */
	public function setErrors(array $errors): self
	{
		$this->errors = $errors;

		return $this;
	}

	/**
	 * {@inheritDoc}
	 */
	public function getTryCount(): int
	{
		return $this->try_count;
	}

	/**
	 * {@inheritDoc}
	 */
	public function setTryCount(int $try_count): self
	{
		$this->try_count = $try_count;

		return $this;
	}

	/**
	 * {@inheritDoc}
	 */
	public function incrementTryCount(): self
	{
		++$this->try_count;

		return $this;
	}

	/**
	 * {@inheritDoc}
	 */
	public function getRetryMax(): int
	{
		return $this->retry_max;
	}

	/**
	 * {@inheritDoc}
	 */
	public function setRetryMax(int $retry_max): self
	{
		$this->retry_max = $retry_max;

		return $this;
	}

	/**
	 * {@inheritDoc}
	 */
	public function getRetryDelay(): int
	{
		return $this->retry_delay;
	}

	/**
	 * {@inheritDoc}
	 */
	public function setRetryDelay(int $retry_delay): self
	{
		$this->retry_delay = $retry_delay;

		return $this;
	}

	/**
	 * {@inheritDoc}
	 */
	public function dispatch(string $store_name = Queue::DEFAULT_STORE): JobContractInterface
	{
		return JobsManager::getStore($store_name)
			->add($this);
	}
}
