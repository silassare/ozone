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
	public function work(JobContractInterface $job_contract): self
	{
		$this->task->run();

		return $this;
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public static function fromPayload(array $payload): WorkerInterface
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
