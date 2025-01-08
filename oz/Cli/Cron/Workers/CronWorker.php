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

use OZONE\Core\Cli\Cron\Cron;
use OZONE\Core\Cli\Cron\Interfaces\TaskInterface;
use OZONE\Core\Queue\Interfaces\JobContractInterface;
use OZONE\Core\Queue\Interfaces\WorkerInterface;
use RuntimeException;

/**
 * Class CronWorker.
 */
class CronWorker implements WorkerInterface
{
	private TaskInterface $task;

	/**
	 * CronWorker constructor.
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
	public static function getName(): string
	{
		return self::class;
	}

	/**
	 * {@inheritDoc}
	 */
	public function isAsync(): bool
	{
		return $this->task->shouldRunInBackground();
	}

	/**
	 * {@inheritDoc}
	 */
	public function work(JobContractInterface $job_contract): self
	{
		$this->task->run();

		return $this;
	}

	/**
	 * {@inheritDoc}
	 */
	public static function fromPayload(array $payload): WorkerInterface
	{
		return new static($payload['task_name']);
	}

	/**
	 * {@inheritDoc}
	 */
	public function getPayload(): array
	{
		return [
			'task_name' => $this->task_name,
		];
	}

	/**
	 * {@inheritDoc}
	 */
	public function getResult(): array
	{
		return [
			'output' => '__UNIMPLEMENTED__', // TODO read output for command line tasks
		];
	}
}
