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

namespace OZONE\Core\Db\Base;

use Gobl\DBAL\Operator;

/**
 * Class OZJobsQuery.
 *
 * @method \OZONE\Core\Db\OZJobsResults find(?int $max = null, int $offset = 0, array $order_by = [])
 */
abstract class OZJobsQuery extends \Gobl\ORM\ORMTableQuery
{
	/**
	 * OZJobsQuery constructor.
	 */
	public function __construct()
	{
		parent::__construct(
			\OZONE\Core\Db\OZJob::TABLE_NAMESPACE,
			\OZONE\Core\Db\OZJob::TABLE_NAME
		);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @return static
	 */
	public static function createInstance(): static
	{
		return new \OZONE\Core\Db\OZJobsQuery();
	}

	/**
	 * {@inheritDoc}
	 */
	public function subGroup(): static
	{
		$instance              = new static();
		$instance->qb          = $this->qb;
		$instance->filters     = $this->filters->subGroup();
		$instance->table_alias = $this->table_alias;

		return $instance;
	}

	/**
	 * Filters rows with `eq` condition on column `oz_jobs`.`id`.
	 *
	 * @param int|string $value the filter value
	 *
	 * @return static
	 */
	public function whereIdIs(string|int $value): self
	{
		return $this->filterBy(
			Operator::from('eq'),
			\OZONE\Core\Db\OZJob::COL_ID,
			$value
		);
	}

	/**
	 * Filters rows with `neq` condition on column `oz_jobs`.`id`.
	 *
	 * @param int|string $value the filter value
	 *
	 * @return static
	 */
	public function whereIdIsNot(string|int $value): self
	{
		return $this->filterBy(
			Operator::from('neq'),
			\OZONE\Core\Db\OZJob::COL_ID,
			$value
		);
	}

	/**
	 * Filters rows with `lt` condition on column `oz_jobs`.`id`.
	 *
	 * @param int|string $value the filter value
	 *
	 * @return static
	 */
	public function whereIdIsLt(string|int $value): self
	{
		return $this->filterBy(
			Operator::from('lt'),
			\OZONE\Core\Db\OZJob::COL_ID,
			$value
		);
	}

	/**
	 * Filters rows with `lte` condition on column `oz_jobs`.`id`.
	 *
	 * @param int|string $value the filter value
	 *
	 * @return static
	 */
	public function whereIdIsLte(string|int $value): self
	{
		return $this->filterBy(
			Operator::from('lte'),
			\OZONE\Core\Db\OZJob::COL_ID,
			$value
		);
	}

	/**
	 * Filters rows with `gt` condition on column `oz_jobs`.`id`.
	 *
	 * @param int|string $value the filter value
	 *
	 * @return static
	 */
	public function whereIdIsGt(string|int $value): self
	{
		return $this->filterBy(
			Operator::from('gt'),
			\OZONE\Core\Db\OZJob::COL_ID,
			$value
		);
	}

	/**
	 * Filters rows with `gte` condition on column `oz_jobs`.`id`.
	 *
	 * @param int|string $value the filter value
	 *
	 * @return static
	 */
	public function whereIdIsGte(string|int $value): self
	{
		return $this->filterBy(
			Operator::from('gte'),
			\OZONE\Core\Db\OZJob::COL_ID,
			$value
		);
	}

	/**
	 * Filters rows with `like` condition on column `oz_jobs`.`id`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 */
	public function whereIdIsLike(string $value): self
	{
		return $this->filterBy(
			Operator::from('like'),
			\OZONE\Core\Db\OZJob::COL_ID,
			$value
		);
	}

	/**
	 * Filters rows with `not_like` condition on column `oz_jobs`.`id`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 */
	public function whereIdIsNotLike(string $value): self
	{
		return $this->filterBy(
			Operator::from('not_like'),
			\OZONE\Core\Db\OZJob::COL_ID,
			$value
		);
	}

	/**
	 * Filters rows with `in` condition on column `oz_jobs`.`id`.
	 *
	 * @param array $value the filter value
	 *
	 * @return static
	 */
	public function whereIdIsIn(array $value): self
	{
		return $this->filterBy(
			Operator::from('in'),
			\OZONE\Core\Db\OZJob::COL_ID,
			$value
		);
	}

	/**
	 * Filters rows with `not_in` condition on column `oz_jobs`.`id`.
	 *
	 * @param array $value the filter value
	 *
	 * @return static
	 */
	public function whereIdIsNotIn(array $value): self
	{
		return $this->filterBy(
			Operator::from('not_in'),
			\OZONE\Core\Db\OZJob::COL_ID,
			$value
		);
	}

	/**
	 * Filters rows with `eq` condition on column `oz_jobs`.`ref`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 */
	public function whereRefIs(string $value): self
	{
		return $this->filterBy(
			Operator::from('eq'),
			\OZONE\Core\Db\OZJob::COL_REF,
			$value
		);
	}

	/**
	 * Filters rows with `neq` condition on column `oz_jobs`.`ref`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 */
	public function whereRefIsNot(string $value): self
	{
		return $this->filterBy(
			Operator::from('neq'),
			\OZONE\Core\Db\OZJob::COL_REF,
			$value
		);
	}

	/**
	 * Filters rows with `lt` condition on column `oz_jobs`.`ref`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 */
	public function whereRefIsLt(string $value): self
	{
		return $this->filterBy(
			Operator::from('lt'),
			\OZONE\Core\Db\OZJob::COL_REF,
			$value
		);
	}

	/**
	 * Filters rows with `lte` condition on column `oz_jobs`.`ref`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 */
	public function whereRefIsLte(string $value): self
	{
		return $this->filterBy(
			Operator::from('lte'),
			\OZONE\Core\Db\OZJob::COL_REF,
			$value
		);
	}

	/**
	 * Filters rows with `gt` condition on column `oz_jobs`.`ref`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 */
	public function whereRefIsGt(string $value): self
	{
		return $this->filterBy(
			Operator::from('gt'),
			\OZONE\Core\Db\OZJob::COL_REF,
			$value
		);
	}

	/**
	 * Filters rows with `gte` condition on column `oz_jobs`.`ref`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 */
	public function whereRefIsGte(string $value): self
	{
		return $this->filterBy(
			Operator::from('gte'),
			\OZONE\Core\Db\OZJob::COL_REF,
			$value
		);
	}

	/**
	 * Filters rows with `like` condition on column `oz_jobs`.`ref`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 */
	public function whereRefIsLike(string $value): self
	{
		return $this->filterBy(
			Operator::from('like'),
			\OZONE\Core\Db\OZJob::COL_REF,
			$value
		);
	}

	/**
	 * Filters rows with `not_like` condition on column `oz_jobs`.`ref`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 */
	public function whereRefIsNotLike(string $value): self
	{
		return $this->filterBy(
			Operator::from('not_like'),
			\OZONE\Core\Db\OZJob::COL_REF,
			$value
		);
	}

	/**
	 * Filters rows with `in` condition on column `oz_jobs`.`ref`.
	 *
	 * @param array $value the filter value
	 *
	 * @return static
	 */
	public function whereRefIsIn(array $value): self
	{
		return $this->filterBy(
			Operator::from('in'),
			\OZONE\Core\Db\OZJob::COL_REF,
			$value
		);
	}

	/**
	 * Filters rows with `not_in` condition on column `oz_jobs`.`ref`.
	 *
	 * @param array $value the filter value
	 *
	 * @return static
	 */
	public function whereRefIsNotIn(array $value): self
	{
		return $this->filterBy(
			Operator::from('not_in'),
			\OZONE\Core\Db\OZJob::COL_REF,
			$value
		);
	}

	/**
	 * Filters rows with `eq` condition on column `oz_jobs`.`state`.
	 *
	 * @param \OZONE\Core\Queue\JobState|string $value the filter value
	 *
	 * @return static
	 */
	public function whereStateIs(\OZONE\Core\Queue\JobState|string $value): self
	{
		return $this->filterBy(
			Operator::from('eq'),
			\OZONE\Core\Db\OZJob::COL_STATE,
			$value
		);
	}

	/**
	 * Filters rows with `neq` condition on column `oz_jobs`.`state`.
	 *
	 * @param \OZONE\Core\Queue\JobState|string $value the filter value
	 *
	 * @return static
	 */
	public function whereStateIsNot(\OZONE\Core\Queue\JobState|string $value): self
	{
		return $this->filterBy(
			Operator::from('neq'),
			\OZONE\Core\Db\OZJob::COL_STATE,
			$value
		);
	}

	/**
	 * Filters rows with `lt` condition on column `oz_jobs`.`state`.
	 *
	 * @param \OZONE\Core\Queue\JobState|string $value the filter value
	 *
	 * @return static
	 */
	public function whereStateIsLt(\OZONE\Core\Queue\JobState|string $value): self
	{
		return $this->filterBy(
			Operator::from('lt'),
			\OZONE\Core\Db\OZJob::COL_STATE,
			$value
		);
	}

	/**
	 * Filters rows with `lte` condition on column `oz_jobs`.`state`.
	 *
	 * @param \OZONE\Core\Queue\JobState|string $value the filter value
	 *
	 * @return static
	 */
	public function whereStateIsLte(\OZONE\Core\Queue\JobState|string $value): self
	{
		return $this->filterBy(
			Operator::from('lte'),
			\OZONE\Core\Db\OZJob::COL_STATE,
			$value
		);
	}

	/**
	 * Filters rows with `gt` condition on column `oz_jobs`.`state`.
	 *
	 * @param \OZONE\Core\Queue\JobState|string $value the filter value
	 *
	 * @return static
	 */
	public function whereStateIsGt(\OZONE\Core\Queue\JobState|string $value): self
	{
		return $this->filterBy(
			Operator::from('gt'),
			\OZONE\Core\Db\OZJob::COL_STATE,
			$value
		);
	}

	/**
	 * Filters rows with `gte` condition on column `oz_jobs`.`state`.
	 *
	 * @param \OZONE\Core\Queue\JobState|string $value the filter value
	 *
	 * @return static
	 */
	public function whereStateIsGte(\OZONE\Core\Queue\JobState|string $value): self
	{
		return $this->filterBy(
			Operator::from('gte'),
			\OZONE\Core\Db\OZJob::COL_STATE,
			$value
		);
	}

	/**
	 * Filters rows with `like` condition on column `oz_jobs`.`state`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 */
	public function whereStateIsLike(string $value): self
	{
		return $this->filterBy(
			Operator::from('like'),
			\OZONE\Core\Db\OZJob::COL_STATE,
			$value
		);
	}

	/**
	 * Filters rows with `not_like` condition on column `oz_jobs`.`state`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 */
	public function whereStateIsNotLike(string $value): self
	{
		return $this->filterBy(
			Operator::from('not_like'),
			\OZONE\Core\Db\OZJob::COL_STATE,
			$value
		);
	}

	/**
	 * Filters rows with `in` condition on column `oz_jobs`.`state`.
	 *
	 * @param array $value the filter value
	 *
	 * @return static
	 */
	public function whereStateIsIn(array $value): self
	{
		return $this->filterBy(
			Operator::from('in'),
			\OZONE\Core\Db\OZJob::COL_STATE,
			$value
		);
	}

	/**
	 * Filters rows with `not_in` condition on column `oz_jobs`.`state`.
	 *
	 * @param array $value the filter value
	 *
	 * @return static
	 */
	public function whereStateIsNotIn(array $value): self
	{
		return $this->filterBy(
			Operator::from('not_in'),
			\OZONE\Core\Db\OZJob::COL_STATE,
			$value
		);
	}

	/**
	 * Filters rows with `eq` condition on column `oz_jobs`.`queue`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 */
	public function whereQueueIs(string $value): self
	{
		return $this->filterBy(
			Operator::from('eq'),
			\OZONE\Core\Db\OZJob::COL_QUEUE,
			$value
		);
	}

	/**
	 * Filters rows with `neq` condition on column `oz_jobs`.`queue`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 */
	public function whereQueueIsNot(string $value): self
	{
		return $this->filterBy(
			Operator::from('neq'),
			\OZONE\Core\Db\OZJob::COL_QUEUE,
			$value
		);
	}

	/**
	 * Filters rows with `lt` condition on column `oz_jobs`.`queue`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 */
	public function whereQueueIsLt(string $value): self
	{
		return $this->filterBy(
			Operator::from('lt'),
			\OZONE\Core\Db\OZJob::COL_QUEUE,
			$value
		);
	}

	/**
	 * Filters rows with `lte` condition on column `oz_jobs`.`queue`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 */
	public function whereQueueIsLte(string $value): self
	{
		return $this->filterBy(
			Operator::from('lte'),
			\OZONE\Core\Db\OZJob::COL_QUEUE,
			$value
		);
	}

	/**
	 * Filters rows with `gt` condition on column `oz_jobs`.`queue`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 */
	public function whereQueueIsGt(string $value): self
	{
		return $this->filterBy(
			Operator::from('gt'),
			\OZONE\Core\Db\OZJob::COL_QUEUE,
			$value
		);
	}

	/**
	 * Filters rows with `gte` condition on column `oz_jobs`.`queue`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 */
	public function whereQueueIsGte(string $value): self
	{
		return $this->filterBy(
			Operator::from('gte'),
			\OZONE\Core\Db\OZJob::COL_QUEUE,
			$value
		);
	}

	/**
	 * Filters rows with `like` condition on column `oz_jobs`.`queue`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 */
	public function whereQueueIsLike(string $value): self
	{
		return $this->filterBy(
			Operator::from('like'),
			\OZONE\Core\Db\OZJob::COL_QUEUE,
			$value
		);
	}

	/**
	 * Filters rows with `not_like` condition on column `oz_jobs`.`queue`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 */
	public function whereQueueIsNotLike(string $value): self
	{
		return $this->filterBy(
			Operator::from('not_like'),
			\OZONE\Core\Db\OZJob::COL_QUEUE,
			$value
		);
	}

	/**
	 * Filters rows with `in` condition on column `oz_jobs`.`queue`.
	 *
	 * @param array $value the filter value
	 *
	 * @return static
	 */
	public function whereQueueIsIn(array $value): self
	{
		return $this->filterBy(
			Operator::from('in'),
			\OZONE\Core\Db\OZJob::COL_QUEUE,
			$value
		);
	}

	/**
	 * Filters rows with `not_in` condition on column `oz_jobs`.`queue`.
	 *
	 * @param array $value the filter value
	 *
	 * @return static
	 */
	public function whereQueueIsNotIn(array $value): self
	{
		return $this->filterBy(
			Operator::from('not_in'),
			\OZONE\Core\Db\OZJob::COL_QUEUE,
			$value
		);
	}

	/**
	 * Filters rows with `eq` condition on column `oz_jobs`.`name`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 */
	public function whereNameIs(string $value): self
	{
		return $this->filterBy(
			Operator::from('eq'),
			\OZONE\Core\Db\OZJob::COL_NAME,
			$value
		);
	}

	/**
	 * Filters rows with `neq` condition on column `oz_jobs`.`name`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 */
	public function whereNameIsNot(string $value): self
	{
		return $this->filterBy(
			Operator::from('neq'),
			\OZONE\Core\Db\OZJob::COL_NAME,
			$value
		);
	}

	/**
	 * Filters rows with `lt` condition on column `oz_jobs`.`name`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 */
	public function whereNameIsLt(string $value): self
	{
		return $this->filterBy(
			Operator::from('lt'),
			\OZONE\Core\Db\OZJob::COL_NAME,
			$value
		);
	}

	/**
	 * Filters rows with `lte` condition on column `oz_jobs`.`name`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 */
	public function whereNameIsLte(string $value): self
	{
		return $this->filterBy(
			Operator::from('lte'),
			\OZONE\Core\Db\OZJob::COL_NAME,
			$value
		);
	}

	/**
	 * Filters rows with `gt` condition on column `oz_jobs`.`name`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 */
	public function whereNameIsGt(string $value): self
	{
		return $this->filterBy(
			Operator::from('gt'),
			\OZONE\Core\Db\OZJob::COL_NAME,
			$value
		);
	}

	/**
	 * Filters rows with `gte` condition on column `oz_jobs`.`name`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 */
	public function whereNameIsGte(string $value): self
	{
		return $this->filterBy(
			Operator::from('gte'),
			\OZONE\Core\Db\OZJob::COL_NAME,
			$value
		);
	}

	/**
	 * Filters rows with `like` condition on column `oz_jobs`.`name`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 */
	public function whereNameIsLike(string $value): self
	{
		return $this->filterBy(
			Operator::from('like'),
			\OZONE\Core\Db\OZJob::COL_NAME,
			$value
		);
	}

	/**
	 * Filters rows with `not_like` condition on column `oz_jobs`.`name`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 */
	public function whereNameIsNotLike(string $value): self
	{
		return $this->filterBy(
			Operator::from('not_like'),
			\OZONE\Core\Db\OZJob::COL_NAME,
			$value
		);
	}

	/**
	 * Filters rows with `in` condition on column `oz_jobs`.`name`.
	 *
	 * @param array $value the filter value
	 *
	 * @return static
	 */
	public function whereNameIsIn(array $value): self
	{
		return $this->filterBy(
			Operator::from('in'),
			\OZONE\Core\Db\OZJob::COL_NAME,
			$value
		);
	}

	/**
	 * Filters rows with `not_in` condition on column `oz_jobs`.`name`.
	 *
	 * @param array $value the filter value
	 *
	 * @return static
	 */
	public function whereNameIsNotIn(array $value): self
	{
		return $this->filterBy(
			Operator::from('not_in'),
			\OZONE\Core\Db\OZJob::COL_NAME,
			$value
		);
	}

	/**
	 * Filters rows with `eq` condition on column `oz_jobs`.`worker`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 */
	public function whereWorkerIs(string $value): self
	{
		return $this->filterBy(
			Operator::from('eq'),
			\OZONE\Core\Db\OZJob::COL_WORKER,
			$value
		);
	}

	/**
	 * Filters rows with `neq` condition on column `oz_jobs`.`worker`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 */
	public function whereWorkerIsNot(string $value): self
	{
		return $this->filterBy(
			Operator::from('neq'),
			\OZONE\Core\Db\OZJob::COL_WORKER,
			$value
		);
	}

	/**
	 * Filters rows with `lt` condition on column `oz_jobs`.`worker`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 */
	public function whereWorkerIsLt(string $value): self
	{
		return $this->filterBy(
			Operator::from('lt'),
			\OZONE\Core\Db\OZJob::COL_WORKER,
			$value
		);
	}

	/**
	 * Filters rows with `lte` condition on column `oz_jobs`.`worker`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 */
	public function whereWorkerIsLte(string $value): self
	{
		return $this->filterBy(
			Operator::from('lte'),
			\OZONE\Core\Db\OZJob::COL_WORKER,
			$value
		);
	}

	/**
	 * Filters rows with `gt` condition on column `oz_jobs`.`worker`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 */
	public function whereWorkerIsGt(string $value): self
	{
		return $this->filterBy(
			Operator::from('gt'),
			\OZONE\Core\Db\OZJob::COL_WORKER,
			$value
		);
	}

	/**
	 * Filters rows with `gte` condition on column `oz_jobs`.`worker`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 */
	public function whereWorkerIsGte(string $value): self
	{
		return $this->filterBy(
			Operator::from('gte'),
			\OZONE\Core\Db\OZJob::COL_WORKER,
			$value
		);
	}

	/**
	 * Filters rows with `like` condition on column `oz_jobs`.`worker`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 */
	public function whereWorkerIsLike(string $value): self
	{
		return $this->filterBy(
			Operator::from('like'),
			\OZONE\Core\Db\OZJob::COL_WORKER,
			$value
		);
	}

	/**
	 * Filters rows with `not_like` condition on column `oz_jobs`.`worker`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 */
	public function whereWorkerIsNotLike(string $value): self
	{
		return $this->filterBy(
			Operator::from('not_like'),
			\OZONE\Core\Db\OZJob::COL_WORKER,
			$value
		);
	}

	/**
	 * Filters rows with `in` condition on column `oz_jobs`.`worker`.
	 *
	 * @param array $value the filter value
	 *
	 * @return static
	 */
	public function whereWorkerIsIn(array $value): self
	{
		return $this->filterBy(
			Operator::from('in'),
			\OZONE\Core\Db\OZJob::COL_WORKER,
			$value
		);
	}

	/**
	 * Filters rows with `not_in` condition on column `oz_jobs`.`worker`.
	 *
	 * @param array $value the filter value
	 *
	 * @return static
	 */
	public function whereWorkerIsNotIn(array $value): self
	{
		return $this->filterBy(
			Operator::from('not_in'),
			\OZONE\Core\Db\OZJob::COL_WORKER,
			$value
		);
	}

	/**
	 * Filters rows with `eq` condition on column `oz_jobs`.`priority`.
	 *
	 * @param int $value the filter value
	 *
	 * @return static
	 */
	public function wherePriorityIs(int $value): self
	{
		return $this->filterBy(
			Operator::from('eq'),
			\OZONE\Core\Db\OZJob::COL_PRIORITY,
			$value
		);
	}

	/**
	 * Filters rows with `neq` condition on column `oz_jobs`.`priority`.
	 *
	 * @param int $value the filter value
	 *
	 * @return static
	 */
	public function wherePriorityIsNot(int $value): self
	{
		return $this->filterBy(
			Operator::from('neq'),
			\OZONE\Core\Db\OZJob::COL_PRIORITY,
			$value
		);
	}

	/**
	 * Filters rows with `lt` condition on column `oz_jobs`.`priority`.
	 *
	 * @param int $value the filter value
	 *
	 * @return static
	 */
	public function wherePriorityIsLt(int $value): self
	{
		return $this->filterBy(
			Operator::from('lt'),
			\OZONE\Core\Db\OZJob::COL_PRIORITY,
			$value
		);
	}

	/**
	 * Filters rows with `lte` condition on column `oz_jobs`.`priority`.
	 *
	 * @param int $value the filter value
	 *
	 * @return static
	 */
	public function wherePriorityIsLte(int $value): self
	{
		return $this->filterBy(
			Operator::from('lte'),
			\OZONE\Core\Db\OZJob::COL_PRIORITY,
			$value
		);
	}

	/**
	 * Filters rows with `gt` condition on column `oz_jobs`.`priority`.
	 *
	 * @param int $value the filter value
	 *
	 * @return static
	 */
	public function wherePriorityIsGt(int $value): self
	{
		return $this->filterBy(
			Operator::from('gt'),
			\OZONE\Core\Db\OZJob::COL_PRIORITY,
			$value
		);
	}

	/**
	 * Filters rows with `gte` condition on column `oz_jobs`.`priority`.
	 *
	 * @param int $value the filter value
	 *
	 * @return static
	 */
	public function wherePriorityIsGte(int $value): self
	{
		return $this->filterBy(
			Operator::from('gte'),
			\OZONE\Core\Db\OZJob::COL_PRIORITY,
			$value
		);
	}

	/**
	 * Filters rows with `like` condition on column `oz_jobs`.`priority`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 */
	public function wherePriorityIsLike(string $value): self
	{
		return $this->filterBy(
			Operator::from('like'),
			\OZONE\Core\Db\OZJob::COL_PRIORITY,
			$value
		);
	}

	/**
	 * Filters rows with `not_like` condition on column `oz_jobs`.`priority`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 */
	public function wherePriorityIsNotLike(string $value): self
	{
		return $this->filterBy(
			Operator::from('not_like'),
			\OZONE\Core\Db\OZJob::COL_PRIORITY,
			$value
		);
	}

	/**
	 * Filters rows with `in` condition on column `oz_jobs`.`priority`.
	 *
	 * @param array $value the filter value
	 *
	 * @return static
	 */
	public function wherePriorityIsIn(array $value): self
	{
		return $this->filterBy(
			Operator::from('in'),
			\OZONE\Core\Db\OZJob::COL_PRIORITY,
			$value
		);
	}

	/**
	 * Filters rows with `not_in` condition on column `oz_jobs`.`priority`.
	 *
	 * @param array $value the filter value
	 *
	 * @return static
	 */
	public function wherePriorityIsNotIn(array $value): self
	{
		return $this->filterBy(
			Operator::from('not_in'),
			\OZONE\Core\Db\OZJob::COL_PRIORITY,
			$value
		);
	}

	/**
	 * Filters rows with `eq` condition on column `oz_jobs`.`try_count`.
	 *
	 * @param int $value the filter value
	 *
	 * @return static
	 */
	public function whereTryCountIs(int $value): self
	{
		return $this->filterBy(
			Operator::from('eq'),
			\OZONE\Core\Db\OZJob::COL_TRY_COUNT,
			$value
		);
	}

	/**
	 * Filters rows with `neq` condition on column `oz_jobs`.`try_count`.
	 *
	 * @param int $value the filter value
	 *
	 * @return static
	 */
	public function whereTryCountIsNot(int $value): self
	{
		return $this->filterBy(
			Operator::from('neq'),
			\OZONE\Core\Db\OZJob::COL_TRY_COUNT,
			$value
		);
	}

	/**
	 * Filters rows with `lt` condition on column `oz_jobs`.`try_count`.
	 *
	 * @param int $value the filter value
	 *
	 * @return static
	 */
	public function whereTryCountIsLt(int $value): self
	{
		return $this->filterBy(
			Operator::from('lt'),
			\OZONE\Core\Db\OZJob::COL_TRY_COUNT,
			$value
		);
	}

	/**
	 * Filters rows with `lte` condition on column `oz_jobs`.`try_count`.
	 *
	 * @param int $value the filter value
	 *
	 * @return static
	 */
	public function whereTryCountIsLte(int $value): self
	{
		return $this->filterBy(
			Operator::from('lte'),
			\OZONE\Core\Db\OZJob::COL_TRY_COUNT,
			$value
		);
	}

	/**
	 * Filters rows with `gt` condition on column `oz_jobs`.`try_count`.
	 *
	 * @param int $value the filter value
	 *
	 * @return static
	 */
	public function whereTryCountIsGt(int $value): self
	{
		return $this->filterBy(
			Operator::from('gt'),
			\OZONE\Core\Db\OZJob::COL_TRY_COUNT,
			$value
		);
	}

	/**
	 * Filters rows with `gte` condition on column `oz_jobs`.`try_count`.
	 *
	 * @param int $value the filter value
	 *
	 * @return static
	 */
	public function whereTryCountIsGte(int $value): self
	{
		return $this->filterBy(
			Operator::from('gte'),
			\OZONE\Core\Db\OZJob::COL_TRY_COUNT,
			$value
		);
	}

	/**
	 * Filters rows with `like` condition on column `oz_jobs`.`try_count`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 */
	public function whereTryCountIsLike(string $value): self
	{
		return $this->filterBy(
			Operator::from('like'),
			\OZONE\Core\Db\OZJob::COL_TRY_COUNT,
			$value
		);
	}

	/**
	 * Filters rows with `not_like` condition on column `oz_jobs`.`try_count`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 */
	public function whereTryCountIsNotLike(string $value): self
	{
		return $this->filterBy(
			Operator::from('not_like'),
			\OZONE\Core\Db\OZJob::COL_TRY_COUNT,
			$value
		);
	}

	/**
	 * Filters rows with `in` condition on column `oz_jobs`.`try_count`.
	 *
	 * @param array $value the filter value
	 *
	 * @return static
	 */
	public function whereTryCountIsIn(array $value): self
	{
		return $this->filterBy(
			Operator::from('in'),
			\OZONE\Core\Db\OZJob::COL_TRY_COUNT,
			$value
		);
	}

	/**
	 * Filters rows with `not_in` condition on column `oz_jobs`.`try_count`.
	 *
	 * @param array $value the filter value
	 *
	 * @return static
	 */
	public function whereTryCountIsNotIn(array $value): self
	{
		return $this->filterBy(
			Operator::from('not_in'),
			\OZONE\Core\Db\OZJob::COL_TRY_COUNT,
			$value
		);
	}

	/**
	 * Filters rows with `eq` condition on column `oz_jobs`.`retry_max`.
	 *
	 * @param int $value the filter value
	 *
	 * @return static
	 */
	public function whereRetryMaxIs(int $value): self
	{
		return $this->filterBy(
			Operator::from('eq'),
			\OZONE\Core\Db\OZJob::COL_RETRY_MAX,
			$value
		);
	}

	/**
	 * Filters rows with `neq` condition on column `oz_jobs`.`retry_max`.
	 *
	 * @param int $value the filter value
	 *
	 * @return static
	 */
	public function whereRetryMaxIsNot(int $value): self
	{
		return $this->filterBy(
			Operator::from('neq'),
			\OZONE\Core\Db\OZJob::COL_RETRY_MAX,
			$value
		);
	}

	/**
	 * Filters rows with `lt` condition on column `oz_jobs`.`retry_max`.
	 *
	 * @param int $value the filter value
	 *
	 * @return static
	 */
	public function whereRetryMaxIsLt(int $value): self
	{
		return $this->filterBy(
			Operator::from('lt'),
			\OZONE\Core\Db\OZJob::COL_RETRY_MAX,
			$value
		);
	}

	/**
	 * Filters rows with `lte` condition on column `oz_jobs`.`retry_max`.
	 *
	 * @param int $value the filter value
	 *
	 * @return static
	 */
	public function whereRetryMaxIsLte(int $value): self
	{
		return $this->filterBy(
			Operator::from('lte'),
			\OZONE\Core\Db\OZJob::COL_RETRY_MAX,
			$value
		);
	}

	/**
	 * Filters rows with `gt` condition on column `oz_jobs`.`retry_max`.
	 *
	 * @param int $value the filter value
	 *
	 * @return static
	 */
	public function whereRetryMaxIsGt(int $value): self
	{
		return $this->filterBy(
			Operator::from('gt'),
			\OZONE\Core\Db\OZJob::COL_RETRY_MAX,
			$value
		);
	}

	/**
	 * Filters rows with `gte` condition on column `oz_jobs`.`retry_max`.
	 *
	 * @param int $value the filter value
	 *
	 * @return static
	 */
	public function whereRetryMaxIsGte(int $value): self
	{
		return $this->filterBy(
			Operator::from('gte'),
			\OZONE\Core\Db\OZJob::COL_RETRY_MAX,
			$value
		);
	}

	/**
	 * Filters rows with `like` condition on column `oz_jobs`.`retry_max`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 */
	public function whereRetryMaxIsLike(string $value): self
	{
		return $this->filterBy(
			Operator::from('like'),
			\OZONE\Core\Db\OZJob::COL_RETRY_MAX,
			$value
		);
	}

	/**
	 * Filters rows with `not_like` condition on column `oz_jobs`.`retry_max`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 */
	public function whereRetryMaxIsNotLike(string $value): self
	{
		return $this->filterBy(
			Operator::from('not_like'),
			\OZONE\Core\Db\OZJob::COL_RETRY_MAX,
			$value
		);
	}

	/**
	 * Filters rows with `in` condition on column `oz_jobs`.`retry_max`.
	 *
	 * @param array $value the filter value
	 *
	 * @return static
	 */
	public function whereRetryMaxIsIn(array $value): self
	{
		return $this->filterBy(
			Operator::from('in'),
			\OZONE\Core\Db\OZJob::COL_RETRY_MAX,
			$value
		);
	}

	/**
	 * Filters rows with `not_in` condition on column `oz_jobs`.`retry_max`.
	 *
	 * @param array $value the filter value
	 *
	 * @return static
	 */
	public function whereRetryMaxIsNotIn(array $value): self
	{
		return $this->filterBy(
			Operator::from('not_in'),
			\OZONE\Core\Db\OZJob::COL_RETRY_MAX,
			$value
		);
	}

	/**
	 * Filters rows with `eq` condition on column `oz_jobs`.`retry_delay`.
	 *
	 * @param int $value the filter value
	 *
	 * @return static
	 */
	public function whereRetryDelayIs(int $value): self
	{
		return $this->filterBy(
			Operator::from('eq'),
			\OZONE\Core\Db\OZJob::COL_RETRY_DELAY,
			$value
		);
	}

	/**
	 * Filters rows with `neq` condition on column `oz_jobs`.`retry_delay`.
	 *
	 * @param int $value the filter value
	 *
	 * @return static
	 */
	public function whereRetryDelayIsNot(int $value): self
	{
		return $this->filterBy(
			Operator::from('neq'),
			\OZONE\Core\Db\OZJob::COL_RETRY_DELAY,
			$value
		);
	}

	/**
	 * Filters rows with `lt` condition on column `oz_jobs`.`retry_delay`.
	 *
	 * @param int $value the filter value
	 *
	 * @return static
	 */
	public function whereRetryDelayIsLt(int $value): self
	{
		return $this->filterBy(
			Operator::from('lt'),
			\OZONE\Core\Db\OZJob::COL_RETRY_DELAY,
			$value
		);
	}

	/**
	 * Filters rows with `lte` condition on column `oz_jobs`.`retry_delay`.
	 *
	 * @param int $value the filter value
	 *
	 * @return static
	 */
	public function whereRetryDelayIsLte(int $value): self
	{
		return $this->filterBy(
			Operator::from('lte'),
			\OZONE\Core\Db\OZJob::COL_RETRY_DELAY,
			$value
		);
	}

	/**
	 * Filters rows with `gt` condition on column `oz_jobs`.`retry_delay`.
	 *
	 * @param int $value the filter value
	 *
	 * @return static
	 */
	public function whereRetryDelayIsGt(int $value): self
	{
		return $this->filterBy(
			Operator::from('gt'),
			\OZONE\Core\Db\OZJob::COL_RETRY_DELAY,
			$value
		);
	}

	/**
	 * Filters rows with `gte` condition on column `oz_jobs`.`retry_delay`.
	 *
	 * @param int $value the filter value
	 *
	 * @return static
	 */
	public function whereRetryDelayIsGte(int $value): self
	{
		return $this->filterBy(
			Operator::from('gte'),
			\OZONE\Core\Db\OZJob::COL_RETRY_DELAY,
			$value
		);
	}

	/**
	 * Filters rows with `like` condition on column `oz_jobs`.`retry_delay`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 */
	public function whereRetryDelayIsLike(string $value): self
	{
		return $this->filterBy(
			Operator::from('like'),
			\OZONE\Core\Db\OZJob::COL_RETRY_DELAY,
			$value
		);
	}

	/**
	 * Filters rows with `not_like` condition on column `oz_jobs`.`retry_delay`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 */
	public function whereRetryDelayIsNotLike(string $value): self
	{
		return $this->filterBy(
			Operator::from('not_like'),
			\OZONE\Core\Db\OZJob::COL_RETRY_DELAY,
			$value
		);
	}

	/**
	 * Filters rows with `in` condition on column `oz_jobs`.`retry_delay`.
	 *
	 * @param array $value the filter value
	 *
	 * @return static
	 */
	public function whereRetryDelayIsIn(array $value): self
	{
		return $this->filterBy(
			Operator::from('in'),
			\OZONE\Core\Db\OZJob::COL_RETRY_DELAY,
			$value
		);
	}

	/**
	 * Filters rows with `not_in` condition on column `oz_jobs`.`retry_delay`.
	 *
	 * @param array $value the filter value
	 *
	 * @return static
	 */
	public function whereRetryDelayIsNotIn(array $value): self
	{
		return $this->filterBy(
			Operator::from('not_in'),
			\OZONE\Core\Db\OZJob::COL_RETRY_DELAY,
			$value
		);
	}

	/**
	 * Filters rows with `eq` condition on column `oz_jobs`.`payload`.
	 *
	 * @param array $value the filter value
	 *
	 * @return static
	 */
	public function wherePayloadIs(array $value): self
	{
		return $this->filterBy(
			Operator::from('eq'),
			\OZONE\Core\Db\OZJob::COL_PAYLOAD,
			$value
		);
	}

	/**
	 * Filters rows with `neq` condition on column `oz_jobs`.`payload`.
	 *
	 * @param array $value the filter value
	 *
	 * @return static
	 */
	public function wherePayloadIsNot(array $value): self
	{
		return $this->filterBy(
			Operator::from('neq'),
			\OZONE\Core\Db\OZJob::COL_PAYLOAD,
			$value
		);
	}

	/**
	 * Filters rows with `like` condition on column `oz_jobs`.`payload`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 */
	public function wherePayloadIsLike(string $value): self
	{
		return $this->filterBy(
			Operator::from('like'),
			\OZONE\Core\Db\OZJob::COL_PAYLOAD,
			$value
		);
	}

	/**
	 * Filters rows with `not_like` condition on column `oz_jobs`.`payload`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 */
	public function wherePayloadIsNotLike(string $value): self
	{
		return $this->filterBy(
			Operator::from('not_like'),
			\OZONE\Core\Db\OZJob::COL_PAYLOAD,
			$value
		);
	}

	/**
	 * Filters rows with `eq` condition on column `oz_jobs`.`result`.
	 *
	 * @param array $value the filter value
	 *
	 * @return static
	 */
	public function whereResultIs(array $value): self
	{
		return $this->filterBy(
			Operator::from('eq'),
			\OZONE\Core\Db\OZJob::COL_RESULT,
			$value
		);
	}

	/**
	 * Filters rows with `neq` condition on column `oz_jobs`.`result`.
	 *
	 * @param array $value the filter value
	 *
	 * @return static
	 */
	public function whereResultIsNot(array $value): self
	{
		return $this->filterBy(
			Operator::from('neq'),
			\OZONE\Core\Db\OZJob::COL_RESULT,
			$value
		);
	}

	/**
	 * Filters rows with `like` condition on column `oz_jobs`.`result`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 */
	public function whereResultIsLike(string $value): self
	{
		return $this->filterBy(
			Operator::from('like'),
			\OZONE\Core\Db\OZJob::COL_RESULT,
			$value
		);
	}

	/**
	 * Filters rows with `not_like` condition on column `oz_jobs`.`result`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 */
	public function whereResultIsNotLike(string $value): self
	{
		return $this->filterBy(
			Operator::from('not_like'),
			\OZONE\Core\Db\OZJob::COL_RESULT,
			$value
		);
	}

	/**
	 * Filters rows with `eq` condition on column `oz_jobs`.`errors`.
	 *
	 * @param array $value the filter value
	 *
	 * @return static
	 */
	public function whereErrorsIs(array $value): self
	{
		return $this->filterBy(
			Operator::from('eq'),
			\OZONE\Core\Db\OZJob::COL_ERRORS,
			$value
		);
	}

	/**
	 * Filters rows with `neq` condition on column `oz_jobs`.`errors`.
	 *
	 * @param array $value the filter value
	 *
	 * @return static
	 */
	public function whereErrorsIsNot(array $value): self
	{
		return $this->filterBy(
			Operator::from('neq'),
			\OZONE\Core\Db\OZJob::COL_ERRORS,
			$value
		);
	}

	/**
	 * Filters rows with `like` condition on column `oz_jobs`.`errors`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 */
	public function whereErrorsIsLike(string $value): self
	{
		return $this->filterBy(
			Operator::from('like'),
			\OZONE\Core\Db\OZJob::COL_ERRORS,
			$value
		);
	}

	/**
	 * Filters rows with `not_like` condition on column `oz_jobs`.`errors`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 */
	public function whereErrorsIsNotLike(string $value): self
	{
		return $this->filterBy(
			Operator::from('not_like'),
			\OZONE\Core\Db\OZJob::COL_ERRORS,
			$value
		);
	}

	/**
	 * Filters rows with `eq` condition on column `oz_jobs`.`locked`.
	 *
	 * @param bool $value the filter value
	 *
	 * @return static
	 */
	public function whereLockedIs(bool $value): self
	{
		return $this->filterBy(
			Operator::from('eq'),
			\OZONE\Core\Db\OZJob::COL_LOCKED,
			$value
		);
	}

	/**
	 * Filters rows with `neq` condition on column `oz_jobs`.`locked`.
	 *
	 * @param bool $value the filter value
	 *
	 * @return static
	 */
	public function whereLockedIsNot(bool $value): self
	{
		return $this->filterBy(
			Operator::from('neq'),
			\OZONE\Core\Db\OZJob::COL_LOCKED,
			$value
		);
	}

	/**
	 * Filters rows with `is_false` condition on column `oz_jobs`.`locked`.
	 *
	 * @return static
	 */
	public function whereIsNotLocked(): self
	{
		return $this->filterBy(
			Operator::from('is_false'),
			\OZONE\Core\Db\OZJob::COL_LOCKED
		);
	}

	/**
	 * Filters rows with `is_true` condition on column `oz_jobs`.`locked`.
	 *
	 * @return static
	 */
	public function whereIsLocked(): self
	{
		return $this->filterBy(
			Operator::from('is_true'),
			\OZONE\Core\Db\OZJob::COL_LOCKED
		);
	}

	/**
	 * Filters rows with `eq` condition on column `oz_jobs`.`started_at`.
	 *
	 * @param float|int|string $value the filter value
	 *
	 * @return static
	 */
	public function whereStartedAtIs(string|float|int $value): self
	{
		return $this->filterBy(
			Operator::from('eq'),
			\OZONE\Core\Db\OZJob::COL_STARTED_AT,
			$value
		);
	}

	/**
	 * Filters rows with `neq` condition on column `oz_jobs`.`started_at`.
	 *
	 * @param float|int|string $value the filter value
	 *
	 * @return static
	 */
	public function whereStartedAtIsNot(string|float|int $value): self
	{
		return $this->filterBy(
			Operator::from('neq'),
			\OZONE\Core\Db\OZJob::COL_STARTED_AT,
			$value
		);
	}

	/**
	 * Filters rows with `lt` condition on column `oz_jobs`.`started_at`.
	 *
	 * @param float|int|string $value the filter value
	 *
	 * @return static
	 */
	public function whereStartedAtIsLt(string|float|int $value): self
	{
		return $this->filterBy(
			Operator::from('lt'),
			\OZONE\Core\Db\OZJob::COL_STARTED_AT,
			$value
		);
	}

	/**
	 * Filters rows with `lte` condition on column `oz_jobs`.`started_at`.
	 *
	 * @param float|int|string $value the filter value
	 *
	 * @return static
	 */
	public function whereStartedAtIsLte(string|float|int $value): self
	{
		return $this->filterBy(
			Operator::from('lte'),
			\OZONE\Core\Db\OZJob::COL_STARTED_AT,
			$value
		);
	}

	/**
	 * Filters rows with `gt` condition on column `oz_jobs`.`started_at`.
	 *
	 * @param float|int|string $value the filter value
	 *
	 * @return static
	 */
	public function whereStartedAtIsGt(string|float|int $value): self
	{
		return $this->filterBy(
			Operator::from('gt'),
			\OZONE\Core\Db\OZJob::COL_STARTED_AT,
			$value
		);
	}

	/**
	 * Filters rows with `gte` condition on column `oz_jobs`.`started_at`.
	 *
	 * @param float|int|string $value the filter value
	 *
	 * @return static
	 */
	public function whereStartedAtIsGte(string|float|int $value): self
	{
		return $this->filterBy(
			Operator::from('gte'),
			\OZONE\Core\Db\OZJob::COL_STARTED_AT,
			$value
		);
	}

	/**
	 * Filters rows with `like` condition on column `oz_jobs`.`started_at`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 */
	public function whereStartedAtIsLike(string $value): self
	{
		return $this->filterBy(
			Operator::from('like'),
			\OZONE\Core\Db\OZJob::COL_STARTED_AT,
			$value
		);
	}

	/**
	 * Filters rows with `not_like` condition on column `oz_jobs`.`started_at`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 */
	public function whereStartedAtIsNotLike(string $value): self
	{
		return $this->filterBy(
			Operator::from('not_like'),
			\OZONE\Core\Db\OZJob::COL_STARTED_AT,
			$value
		);
	}

	/**
	 * Filters rows with `is_null` condition on column `oz_jobs`.`started_at`.
	 *
	 * @return static
	 */
	public function whereStartedAtIsNull(): self
	{
		return $this->filterBy(
			Operator::from('is_null'),
			\OZONE\Core\Db\OZJob::COL_STARTED_AT
		);
	}

	/**
	 * Filters rows with `is_not_null` condition on column `oz_jobs`.`started_at`.
	 *
	 * @return static
	 */
	public function whereStartedAtIsNotNull(): self
	{
		return $this->filterBy(
			Operator::from('is_not_null'),
			\OZONE\Core\Db\OZJob::COL_STARTED_AT
		);
	}

	/**
	 * Filters rows with `in` condition on column `oz_jobs`.`started_at`.
	 *
	 * @param array $value the filter value
	 *
	 * @return static
	 */
	public function whereStartedAtIsIn(array $value): self
	{
		return $this->filterBy(
			Operator::from('in'),
			\OZONE\Core\Db\OZJob::COL_STARTED_AT,
			$value
		);
	}

	/**
	 * Filters rows with `not_in` condition on column `oz_jobs`.`started_at`.
	 *
	 * @param array $value the filter value
	 *
	 * @return static
	 */
	public function whereStartedAtIsNotIn(array $value): self
	{
		return $this->filterBy(
			Operator::from('not_in'),
			\OZONE\Core\Db\OZJob::COL_STARTED_AT,
			$value
		);
	}

	/**
	 * Filters rows with `eq` condition on column `oz_jobs`.`ended_at`.
	 *
	 * @param float|int|string $value the filter value
	 *
	 * @return static
	 */
	public function whereEndedAtIs(string|float|int $value): self
	{
		return $this->filterBy(
			Operator::from('eq'),
			\OZONE\Core\Db\OZJob::COL_ENDED_AT,
			$value
		);
	}

	/**
	 * Filters rows with `neq` condition on column `oz_jobs`.`ended_at`.
	 *
	 * @param float|int|string $value the filter value
	 *
	 * @return static
	 */
	public function whereEndedAtIsNot(string|float|int $value): self
	{
		return $this->filterBy(
			Operator::from('neq'),
			\OZONE\Core\Db\OZJob::COL_ENDED_AT,
			$value
		);
	}

	/**
	 * Filters rows with `lt` condition on column `oz_jobs`.`ended_at`.
	 *
	 * @param float|int|string $value the filter value
	 *
	 * @return static
	 */
	public function whereEndedAtIsLt(string|float|int $value): self
	{
		return $this->filterBy(
			Operator::from('lt'),
			\OZONE\Core\Db\OZJob::COL_ENDED_AT,
			$value
		);
	}

	/**
	 * Filters rows with `lte` condition on column `oz_jobs`.`ended_at`.
	 *
	 * @param float|int|string $value the filter value
	 *
	 * @return static
	 */
	public function whereEndedAtIsLte(string|float|int $value): self
	{
		return $this->filterBy(
			Operator::from('lte'),
			\OZONE\Core\Db\OZJob::COL_ENDED_AT,
			$value
		);
	}

	/**
	 * Filters rows with `gt` condition on column `oz_jobs`.`ended_at`.
	 *
	 * @param float|int|string $value the filter value
	 *
	 * @return static
	 */
	public function whereEndedAtIsGt(string|float|int $value): self
	{
		return $this->filterBy(
			Operator::from('gt'),
			\OZONE\Core\Db\OZJob::COL_ENDED_AT,
			$value
		);
	}

	/**
	 * Filters rows with `gte` condition on column `oz_jobs`.`ended_at`.
	 *
	 * @param float|int|string $value the filter value
	 *
	 * @return static
	 */
	public function whereEndedAtIsGte(string|float|int $value): self
	{
		return $this->filterBy(
			Operator::from('gte'),
			\OZONE\Core\Db\OZJob::COL_ENDED_AT,
			$value
		);
	}

	/**
	 * Filters rows with `like` condition on column `oz_jobs`.`ended_at`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 */
	public function whereEndedAtIsLike(string $value): self
	{
		return $this->filterBy(
			Operator::from('like'),
			\OZONE\Core\Db\OZJob::COL_ENDED_AT,
			$value
		);
	}

	/**
	 * Filters rows with `not_like` condition on column `oz_jobs`.`ended_at`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 */
	public function whereEndedAtIsNotLike(string $value): self
	{
		return $this->filterBy(
			Operator::from('not_like'),
			\OZONE\Core\Db\OZJob::COL_ENDED_AT,
			$value
		);
	}

	/**
	 * Filters rows with `is_null` condition on column `oz_jobs`.`ended_at`.
	 *
	 * @return static
	 */
	public function whereEndedAtIsNull(): self
	{
		return $this->filterBy(
			Operator::from('is_null'),
			\OZONE\Core\Db\OZJob::COL_ENDED_AT
		);
	}

	/**
	 * Filters rows with `is_not_null` condition on column `oz_jobs`.`ended_at`.
	 *
	 * @return static
	 */
	public function whereEndedAtIsNotNull(): self
	{
		return $this->filterBy(
			Operator::from('is_not_null'),
			\OZONE\Core\Db\OZJob::COL_ENDED_AT
		);
	}

	/**
	 * Filters rows with `in` condition on column `oz_jobs`.`ended_at`.
	 *
	 * @param array $value the filter value
	 *
	 * @return static
	 */
	public function whereEndedAtIsIn(array $value): self
	{
		return $this->filterBy(
			Operator::from('in'),
			\OZONE\Core\Db\OZJob::COL_ENDED_AT,
			$value
		);
	}

	/**
	 * Filters rows with `not_in` condition on column `oz_jobs`.`ended_at`.
	 *
	 * @param array $value the filter value
	 *
	 * @return static
	 */
	public function whereEndedAtIsNotIn(array $value): self
	{
		return $this->filterBy(
			Operator::from('not_in'),
			\OZONE\Core\Db\OZJob::COL_ENDED_AT,
			$value
		);
	}

	/**
	 * Filters rows with `eq` condition on column `oz_jobs`.`created_at`.
	 *
	 * @param int|string $value the filter value
	 *
	 * @return static
	 */
	public function whereCreatedAtIs(string|int $value): self
	{
		return $this->filterBy(
			Operator::from('eq'),
			\OZONE\Core\Db\OZJob::COL_CREATED_AT,
			$value
		);
	}

	/**
	 * Filters rows with `neq` condition on column `oz_jobs`.`created_at`.
	 *
	 * @param int|string $value the filter value
	 *
	 * @return static
	 */
	public function whereCreatedAtIsNot(string|int $value): self
	{
		return $this->filterBy(
			Operator::from('neq'),
			\OZONE\Core\Db\OZJob::COL_CREATED_AT,
			$value
		);
	}

	/**
	 * Filters rows with `lt` condition on column `oz_jobs`.`created_at`.
	 *
	 * @param int|string $value the filter value
	 *
	 * @return static
	 */
	public function whereCreatedAtIsLt(string|int $value): self
	{
		return $this->filterBy(
			Operator::from('lt'),
			\OZONE\Core\Db\OZJob::COL_CREATED_AT,
			$value
		);
	}

	/**
	 * Filters rows with `lte` condition on column `oz_jobs`.`created_at`.
	 *
	 * @param int|string $value the filter value
	 *
	 * @return static
	 */
	public function whereCreatedAtIsLte(string|int $value): self
	{
		return $this->filterBy(
			Operator::from('lte'),
			\OZONE\Core\Db\OZJob::COL_CREATED_AT,
			$value
		);
	}

	/**
	 * Filters rows with `gt` condition on column `oz_jobs`.`created_at`.
	 *
	 * @param int|string $value the filter value
	 *
	 * @return static
	 */
	public function whereCreatedAtIsGt(string|int $value): self
	{
		return $this->filterBy(
			Operator::from('gt'),
			\OZONE\Core\Db\OZJob::COL_CREATED_AT,
			$value
		);
	}

	/**
	 * Filters rows with `gte` condition on column `oz_jobs`.`created_at`.
	 *
	 * @param int|string $value the filter value
	 *
	 * @return static
	 */
	public function whereCreatedAtIsGte(string|int $value): self
	{
		return $this->filterBy(
			Operator::from('gte'),
			\OZONE\Core\Db\OZJob::COL_CREATED_AT,
			$value
		);
	}

	/**
	 * Filters rows with `like` condition on column `oz_jobs`.`created_at`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 */
	public function whereCreatedAtIsLike(string $value): self
	{
		return $this->filterBy(
			Operator::from('like'),
			\OZONE\Core\Db\OZJob::COL_CREATED_AT,
			$value
		);
	}

	/**
	 * Filters rows with `not_like` condition on column `oz_jobs`.`created_at`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 */
	public function whereCreatedAtIsNotLike(string $value): self
	{
		return $this->filterBy(
			Operator::from('not_like'),
			\OZONE\Core\Db\OZJob::COL_CREATED_AT,
			$value
		);
	}

	/**
	 * Filters rows with `in` condition on column `oz_jobs`.`created_at`.
	 *
	 * @param array $value the filter value
	 *
	 * @return static
	 */
	public function whereCreatedAtIsIn(array $value): self
	{
		return $this->filterBy(
			Operator::from('in'),
			\OZONE\Core\Db\OZJob::COL_CREATED_AT,
			$value
		);
	}

	/**
	 * Filters rows with `not_in` condition on column `oz_jobs`.`created_at`.
	 *
	 * @param array $value the filter value
	 *
	 * @return static
	 */
	public function whereCreatedAtIsNotIn(array $value): self
	{
		return $this->filterBy(
			Operator::from('not_in'),
			\OZONE\Core\Db\OZJob::COL_CREATED_AT,
			$value
		);
	}

	/**
	 * Filters rows with `eq` condition on column `oz_jobs`.`updated_at`.
	 *
	 * @param int|string $value the filter value
	 *
	 * @return static
	 */
	public function whereUpdatedAtIs(string|int $value): self
	{
		return $this->filterBy(
			Operator::from('eq'),
			\OZONE\Core\Db\OZJob::COL_UPDATED_AT,
			$value
		);
	}

	/**
	 * Filters rows with `neq` condition on column `oz_jobs`.`updated_at`.
	 *
	 * @param int|string $value the filter value
	 *
	 * @return static
	 */
	public function whereUpdatedAtIsNot(string|int $value): self
	{
		return $this->filterBy(
			Operator::from('neq'),
			\OZONE\Core\Db\OZJob::COL_UPDATED_AT,
			$value
		);
	}

	/**
	 * Filters rows with `lt` condition on column `oz_jobs`.`updated_at`.
	 *
	 * @param int|string $value the filter value
	 *
	 * @return static
	 */
	public function whereUpdatedAtIsLt(string|int $value): self
	{
		return $this->filterBy(
			Operator::from('lt'),
			\OZONE\Core\Db\OZJob::COL_UPDATED_AT,
			$value
		);
	}

	/**
	 * Filters rows with `lte` condition on column `oz_jobs`.`updated_at`.
	 *
	 * @param int|string $value the filter value
	 *
	 * @return static
	 */
	public function whereUpdatedAtIsLte(string|int $value): self
	{
		return $this->filterBy(
			Operator::from('lte'),
			\OZONE\Core\Db\OZJob::COL_UPDATED_AT,
			$value
		);
	}

	/**
	 * Filters rows with `gt` condition on column `oz_jobs`.`updated_at`.
	 *
	 * @param int|string $value the filter value
	 *
	 * @return static
	 */
	public function whereUpdatedAtIsGt(string|int $value): self
	{
		return $this->filterBy(
			Operator::from('gt'),
			\OZONE\Core\Db\OZJob::COL_UPDATED_AT,
			$value
		);
	}

	/**
	 * Filters rows with `gte` condition on column `oz_jobs`.`updated_at`.
	 *
	 * @param int|string $value the filter value
	 *
	 * @return static
	 */
	public function whereUpdatedAtIsGte(string|int $value): self
	{
		return $this->filterBy(
			Operator::from('gte'),
			\OZONE\Core\Db\OZJob::COL_UPDATED_AT,
			$value
		);
	}

	/**
	 * Filters rows with `like` condition on column `oz_jobs`.`updated_at`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 */
	public function whereUpdatedAtIsLike(string $value): self
	{
		return $this->filterBy(
			Operator::from('like'),
			\OZONE\Core\Db\OZJob::COL_UPDATED_AT,
			$value
		);
	}

	/**
	 * Filters rows with `not_like` condition on column `oz_jobs`.`updated_at`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 */
	public function whereUpdatedAtIsNotLike(string $value): self
	{
		return $this->filterBy(
			Operator::from('not_like'),
			\OZONE\Core\Db\OZJob::COL_UPDATED_AT,
			$value
		);
	}

	/**
	 * Filters rows with `in` condition on column `oz_jobs`.`updated_at`.
	 *
	 * @param array $value the filter value
	 *
	 * @return static
	 */
	public function whereUpdatedAtIsIn(array $value): self
	{
		return $this->filterBy(
			Operator::from('in'),
			\OZONE\Core\Db\OZJob::COL_UPDATED_AT,
			$value
		);
	}

	/**
	 * Filters rows with `not_in` condition on column `oz_jobs`.`updated_at`.
	 *
	 * @param array $value the filter value
	 *
	 * @return static
	 */
	public function whereUpdatedAtIsNotIn(array $value): self
	{
		return $this->filterBy(
			Operator::from('not_in'),
			\OZONE\Core\Db\OZJob::COL_UPDATED_AT,
			$value
		);
	}
}
