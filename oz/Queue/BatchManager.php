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

use Gobl\CRUD\Exceptions\CRUDException;
use Gobl\Exceptions\GoblException;
use Gobl\ORM\Exceptions\ORMException;
use OZONE\Core\App\Keys;
use OZONE\Core\Db\OZJobBatch;
use OZONE\Core\Db\OZJobBatchesQuery;
use OZONE\Core\Queue\Hooks\BatchFinished;
use OZONE\Core\Queue\Interfaces\JobContractInterface;
use OZONE\Core\Queue\Interfaces\WorkerInterface;

/**
 * Class BatchManager.
 *
 * Creates and monitors job batches that group related jobs dispatched together.
 * When every job in a batch reaches a terminal state, {@link BatchFinished}
 * is dispatched.
 *
 * ### Creating a batch
 *
 * ```php
 * $batch = BatchManager::create([
 *     new ProcessImageWorker('img-1'),
 *     new ProcessImageWorker('img-2'),
 * ], Queue::DEFAULT, 'resize-images');
 *
 * echo $batch->getID(); // batch ID for later queries
 * ```
 *
 * ### Listening for completion
 *
 * ```php
 * BatchFinished::listen(function (BatchFinished $e) {
 *     oz_logger()->info('Batch finished', ['id' => $e->batch->getID(), 'error' => $e->has_error]);
 * });
 * ```
 */
final class BatchManager
{
	/**
	 * Terminal states -- a job in one of these states is considered settled.
	 */
	public const TERMINAL_STATES = [
		JobState::DONE,
		JobState::FAILED,
		JobState::DEAD_LETTER,
		JobState::CANCELLED,
	];

	/**
	 * Create a batch of jobs and dispatch them all to the given queue.
	 *
	 * Each worker is pushed to `$queue_name` via a single store. All generated
	 * jobs receive the batch ref as their `batch_id`. If no workers are provided,
	 * the batch is created but immediately marked as finished (empty batches are
	 * trivially complete).
	 *
	 * @param WorkerInterface[] $workers    the workers to dispatch
	 * @param string            $queue_name the queue to add all jobs to
	 * @param null|string       $name       optional human-readable name for the batch
	 * @param string            $store_name the store to use (default: db)
	 *
	 * @return OZJobBatch the persisted batch entity
	 *
	 * @throws CRUDException
	 * @throws GoblException
	 * @throws ORMException
	 */
	public static function create(
		array $workers,
		string $queue_name = Queue::DEFAULT,
		?string $name = null,
		string $store_name = Queue::DEFAULT_STORE,
	): OZJobBatch {
		$batch = new OZJobBatch();

		if (null !== $name) {
			$batch->setName($name);
		}

		$batch->save();

		// Store the integer PK — jobs.batch_id is a FK to oz_job_batches.id.
		$batch_id = $batch->getID();
		$store    = JobsManager::getStore($store_name);

		foreach ($workers as $worker) {
			$ref = Keys::id64('job-ref');
			$job = (new Job($ref, $worker::getName(), $worker->getPayload()))
				->setQueue($queue_name)
				->setName($worker::getName())
				->setBatchId($batch_id);

			$store->add($job);
		}

		// Empty batch -- immediately mark finished.
		if (empty($workers)) {
			$batch->setFinishedAT((string) \time());
			$batch->save();

			(new BatchFinished($batch, false))->dispatch();
		}

		return $batch;
	}

	/**
	 * Get batch progress counts.
	 *
	 * Returns an associative array with one entry per {@link JobState} name
	 * plus a `total` key. Non-terminal states reflect live job counts.
	 *
	 * @param string $batch_id the batch ID (oz_job_batches.id)
	 *
	 * @return array{total: int, pending: int, running: int, done: int, failed: int, cancelled: int, dead_letter: int}
	 */
	public static function progress(string $batch_id): array
	{
		$empty = [
			'total'       => 0,
			'pending'     => 0,
			'running'     => 0,
			'done'        => 0,
			'failed'      => 0,
			'cancelled'   => 0,
			'dead_letter' => 0,
		];

		$bq    = new OZJobBatchesQuery();
		$batch = $bq->whereIdIs($batch_id)->find()->fetchClass();

		if (null === $batch) {
			return $empty;
		}

		$jobs = [];

		// Collect from every registered store -- batch jobs may live in any store.
		foreach (JobsManager::getStores() as $store) {
			foreach ($store->listByBatch($batch_id) as $job) {
				$jobs[] = $job;
			}
		}

		$progress          = $empty;
		$progress['total'] = \count($jobs);

		foreach ($jobs as $job) {
			switch ($job->getState()) {
				case JobState::PENDING:
					++$progress['pending'];

					break;

				case JobState::RUNNING:
					++$progress['running'];

					break;

				case JobState::DONE:
					++$progress['done'];

					break;

				case JobState::FAILED:
					++$progress['failed'];

					break;

				case JobState::CANCELLED:
					++$progress['cancelled'];

					break;

				case JobState::DEAD_LETTER:
					++$progress['dead_letter'];

					break;
			}
		}

		return $progress;
	}

	/**
	 * Returns true when the batch has been marked finished.
	 *
	 * This reflects the persisted `batch_finished_at` timestamp. The batch is
	 * marked finished by {@link onJobSettled()} once all jobs have settled.
	 *
	 * @param string $batch_id the batch ID (oz_job_batches.id)
	 *
	 * @return bool
	 */
	public static function isFinished(string $batch_id): bool
	{
		$qb    = new OZJobBatchesQuery();
		$batch = $qb->whereIdIs($batch_id)->find()->fetchClass();

		return null !== $batch && null !== $batch->getFinishedAT();
	}

	/**
	 * Called by {@link JobsManager} after a job belonging to a batch settles.
	 *
	 * Checks whether all jobs in the batch have reached a terminal state. If
	 * so, marks the batch finished and fires {@link BatchFinished}.
	 *
	 * @param JobContractInterface $job_contract the settled job
	 * @param string               $batch_id     the oz_job_batches.id FK value stored on the job
	 *
	 * @internal only called from JobsManager::finish()
	 */
	public static function onJobSettled(JobContractInterface $job_contract, string $batch_id): void
	{
		// Use the store that owns the settled job -- avoids any hardcoded DB dependency.
		$store = $job_contract->getStore();
		$total = $store->countByBatch($batch_id);

		// Sum settled jobs across all terminal states.
		$settled = 0;

		foreach (self::TERMINAL_STATES as $terminal_state) {
			$settled += $store->countByBatch($batch_id, $terminal_state);
		}

		if ($settled < $total) {
			return; // batch still in progress
		}

		// All jobs have settled -- mark the batch finished.
		$bq    = new OZJobBatchesQuery();
		$batch = $bq->whereIdIs($batch_id)->find()->fetchClass();

		if (null === $batch || null !== $batch->getFinishedAT()) {
			return; // already finished or batch not found
		}

		$batch->setFinishedAT((string) \time());
		$batch->save();

		// Determine if any job ended in a non-DONE terminal state.
		$error_states = [JobState::FAILED, JobState::DEAD_LETTER, JobState::CANCELLED];
		$has_error    = false;

		foreach ($error_states as $error_state) {
			if ($store->countByBatch($batch_id, $error_state) > 0) {
				$has_error = true;

				break;
			}
		}

		(new BatchFinished($batch, $has_error))->dispatch();
	}
}
