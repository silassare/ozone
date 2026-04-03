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

use OZONE\Core\App\Keys;
use OZONE\Core\Queue\Interfaces\WorkerInterface;
use OZONE\Core\Queue\Stores\DbJobStore;

/**
 * Class Queue.
 *
 * Represents a named job channel with configurable error-tolerance policies.
 *
 * {@link push()} wraps a {@link WorkerInterface} in a {@link Job} and returns it ready
 * to be persisted via {@link Job::dispatch()}. Built-in channel names:
 *
 * - {@link Queue::DEFAULT} - general-purpose jobs
 * - {@link Queue::CRON_SYNC} - due cron tasks that must run in the foreground
 * - {@link Queue::CRON_ASYNC} - due cron tasks that run in a background process
 *
 * Retrieve or lazily create a channel with {@link Queue::get()}.
 */
final class Queue
{
	public const DEFAULT       = 'default';
	public const CRON_ASYNC    = 'cron:async';
	public const CRON_SYNC     = 'cron:sync';
	public const DEFAULT_STORE = DbJobStore::NAME;

	/**
	 * @var array<string, Queue>
	 */
	private static array $queues = [];

	private bool $stop_on_error                = false;
	private int $max_consecutive_errors_count  = 3;
	private int $max_errors_count              = 10;
	private ?int $max_concurrent               = null;

	/**
	 * Queue constructor.
	 *
	 * @param string $name
	 */
	public function __construct(private readonly string $name) {}

	/**
	 * Gets queue name.
	 *
	 * @return string
	 */
	public function getName(): string
	{
		return $this->name;
	}

	/**
	 * Enables or disables stop on error.
	 *
	 * @param bool $stop_on_error
	 */
	public function enableStopOnError(bool $stop_on_error = true): static
	{
		$this->stop_on_error = $stop_on_error;

		return $this;
	}

	/**
	 * Checks if stop on error is enabled.
	 *
	 * @return bool
	 */
	public function shouldStopOnError(): bool
	{
		return $this->stop_on_error;
	}

	/**
	 * Sets max consecutive errors count.
	 *
	 * @param int $max_consecutive_errors_count
	 */
	public function setMaxConsecutiveErrorsCount(int $max_consecutive_errors_count): static
	{
		$this->max_consecutive_errors_count = $max_consecutive_errors_count;

		return $this;
	}

	/**
	 * Gets max consecutive errors count.
	 *
	 * @return int
	 */
	public function getMaxConsecutiveErrorsCount(): int
	{
		return $this->max_consecutive_errors_count;
	}

	/**
	 * Sets max errors count.
	 *
	 * @param int $max_errors
	 */
	public function setMaxErrorsCount(int $max_errors): static
	{
		$this->max_errors_count = $max_errors;

		return $this;
	}

	/**
	 * Gets max errors count.
	 *
	 * @return int
	 */
	public function getMaxErrorsCount(): int
	{
		return $this->max_errors_count;
	}

	/**
	 * Sets the maximum number of concurrent RUNNING jobs allowed in this queue.
	 *
	 * When the RUNNING job count exceeds this limit at async dispatch time,
	 * the new async job is demoted to synchronous in-process execution rather
	 * than spawning an additional subprocess. Set to null (default) for no limit.
	 *
	 * @param null|int $max_concurrent
	 */
	public function setMaxConcurrent(?int $max_concurrent): static
	{
		$this->max_concurrent = $max_concurrent;

		return $this;
	}

	/**
	 * Gets the maximum number of concurrent RUNNING jobs allowed in this queue.
	 *
	 * @return null|int null means unlimited
	 */
	public function getMaxConcurrent(): ?int
	{
		return $this->max_concurrent;
	}

	/**
	 * Adds a job to the queue.
	 *
	 * @param WorkerInterface $worker
	 *
	 * @return Job
	 */
	public function push(WorkerInterface $worker): Job
	{
		$ref = Keys::id64('job-ref');

		return (new Job($ref, $worker::getName(), $worker->getPayload()))
			->setQueue($this->name)
			->setName($worker::getName());
	}

	/**
	 * Gets a queue by name.
	 *
	 * @param string $name
	 */
	public static function get(string $name): static
	{
		if (!isset(self::$queues[$name])) {
			self::$queues[$name] = new self($name);
		}

		return self::$queues[$name];
	}

	/**
	 * Gets all queues.
	 *
	 * @return array<string, Queue>
	 */
	public static function getQueues(): array
	{
		return self::$queues;
	}
}
