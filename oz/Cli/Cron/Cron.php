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

namespace OZONE\Core\Cli\Cron;

use Exception;
use OZONE\Core\Cache\CacheManager;
use OZONE\Core\Cli\Cron\Hooks\CronCollect;
use OZONE\Core\Cli\Cron\Interfaces\TaskInterface;
use OZONE\Core\Cli\Cron\Tasks\CallableTask;
use OZONE\Core\Cli\Cron\Tasks\CommandTask;
use OZONE\Core\Cli\Cron\Workers\CronTaskWorker;
use OZONE\Core\Exceptions\RuntimeException;
use OZONE\Core\Queue\Interfaces\WorkerInterface;
use OZONE\Core\Queue\Queue;
use OZONE\Core\Utils\JSONResult;
use PHPUtils\Str;

/**
 * Class Cron.
 *
 * Static registry and entry point for the cron scheduler.
 *
 * Tasks are registered with {@link addTask()} (or the convenience helpers {@link call()},
 * {@link command()}, {@link work()}), then collected at runtime via a {@link CronCollect} hook.
 *
 * {@link runDues()} is called by the CLI cron runner on each tick: it iterates all
 * registered tasks, checks for due {@link Schedule} instances, and enqueues each due task
 * as a {@link CronTaskWorker} job to the appropriate queue (`cron:sync` or `cron:async`).
 * Actual execution is handled later by {@link JobsManager::run()} when those queues are
 * processed, or immediately via {@link start()} for single direct runs.
 */
final class Cron
{
	private static bool $collected = false;

	/**
	 * @var array<string, TaskInterface>
	 */
	private static array $tasks = [];

	/**
	 * Schedule a cron task.
	 */
	public static function addTask(TaskInterface $task): void
	{
		$name = $task->getName();

		if (isset(self::$tasks[$name])) {
			throw new RuntimeException(\sprintf(
				'Cron task "%s" with same name already exists.',
				$name
			));
		}

		self::$tasks[$name] = $task;
	}

	/**
	 * Get a task with a given name.
	 */
	public static function getTask(string $task_name): ?TaskInterface
	{
		return self::$tasks[$task_name] ?? null;
	}

	/**
	 * Enqueue all due scheduled tasks.
	 *
	 * Each due task is dispatched as a {@link CronTaskWorker} job to the appropriate
	 * cron queue (`cron:sync` or `cron:async`). Actual execution is deferred to
	 * {@link JobsManager::run()} which processes those queues.
	 *
	 * @throws Exception
	 */
	public static function runDues(): void
	{
		self::collect();

		$now = \time();

		foreach (self::$tasks as $task) {
			foreach ($task->getSchedules() as $schedule) {
				if (!($schedule->isDue($now) && $schedule->shouldRun())) {
					continue;
				}

				if ($task->shouldRunOneAtATime()) {
					// Skip dispatch when a previous instance is still holding its cache lock.
					if (CacheManager::persistent('cron')->has('cron_task_' . $task->getName())) {
						break;
					}
				}

				$queue = Queue::get($task->shouldRunInBackground() ? Queue::CRON_ASYNC : Queue::CRON_SYNC);

				$queue->push(new CronTaskWorker($task->getName()))
					->dispatch();

				break;
			}
		}
	}

	/**
	 * Collect all cron tasks.
	 */
	public static function collect(): void
	{
		if (self::$collected) {
			return;
		}

		self::$collected = true;

		(new CronCollect())->dispatch();
	}

	/**
	 * Schedule a command task.
	 *
	 * @param array|string $command
	 * @param string       $name
	 * @param string       $description
	 * @param bool         $in_background
	 *
	 * @return Schedule
	 */
	public static function command(
		array|string $command,
		string $name = '',
		string $description = '',
		bool $in_background = true
	): Schedule {
		$fallback_name = \is_string($command) ? $command : \implode(' ', $command);
		$name          = empty($name) ? $fallback_name : $name;

		self::addTask($task = new CommandTask($command, $name, $description));

		if ($in_background) {
			$task->inBackground();
		}

		return $task->schedule();
	}

	/**
	 * Schedule a callable cron task.
	 *
	 * @param string                    $name
	 * @param callable(JSONResult):void $callable
	 * @param string                    $description
	 *
	 * @return Schedule
	 */
	public static function call(callable $callable, string $name = '', string $description = ''): Schedule
	{
		$name = empty($name) ? Str::callableName($callable) : $name;

		self::addTask($task = new CallableTask($name, $callable, $description));

		return $task->schedule();
	}

	/**
	 * Schedule a worker job.
	 *
	 * @param WorkerInterface $worker
	 * @param string          $queue
	 * @param string          $store
	 *
	 * @return Schedule
	 */
	public static function work(
		WorkerInterface $worker,
		string $queue = Queue::DEFAULT,
		string $store = Queue::DEFAULT_STORE
	): Schedule {
		// Derive a stable, collision-free name from worker + queue so that
		// multiple Cron::work() calls never clash on the task registry.
		$name = \sprintf('work@%s[%s]', $worker::getName(), $queue);

		return self::call(static function (JSONResult $result) use ($worker, $queue, $store) {
			$job_contract = Queue::get($queue)
				->push($worker)
				->dispatch($store);

			$result->setDone()->setData([
				'job_ref' => $job_contract->getRef(),
				'queue'   => $queue,
				'store'   => $store,
			]);
		}, $name);
	}

	/**
	 * Start a cron task.
	 *
	 * @param string $name
	 */
	public static function start(string $name): void
	{
		self::collect();

		$task = self::getTask($name);

		if (!$task) {
			throw new RuntimeException(\sprintf(
				'Cron task "%s" not found.',
				$name
			));
		}

		$task->run();
	}
}
