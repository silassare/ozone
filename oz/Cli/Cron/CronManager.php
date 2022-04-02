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

namespace OZONE\OZ\Cli\Cron;

use OZONE\OZ\Cli\Cron\Interfaces\CronInterface;
use OZONE\OZ\Cli\Cron\Interfaces\TaskInterface;
use OZONE\OZ\Core\Configs;
use OZONE\OZ\Exceptions\RuntimeException;

/**
 * Class CronManager.
 */
final class CronManager
{
	/**
	 * @var TaskInterface[]
	 */
	private array $tasks = [];

	/**
	 * Schedule a cron task.
	 */
	public function scheduleTask(TaskInterface $task, Schedule $schedule): void
	{
		$this->tasks[$task->getName()] = ['task' => $task, 'schedule' => $schedule];
	}

	/**
	 * Get a task with a given name.
	 */
	public function getTask(string $task_name): ?TaskInterface
	{
		return $this->tasks[$task_name] ?? null;
	}

	/**
	 * Register all task provider.
	 */
	public function registerTaskProviders(): void
	{
		$cron_task_providers = Configs::load('oz.cron');

		foreach ($cron_task_providers as $provider => $enabled) {
			if ($enabled) {
				if (!\is_subclass_of($provider, CronInterface::class)) {
					throw new RuntimeException(\sprintf(
						'Cron task provider "%s" should implements "%s".',
						$provider,
						CronInterface::class
					));
				}

				/* @var CronInterface $provider */
				$provider::register($this);
			}
		}
	}
}
