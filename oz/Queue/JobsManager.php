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
use OZONE\Core\Queue\Hooks\JobBeforeStart;
use OZONE\Core\Queue\Hooks\JobFinished;
use OZONE\Core\Queue\Interfaces\JobContractInterface;
use OZONE\Core\Queue\Interfaces\JobStoreInterface;
use OZONE\Core\Queue\Interfaces\WorkerInterface;
use OZONE\Core\Utils\JSONResult;
use Symfony\Component\Process\Process;
use Throwable;

/**
 * Class JobsManager.
 *
 * Static execution engine for the job queue.
 *
 * Maintains two global registries: worker classes (keyed by worker name) and job stores
 * (keyed by store name). {@link run()} iterates PENDING jobs from one or more stores,
 * resolves the correct worker via {@link WorkerInterface::fromPayload()}, calls
 * {@link WorkerInterface::work()}, then persists the result, error, and final state on
 * the {@link JobContractInterface}.
 *
 * Fires {@link JobBeforeStart} before a job begins and {@link JobFinished} after a job
 * completes successfully.
 */
final class JobsManager
{
	/**
	 * @var JobStoreInterface[]
	 */
	private static array $stores = [];

	/** @var array<string, class-string<WorkerInterface>> */
	private static array $workers = [];

	/**
	 * Register a worker.
	 *
	 * @param class-string<WorkerInterface> $worker_class
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
	 * @return array<string, class-string<WorkerInterface>>
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
	 * @return class-string<WorkerInterface>
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
	 * @param JobStoreInterface $store
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
	 * @return array<string, JobStoreInterface>
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
	 * @return JobStoreInterface
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
			if (null !== $store_name && $store->getName() !== $store_name) {
				continue;
			}

			foreach ($store->iterator($queue->getName(), $worker_name, JobState::PENDING, $priority) as $job) {
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

	/**
	 * Run a job.
	 *
	 * @param JobContractInterface $job_contract
	 *
	 * @return bool
	 */
	public static function runJob(JobContractInterface $job_contract): bool
	{
		if ($job_contract->lock()) {
			(new JobBeforeStart($job_contract))->dispatch();

			$job_contract->setState(JobState::RUNNING);
			$job_contract->save();

			try {
				/** @var WorkerInterface $worker_class */
				$worker_class = self::getWorker($job_contract->getWorker());
				$worker       = $worker_class::fromPayload($job_contract->getPayload());

				if ($worker->isAsync()) {
					// Do NOT increment try count or set startedAt here.
					// The job is reset to PENDING and re-acquired by a background
					// subprocess that calls runJob() again -- that call is the one
					// responsible for counting the attempt and recording the start time.
					self::workAsync($job_contract);
				} else {
					// Count the attempt and record when work actually begins.
					$job_contract->incrementTryCount();
					$job_contract->setStartedAt((float) \microtime(true));
					$job_contract->save();

					$worker->work($job_contract);
					$result = $worker->getResult();
					$job_contract->setResult($result);

					if ($result->isError()) {
						$job_contract->setState(JobState::FAILED);
					} else {
						$job_contract->setState(JobState::DONE);
					}

					self::finish($job_contract);
				}
			} catch (Throwable $t) {
				$job_contract->setState(JobState::FAILED);

				$result = isset($worker) ? $worker->getResult() : new JSONResult();

				$result->setError($t->getMessage())
					->setData(BaseException::throwableDescribe($t, true));

				$job_contract->setResult($result);
				self::finish($job_contract);
			}

			return true;
		}

		return false;
	}

	/**
	 * Finish a job.
	 *
	 * If the job failed but still has retries remaining, the state is reset to
	 * {@link JobState::PENDING} so the next {@link run()} call picks it up again.
	 *
	 * @param JobContractInterface $job_contract
	 */
	public static function finish(
		JobContractInterface $job_contract
	): void {
		$job_contract->setEndedAt((float) \microtime(true));

		if (
			JobState::FAILED === $job_contract->getState()
			&& $job_contract->getTryCount() < $job_contract->getRetryMax()
		) {
			// Reset to PENDING so the job is retried on the next run.
			$job_contract->setState(JobState::PENDING);
		}

		$job_contract->save();
		$job_contract->unlock();

		if (JobState::DONE === $job_contract->getState()) {
			(new JobFinished($job_contract))->dispatch();
		}
	}

	/**
	 * Continue execution of an async-dispatched job.
	 *
	 * Called by the CLI subprocess after receiving --job and --force. The job is assumed
	 * to be locked and in RUNNING state from the dispatching process. This method
	 * bypasses the lock() check -- the subprocess is the designated handler and is
	 * responsible for the final unlock via finish().
	 *
	 * @param JobContractInterface $job_contract
	 */
	public static function forceRunJob(JobContractInterface $job_contract): void
	{
		// Count the attempt and record when work actually begins.
		$job_contract->incrementTryCount();
		$job_contract->setStartedAt((float) \microtime(true));
		$job_contract->save();

		try {
			/** @var WorkerInterface $worker_class */
			$worker_class = self::getWorker($job_contract->getWorker());
			$worker       = $worker_class::fromPayload($job_contract->getPayload());

			$worker->work($job_contract);
			$result = $worker->getResult();
			$job_contract->setResult($result);

			if ($result->isError()) {
				$job_contract->setState(JobState::FAILED);
			} else {
				$job_contract->setState(JobState::DONE);
			}

			self::finish($job_contract);
		} catch (Throwable $t) {
			$job_contract->setState(JobState::FAILED);

			$result = isset($worker) ? $worker->getResult() : new JSONResult();

			$result->setError($t->getMessage())
				->setData(BaseException::throwableDescribe($t, true));

			$job_contract->setResult($result);
			self::finish($job_contract);
		}
	}

	/**
	 * Hand off a locked job to a background subprocess.
	 *
	 * The lock is kept held. The subprocess receives the job ref via --job
	 * and calls forceRunJob(), which owns the job from that point and releases
	 * the lock via finish(). If Process::start() throws, the exception propagates
	 * to runJob()'s catch block, which calls finish() -> unlock() exactly once.
	 *
	 * @param JobContractInterface $job_contract
	 */
	private static function workAsync(
		JobContractInterface $job_contract,
	): void {
		// Keep the lock held -- the subprocess takes over via forceRunJob().
		$bin = OZ_PROJECT_DIR . 'bin' . \DIRECTORY_SEPARATOR . 'oz';
		$cmd = [
			\PHP_BINARY,
			$bin,
			'jobs',
			'run',
			'--store=' . $job_contract->getStore()->getName(),
			'--job=' . $job_contract->getRef(),
			'--force',
		];

		(new Process($cmd, OZ_PROJECT_DIR, null, null, 0))->start();
	}
}
