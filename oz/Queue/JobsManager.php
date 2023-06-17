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

use OZONE\Core\Exceptions\BaseException;
use OZONE\Core\Exceptions\RuntimeException;
use OZONE\Core\Queue\Hooks\AfterJobFinished;
use OZONE\Core\Queue\Hooks\BeforeJobStart;
use OZONE\Core\Queue\Interfaces\JobContractInterface;
use OZONE\Core\Queue\Interfaces\JobStoreInterface;
use OZONE\Core\Queue\Interfaces\WorkerInterface;
use PHPUtils\Events\Event;
use Throwable;

/**
 * Class JobsManager.
 */
final class JobsManager
{
	/**
	 * @var \OZONE\Core\Queue\Interfaces\JobStoreInterface[]
	 */
	private static array $stores = [];

	/** @var array<string, class-string<\OZONE\Core\Queue\Interfaces\WorkerInterface>> */
	private static array $workers = [];

	/**
	 * Register a worker.
	 *
	 * @param class-string<\OZONE\Core\Queue\Interfaces\WorkerInterface> $worker_class
	 */
	public static function registerWorker(string $worker_class): void
	{
		if (!\is_subclass_of($worker_class, WorkerInterface::class)) {
			throw new RuntimeException(\sprintf('Worker "%s" must implement %s', $worker_class, WorkerInterface::class));
		}

		$worker_name = $worker_class::getName();

		if (isset(self::$workers[$worker_name])) {
			throw new RuntimeException('Worker already registered: ' . $worker_name);
		}

		self::$workers[$worker_name] = $worker_class;
	}

	/**
	 * Gets all registered workers.
	 *
	 * @return array<string, class-string<\OZONE\Core\Queue\Interfaces\WorkerInterface>>
	 */
	public static function getWorkers(): array
	{
		return self::$workers;
	}

	/**
	 * Gets a worker.
	 *
	 * @param string $worker_name
	 *
	 * @return class-string<\OZONE\Core\Queue\Interfaces\WorkerInterface>
	 */
	public static function getWorker(string $worker_name): string
	{
		if (!isset(self::$workers[$worker_name])) {
			throw new RuntimeException('Worker not registered: ' . $worker_name);
		}

		return self::$workers[$worker_name];
	}

	/**
	 * Register a job store.
	 *
	 * @param \OZONE\Core\Queue\Interfaces\JobStoreInterface $store
	 */
	public static function registerStore(JobStoreInterface $store): void
	{
		if (isset(self::$stores[$store->getName()])) {
			throw new \RuntimeException('Job store already registered: ' . $store->getName());
		}

		self::$stores[$store->getName()] = $store;
	}

	/**
	 * Gets all registered job stores.
	 *
	 * @return array<string, \OZONE\Core\Queue\Interfaces\JobStoreInterface>
	 */
	public static function getStores(): array
	{
		return self::$stores;
	}

	/**
	 * Get a job store.
	 *
	 * @param string $store_name
	 *
	 * @return \OZONE\Core\Queue\Interfaces\JobStoreInterface
	 */
	public static function getStore(string $store_name): JobStoreInterface
	{
		if (!isset(self::$stores[$store_name])) {
			throw new \RuntimeException('Job store not registered: ' . $store_name);
		}

		return self::$stores[$store_name];
	}

	/**
	 * Run jobs in a queue.
	 *
	 * @param null|string $store_name  the store name, if null all registered store will be
	 *                                 used
	 * @param string      $queue_name  The queue name
	 * @param null|string $worker_name the worker name, if null all workers will be used
	 * @param null|int    $priority    the job priority, if null job will be run regardless of
	 *                                 its priority
	 * @param null|int    $max_job     the max number of jobs to run, if null all jobs will be
	 *                                 run
	 */
	public static function run(
		?string $store_name = null,
		string $queue_name = Queue::DEFAULT,
		?string $worker_name = null,
		?int $priority = null,
		?int $max_job = null
	): void {
		$queue                  = Queue::get($queue_name);
		$max_consecutive_errors = $queue->getMaxConsecutiveErrorsCount();
		$max_errors_count       = $queue->getMaxErrorsCount();

		$errors_count             = 0;
		$consecutive_errors_count = 0;
		$jobs_count               = 0;

		foreach (self::$stores as $store) {
			if (null === $store_name || $store->getName() === $store_name) {
				foreach ($store->iterator($queue->getName(), $worker_name, JobState::PENDING, $priority) as $job) {
					$max_try = $job->getRetryMax();

					if (JobState::FAILED === $job->getState() && $job->getTryCount() >= $max_try) {
						continue;
					}

					if (self::runJob($job)) {
						if (JobState::FAILED === $job->getState()) {
							if ($queue->shouldStopOnError()) {
								throw new RuntimeException(\sprintf(
									'Job "%s" failed in queue "%s".',
									$job->getRef(),
									$queue->getName()
								));
							}
							++$errors_count;
							++$consecutive_errors_count;
						} elseif (JobState::DONE === $job->getState()) {
							$consecutive_errors_count = 0;
						}

						if ($errors_count >= $max_errors_count || $consecutive_errors_count >= $max_consecutive_errors) {
							throw new RuntimeException(\sprintf(
								'Queue "%s" has reached the maximum number of errors allowed.',
								$queue->getName()
							));
						}

						if (null !== $max_job && ++$jobs_count >= $max_job) {
							break 2;
						}
					}
				}
			}
		}
	}

	/**
	 * Run a job.
	 *
	 * @param \OZONE\Core\Queue\Interfaces\JobContractInterface $job_contract
	 *
	 * @return bool
	 */
	public static function runJob(JobContractInterface $job_contract): bool
	{
		if ($job_contract->lock()) {
			Event::trigger(new BeforeJobStart($job_contract));

			$job_contract->setState(JobState::RUNNING);
			$job_contract->incrementTryCount();
			$job_contract->setStartedAt((float) \microtime(true));

			$job_contract->save();

			try {
				/** @var WorkerInterface $worker_class */
				$worker_class = self::getWorker($job_contract->getWorker());
				$worker       = $worker_class::fromPayload($job_contract->getPayload());

				if ($worker->isAsync()) {
					self::workAsync($worker, $job_contract);
				} else {
					$worker->work($job_contract);
					self::finish($job_contract);
				}
			} catch (Throwable $t) {
				$job_contract->setState(JobState::FAILED);
				$job_contract->setErrors(BaseException::throwableDescribe($t));
				self::finish($job_contract);
			}

			return true;
		}

		return false;
	}

	/**
	 * Finish a job.
	 *
	 * @param \OZONE\Core\Queue\Interfaces\JobContractInterface $job_contract
	 */
	public static function finish(
		JobContractInterface $job_contract
	): void {
		$job_contract->setEndedAt((float) \microtime(true));

		$job_contract->save();
		$job_contract->unlock();

		if (JobState::DONE === $job_contract->getState()) {
			Event::trigger(new AfterJobFinished($job_contract));
		}
	}

	/**
	 * Run a job asynchronously.
	 *
	 * @param \OZONE\Core\Queue\Interfaces\WorkerInterface      $worker
	 * @param \OZONE\Core\Queue\Interfaces\JobContractInterface $job_contract
	 */
	private static function workAsync(
		WorkerInterface $worker,
		JobContractInterface $job_contract,
	): void {
		// TODO: instead we could use a background process to launch only this job
		$worker->work($job_contract);
	}
}
