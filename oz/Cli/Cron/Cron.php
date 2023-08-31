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
use OZONE\Core\Cli\Cli;
use OZONE\Core\Cli\Cron\Hooks\CronCollect;
use OZONE\Core\Cli\Cron\Interfaces\TaskInterface;
use OZONE\Core\Cli\Cron\Tasks\CallableTask;
use OZONE\Core\Cli\Cron\Tasks\CommandTask;
use OZONE\Core\Cli\Cron\Workers\CronWorker;
use OZONE\Core\Exceptions\RuntimeException;
use OZONE\Core\Queue\Interfaces\WorkerInterface;
use OZONE\Core\Queue\Queue;
use PHPUtils\Str;

/**
 * Class Cron.
 */
final class Cron
{
	private static bool $collected = false;

	/**
	 * @var array<string, TaskInterface>
	 */
	private static array $tasks = [];

	/**
	 * Cron constructor.
	 */
	private function __construct()
	{
	}

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
	 * @throws Exception
	 */
	public static function runDues(): void
	{
		$now   = \time();
		$sync  = [];
		$async = [];

		foreach (self::$tasks as $task) {
			foreach ($task->getSchedules() as $schedule) {
				if ($schedule->isDue($now) && $schedule->shouldRun()) {
					if ($task->shouldRunInBackground()) {
						$async[] = $task;
					} else {
						$sync[] = $task;
					}
					$queue = Queue::get($task->shouldRunInBackground() ? Queue::CRON_ASYNC : Queue::CRON_SYNC);

					$queue->push(new CronWorker($task->getName()))
						->dispatch();

					break;
				}
			}
		}

		$cli = Cli::getInstance();

		// run async tasks
		foreach ($async as $task) {
			$cli->writeLn(\sprintf(
				'Running task "%s" in background...',
				$task->getName()
			));
			$task->run();
		}

		// run sync tasks
		foreach ($sync as $task) {
			$cli->writeLn(\sprintf(
				'Running task "%s" in foreground...',
				$task->getName()
			));

			$task->run();
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
	 * @return \OZONE\Core\Cli\Cron\Schedule
	 */
	public static function command(
		string|array $command,
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
	 * @param string           $name
	 * @param callable():array $callable
	 * @param string           $description
	 *
	 * @return \OZONE\Core\Cli\Cron\Schedule
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
	 * @param \OZONE\Core\Queue\Interfaces\WorkerInterface $worker
	 * @param string                                       $queue
	 * @param string                                       $store
	 *
	 * @return \OZONE\Core\Cli\Cron\Schedule
	 */
	public static function work(
		WorkerInterface $worker,
		string $queue = Queue::DEFAULT,
		string $store = Queue::DEFAULT_STORE
	): Schedule {
		return self::call(static function () use ($worker, $queue, $store) {
			$job_contract = Queue::get($queue)
				->push($worker)
				->dispatch($store);

			return [
				'job_ref' => $job_contract->getRef(),
				'queue'   => $queue,
				'store'   => $store,
			];
		});
	}

	/**
	 * Start a cron task.
	 *
	 * @param string $name
	 */
	public static function start(string $name): void
	{
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
