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
	public function getName(): string
	{
		return self::NAME;
	}

	/**
	 * {@inheritDoc}
	 */
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
	 * @return JobStoreInterface
	 *
	 * @throws CRUDException
	 * @throws GoblException
	 * @throws ORMException
	 */
	public function update(JobContractInterface $job_contract): JobStoreInterface
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
	public function delete(JobContractInterface $job_contract): JobStoreInterface
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
	public function iterator(
		string $queue_name,
		?string $worker_name = null,
		?JobState $state = null,
		?int $priority = null
	): Generator {
		$qb = new OZJobsQuery();

		empty($queue_name) || $qb->whereQueueIs($queue_name);
		empty($worker_name) || $qb->whereWorkerIs($worker_name);
		null === $state || $qb->whereStateIs($state);
		null === $priority || $qb->wherePriorityIs($priority);

		$result = $qb->find();

		foreach ($result->lazy() as $oz_job) {
			yield $this->fromEntity($oz_job);
		}
	}

	/**
	 * {@inheritDoc}
	 */
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

		return \array_map(static fn (OZJob $oz_job) => $this->fromEntity($oz_job), $all);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @throws CRUDException
	 * @throws ORMException
	 * @throws ORMQueryException
	 */
	public function lock(JobContractInterface $job_contract): bool
	{
		$oz_job = self::identify($job_contract->getRef());

		if ($oz_job && !$oz_job->isLocked()) {
			$oz_job->setLocked(true)
				->save();

			return true;
		}

		return false;
	}

	/**
	 * {@inheritDoc}
	 */
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
			->setPayload($job->getPayload())
			->setResult($job->getResult())
			->setErrors($job->getErrors())
			->setStartedAt($job->getStartedAt())
			->setEndedAt($job->getEndedAt())
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
			$job = new JobContract($oz_job->getRef(), $oz_job->getWorker(), (array) $oz_job->getPayload(), $this);
		}

		return $job->setState($oz_job->getState())
			->setQueue($oz_job->getQueue())
			->setName($oz_job->getName())
			->setPriority($oz_job->getPriority())
			->setTryCount($oz_job->getTryCount())
			->setRetryMax($oz_job->getRetryMax())
			->setResult((array) $oz_job->getResult())
			->setErrors((array) $oz_job->getErrors())
			->setStartedAt((float) $oz_job->getStartedAt())
			->setEndedAt((float) $oz_job->getEndedAt())
			->setCreatedAt((int) $oz_job->getCreatedAt())
			->setUpdatedAt((int) $oz_job->getUpdatedAt());
	}
}
