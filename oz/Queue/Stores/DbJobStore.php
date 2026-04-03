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

namespace OZONE\Core\Queue\Stores;

use Generator;
use Gobl\CRUD\Exceptions\CRUDException;
use Gobl\Exceptions\GoblException;
use Gobl\ORM\Exceptions\ORMException;
use Gobl\ORM\Exceptions\ORMQueryException;
use InvalidArgumentException;
use Override;
use OZONE\Core\Db\OZJob;
use OZONE\Core\Db\OZJobsQuery;
use OZONE\Core\Queue\Interfaces\JobContractInterface;
use OZONE\Core\Queue\Interfaces\JobInterface;
use OZONE\Core\Queue\Interfaces\JobStoreInterface;
use OZONE\Core\Queue\JobContract;
use OZONE\Core\Queue\JobState;

/**
 * Class DbJobStore.
 */
class DbJobStore implements JobStoreInterface
{
	public const NAME = 'db';

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function getName(): string
	{
		return self::NAME;
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function get(string $ref): ?JobContractInterface
	{
		$oz_job = self::identify($ref);

		if ($oz_job) {
			return $this->fromEntity($oz_job);
		}

		return null;
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function getOrFail(string $ref): JobContractInterface
	{
		$job = $this->get($ref);

		if (!$job) {
			throw new InvalidArgumentException(\sprintf('Job with ref "%s" not found in store "%s".', $ref, self::NAME));
		}

		return $job;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param JobInterface $job
	 *
	 * @return JobContractInterface
	 *
	 * @throws CRUDException
	 * @throws GoblException
	 * @throws ORMException
	 */
	#[Override]
	public function add(JobInterface $job): JobContractInterface
	{
		$entity = $this->toEntity($job);

		$entity->save();

		return $this->fromEntity($entity);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param JobContractInterface $job_contract
	 *
	 * @throws CRUDException
	 * @throws GoblException
	 * @throws ORMException
	 */
	#[Override]
	public function update(JobContractInterface $job_contract): static
	{
		$qb = new OZJobsQuery();

		$entity = $qb->whereRefIs($job_contract->getRef())
			->find()
			->fetchClass();

		if ($entity) {
			$this->toEntity($job_contract, $entity)
				->save();
		} else {
			$this->add($job_contract);
		}

		return $this;
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function delete(JobContractInterface $job_contract): static
	{
		$qb = new OZJobsQuery();

		$qb->whereRefIs($job_contract->getRef())
			->delete()
			->execute();

		return $this;
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function iterator(
		string $queue_name,
		?string $worker_name = null,
		?JobState $state = null,
		?int $priority = null
	): Generator {
		$qb  = new OZJobsQuery();
		$now = \time();

		empty($queue_name) || $qb->whereQueueIs($queue_name);
		empty($worker_name) || $qb->whereWorkerIs($worker_name);
		null === $state || $qb->whereStateIs($state);
		null === $priority || $qb->wherePriorityIs($priority);

		$result = $qb->find();

		foreach ($result->lazy() as $oz_job) {
			// Skip jobs whose run_after window has not arrived yet.
			$run_after = $oz_job->getRunAfter();

			if (null !== $run_after && $run_after > $now) {
				continue;
			}

			yield $this->fromEntity($oz_job);
		}
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function list(
		?string $queue_name = null,
		?string $worker_name = null,
		?JobState $state = null,
		?int $priority = null,
		int $page = 1,
		int $max = 100
	): array {
		$qb = new OZJobsQuery();

		empty($queue_name) || $qb->whereQueueIs($queue_name);
		empty($worker_name) || $qb->whereWorkerIs($worker_name);
		null === $state || $qb->whereStateIs($state);
		null === $priority || $qb->wherePriorityIs($priority);

		$all = $qb->find($max, ($page - 1) * $max)
			->fetchAllClass();

		return \array_map($this->fromEntity(...), $all);
	}

	/**
	 * {@inheritDoc}
	 *
	 * Uses an atomic `UPDATE WHERE locked = false` so only one concurrent caller
	 * can acquire the lock - no separate read-then-write race condition.
	 *
	 * @throws CRUDException
	 * @throws ORMException
	 * @throws ORMQueryException
	 */
	#[Override]
	public function lock(JobContractInterface $job_contract): bool
	{
		$qb = new OZJobsQuery();

		$affected = $qb->whereRefIs($job_contract->getRef())
			->whereIsNotLocked()
			->update([
				OZJob::COL_LOCKED => true,
			])
			->execute();

		return $affected > 0;
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function unlock(JobContractInterface $job_contract): bool
	{
		$qb = new OZJobsQuery();

		return (bool) $qb->whereRefIs($job_contract->getRef())
			->update([
				OZJob::COL_LOCKED => false,
			])
			->execute();
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function isLocked(JobContractInterface $job_contract): bool
	{
		$oz_job = self::identify($job_contract->getRef());

		return $oz_job?->isLocked() ?? false;
	}

	/**
	 * {@inheritDoc}
	 *
	 * Executes a `SELECT COUNT(*)` query - no row data loaded.
	 */
	#[Override]
	public function count(string $queue_name, ?JobState $state = null): int
	{
		$qb = new OZJobsQuery();

		empty($queue_name) || $qb->whereQueueIs($queue_name);
		null === $state || $qb->whereStateIs($state);

		return $qb->find()->totalCount();
	}

	/**
	 * {@inheritDoc}
	 *
	 * Only deletes jobs in terminal states (DONE, FAILED, DEAD_LETTER, CANCELLED).
	 * Live jobs (PENDING, RUNNING) are never affected regardless of age.
	 */
	#[Override]
	public function prune(int $older_than_seconds, ?JobState $state = null, ?string $queue_name = null): int
	{
		$terminal = [JobState::DONE, JobState::FAILED, JobState::DEAD_LETTER, JobState::CANCELLED];
		$cutoff   = \time() - $older_than_seconds;

		$qb = new OZJobsQuery();

		if (null !== $state) {
			if (!\in_array($state, $terminal, true)) {
				return 0; // refuse to prune non-terminal states
			}

			$qb->whereStateIs($state);
		} else {
			$qb->whereStateIsIn(\array_map(static fn (JobState $s) => $s->value, $terminal));
		}

		empty($queue_name) || $qb->whereQueueIs($queue_name);

		$qb->whereCreatedAtIsLte((string) $cutoff);

		return $qb->delete()->execute();
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function countByBatch(string $batch_id, ?JobState $state = null): int
	{
		$qb = new OZJobsQuery();
		$qb->whereBatchIdIs($batch_id);
		null === $state || $qb->whereStateIs($state);

		return $qb->find()->totalCount();
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function listByBatch(string $batch_id): array
	{
		$qb = new OZJobsQuery();

		return \array_map(
			$this->fromEntity(...),
			$qb->whereBatchIdIs($batch_id)->find()->fetchAllClass()
		);
	}

	/**
	 * Find {@link \OZONE\Core\Db\OZJob} by ref.
	 *
	 * @param string $ref
	 *
	 * @return null|OZJob
	 */
	protected static function identify(string $ref): ?OZJob
	{
		$qb = new OZJobsQuery();

		return $qb->whereRefIs($ref)
			->find()
			->fetchClass();
	}

	/**
	 * Convert a {@link JobInterface} to {@link \OZONE\Core\Db\OZJob}.
	 *
	 * @param JobInterface $job
	 * @param null|OZJob   $oz_job
	 *
	 * @return OZJob
	 */
	protected function toEntity(JobInterface $job, ?OZJob $oz_job = null): OZJob
	{
		if (null === $oz_job) {
			$oz_job = new OZJob();
		}

		return $oz_job->setRef($job->getRef())
			->setState($job->getState())
			->setQueue($job->getQueue())
			->setName($job->getName())
			->setWorker($job->getWorker())
			->setPriority($job->getPriority())
			->setTryCount($job->getTryCount())
			->setRetryMax($job->getRetryMax())
			->setRetryDelay($job->getRetryDelay())
			->setPayload($job->getPayload())
			->setResult($job->getResult())
			->setStartedAt($job->getStartedAt())
			->setEndedAt($job->getEndedAt())
			->setRunAfter($job->getRunAfter())
			->setChain($job->getChain())
			->setBatchID($job->getBatchId())
			->setCreatedAt($job->getCreatedAt())
			->setUpdatedAt($job->getUpdatedAt());
	}

	/**
	 * Convert a {@link \OZONE\Core\Db\OZJob} to {@link JobInterface}.
	 *
	 * @param OZJob             $oz_job
	 * @param null|JobInterface $job
	 *
	 * @return JobContractInterface
	 */
	protected function fromEntity(OZJob $oz_job, ?JobInterface $job = null): JobContractInterface
	{
		if (null === $job) {
			$job = new JobContract($oz_job->getRef(), $oz_job->getWorker(), (array) $oz_job->getPayload()->getData(), $this);
		}

		return $job->setState($oz_job->getState())
			->setQueue($oz_job->getQueue())
			->setName($oz_job->getName())
			->setPriority($oz_job->getPriority())
			->setTryCount($oz_job->getTryCount())
			->setRetryMax($oz_job->getRetryMax())
			->setRetryDelay($oz_job->getRetryDelay())
			->setResult($oz_job->getResult())
			->setStartedAt(null !== $oz_job->getStartedAt() ? (float) $oz_job->getStartedAt() : null)
			->setEndedAt(null !== $oz_job->getEndedAt() ? (float) $oz_job->getEndedAt() : null)
			->setRunAfter($oz_job->getRunAfter())
			->setChain((array) $oz_job->getChain()->getData())
			->setBatchId($oz_job->getBatchID())
			->setCreatedAt((int) $oz_job->getCreatedAt())
			->setUpdatedAt((int) $oz_job->getUpdatedAt());
	}
}
