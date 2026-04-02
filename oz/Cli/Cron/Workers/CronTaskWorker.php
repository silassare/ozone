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

namespace OZONE\Core\Cli\Cron\Workers;

use Override;
use OZONE\Core\Cache\CacheManager;
use OZONE\Core\Cli\Cron\Cron;
use OZONE\Core\Cli\Cron\Interfaces\TaskInterface;
use OZONE\Core\Queue\Interfaces\JobContractInterface;
use OZONE\Core\Queue\Interfaces\WorkerInterface;
use OZONE\Core\Utils\JSONResult;
use RuntimeException;

/**
 * Class CronTaskWorker.
 *
 * Queue adapter that bridges the cron task system and the job queue.
 *
 * When the job runner picks up a cron job, this worker reconstructs the registered
 * {@link TaskInterface} by name via {@link Cron::getTask()} and delegates execution to
 * {@link TaskInterface::run()}. After `run()` completes, {@link getResult()} proxies back
 * the task's own result array so {@link JobsManager} can persist it on the job record.
 *
 * The payload is `['task_name' => string]`.
 * {@link isAsync()} mirrors the task's own `shouldRunInBackground()` flag.
 */
class CronTaskWorker implements WorkerInterface
{
	private TaskInterface $task;

	/**
	 * CronTaskWorker constructor.
	 *
	 * @param string $task_name
	 */
	public function __construct(protected readonly string $task_name)
	{
		$task = Cron::getTask($this->task_name);

		if (!$task) {
			throw new RuntimeException(\sprintf(
				'Cron task "%s" not found.',
				$this->task_name
			));
		}

		$this->task = $task;
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public static function getName(): string
	{
		return self::class;
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function isAsync(): bool
	{
		return $this->task->shouldRunInBackground();
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function work(JobContractInterface $job_contract): static
	{
		if ($this->task->shouldRunOneAtATime()) {
			$cache     = CacheManager::persistent('cron');
			$cache_key = 'cron_task_' . $this->task->getName();
			$timeout   = $this->task->getTimeout();
			// Use the task's execution timeout as the lock TTL so stale locks
			// expire automatically. Fall back to 24 h when no timeout is set.
			$ttl = $timeout > 0 ? (float) $timeout : 86400.0;

			if ($cache->has($cache_key)) {
				// A previous instance is still holding the lock -- skip quietly.
				$this->task->getResult()
					->setDone()
					->setData(['skipped' => true, 'reason' => 'oneAtATime: another instance is running']);

				return $this;
			}

			$cache->set($cache_key, true, $ttl);

			try {
				$this->task->run();
			} finally {
				$cache->delete($cache_key);
			}
		} else {
			$this->task->run();
		}

		return $this;
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public static function fromPayload(array $payload): static
	{
		return new static($payload['task_name']);
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function getPayload(): array
	{
		return [
			'task_name' => $this->task_name,
		];
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function getResult(): JSONResult
	{
		return $this->task->getResult();
	}
}
