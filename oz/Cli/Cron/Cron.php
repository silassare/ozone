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
use OZONE\Core\Cli\Cron\Hooks\CronCollect;
use OZONE\Core\Cli\Cron\Interfaces\TaskInterface;
use OZONE\Core\Cli\Cron\Tasks\CallableTask;
use OZONE\Core\Cli\Cron\Tasks\CommandTask;
use OZONE\Core\Cli\Cron\Workers\CronTaskWorker;
use OZONE\Core\Db\OZJobsQuery;
use OZONE\Core\Exceptions\RuntimeException;
use OZONE\Core\Queue\Interfaces\WorkerInterface;
use OZONE\Core\Queue\JobsManager;
use OZONE\Core\Queue\JobState;
use OZONE\Core\Queue\Queue;
use OZONE\Core\Utils\JSONResult;
use PHPUtils\Str;
use Throwable;

/**
 * Class Cron.
 *
 * Static registry and entry point for the cron scheduler.
 *
 * Tasks are registered with {@link addTask()} (or the convenience helpers {@link call()},
 * {@link command()}, {@link work()}), then collected at runtime via a {@link CronCollect} hook.
 *
 * {@link runDues()} is called on each tick ({@see CronRunner::tick()}: a scheduler, the `oz:cron`
 * route or a request): it iterates all registered tasks, checks for due {@link Schedule} instances,
 * and enqueues each due task as a {@link CronTaskWorker} job to the appropriate queue (`cron:sync`
 * or `cron:async`), once per scheduled minute.
 * Actual execution is handled later by {@link JobsManager::run()} when those queues are
 * processed, or immediately via {@link start()} for single direct runs.
 */
final class Cron
{
	/**
	 * How far back {@see runDues()} dispatches the tasks of the minutes no tick ran, in seconds.
	 */
	public const CATCH_UP = 3600;

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
	 * Each task due in a minute since `$since` -- the last tick, an hour back at most -- up to the
	 * current one is dispatched once, as a {@link CronTaskWorker} job to the appropriate cron queue
	 * (`cron:sync` or `cron:async`). Actual execution is deferred to {@link JobsManager::run()} which
	 * processes those queues.
	 *
	 * Once whoever else dispatches the same minute -- another server's scheduler, a request, the
	 * `oz:cron` route: the job's ref is the task's and the minute's ({@see jobRef()}), and a job store
	 * refuses a ref it has.
	 *
	 * @param null|int $since when the last tick ran; null: the current minute only
	 *
	 * @throws Exception
	 */
	public static function runDues(?int $since = null): void
	{
		self::collect();

		$now  = \intdiv(\time(), 60) * 60;
		$from = null === $since ? $now : \max((\intdiv($since, 60) + 1) * 60, $now - self::CATCH_UP);

		foreach (self::$tasks as $task) {
			for ($minute = $from; $minute <= $now; $minute += 60) {
				if (self::dispatchIfDue($task, $minute)) {
					break;
				}
			}
		}
	}

	/**
	 * The ref of the job that runs a task for a minute: the same for whoever dispatches it.
	 */
	public static function jobRef(string $task_name, int $minute): string
	{
		return 'cron:' . \hash('xxh128', $task_name . "\0" . \intdiv($minute, 60));
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

		return self::call(static function (JSONResult $result) use ($worker, $queue, $store): void {
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

	/**
	 * Dispatches a task when one of its schedules is due in a minute.
	 *
	 * @return bool whether the task was due (dispatched, or left to an instance still running)
	 *
	 * @throws Exception
	 */
	private static function dispatchIfDue(TaskInterface $task, int $minute): bool
	{
		foreach ($task->getSchedules() as $schedule) {
			if (!($schedule->isDue($minute) && $schedule->shouldRun())) {
				continue;
			}

			if ($task->shouldRunOneAtATime()) {
				// Multi-server safe: check the shared DB for existing PENDING or RUNNING instances.
				// The oz_jobs table is visible to all servers sharing the same DB, so this check
				// prevents concurrent dispatch across multiple nodes.
				$existing = (new OZJobsQuery())
					->whereNameIs($task->getName())
					->whereWorkerIs(CronTaskWorker::class)
					->whereStateIsIn([JobState::PENDING->value, JobState::RUNNING->value])
					->find()
					->getTotal();

				if ($existing > 0) {
					return true;
				}
			}

			$queue = Queue::get($task->shouldRunInBackground() ? Queue::CRON_ASYNC : Queue::CRON_SYNC);
			$ref   = self::jobRef($task->getName(), $minute);

			try {
				// Override the default CronTaskWorker::class job name with the task name.
				// This ensures the multi-server oneAtATime DB check can identify instances by task name.
				$queue->push(new CronTaskWorker($task->getName()), $ref)
					->setName($task->getName())
					->dispatch();
			} catch (Throwable $t) {
				// Dispatched already, by whoever ran this minute first; anything else is a failure.
				if (null === JobsManager::getStore(Queue::DEFAULT_STORE)->get($ref)) {
					throw $t;
				}
			}

			return true;
		}

		return false;
	}
}
