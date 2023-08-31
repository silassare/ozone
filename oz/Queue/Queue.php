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

use OZONE\Core\Queue\Interfaces\WorkerInterface;
use OZONE\Core\Queue\Stores\DbJobStore;

/**
 * Class Queue.
 */
final class Queue
{
	public const DEFAULT       = 'default';
	public const CRON_ASYNC    = 'cron:async';
	public const CRON_SYNC     = 'cron:sync';
	public const DEFAULT_STORE = DbJobStore::NAME;

	/**
	 * @var array<string, \OZONE\Core\Queue\Queue>
	 */
	private static array $queues = [];

	private bool $stop_on_error                = false;
	private int $max_consecutive_errors_count  = 3;
	private int $max_errors_count              = 10;

	/**
	 * Queue constructor.
	 *
	 * @param string $name
	 */
	public function __construct(protected readonly string $name)
	{
	}

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
	 *
	 * @return \OZONE\Core\Queue\Queue
	 */
	public function enableStopOnError(bool $stop_on_error = true): self
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
	 *
	 * @return \OZONE\Core\Queue\Queue
	 */
	public function setMaxConsecutiveErrorsCount(int $max_consecutive_errors_count): self
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
	 *
	 * @return \OZONE\Core\Queue\Queue
	 */
	public function setMaxErrorsCount(int $max_errors): self
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
	 * Adds a job to the queue.
	 *
	 * @param \OZONE\Core\Queue\Interfaces\WorkerInterface $worker
	 *
	 * @return \OZONE\Core\Queue\Job
	 */
	public function push(WorkerInterface $worker): Job
	{
		$ref = \uniqid('', true);

		return (new Job($ref, $worker::getName(), $worker->getPayload()))->setQueue($this->name);
	}

	/**
	 * Gets a queue by name.
	 *
	 * @param string $name
	 *
	 * @return \OZONE\Core\Queue\Queue
	 */
	public static function get(string $name): self
	{
		if (!isset(self::$queues[$name])) {
			self::$queues[$name] = new self($name);
		}

		return self::$queues[$name];
	}

	/**
	 * Gets all queues.
	 *
	 * @return array<string, \OZONE\Core\Queue\Queue>
	 */
	public static function getQueues(): array
	{
		return self::$queues;
	}
}
