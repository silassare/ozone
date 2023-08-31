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

/**
 * Class OZJobsQuery.
 *
 * @extends \Gobl\ORM\ORMTableQuery<\OZONE\Core\Db\OZJob>
 *
 * @method $this whereIdIs(int|string $value)                              Filters rows with `eq` condition on column `oz_jobs`.`id`.
 * @method $this whereIdIsNot(int|string $value)                           Filters rows with `neq` condition on column `oz_jobs`.`id`.
 * @method $this whereIdIsLt(int|string $value)                            Filters rows with `lt` condition on column `oz_jobs`.`id`.
 * @method $this whereIdIsLte(int|string $value)                           Filters rows with `lte` condition on column `oz_jobs`.`id`.
 * @method $this whereIdIsGt(int|string $value)                            Filters rows with `gt` condition on column `oz_jobs`.`id`.
 * @method $this whereIdIsGte(int|string $value)                           Filters rows with `gte` condition on column `oz_jobs`.`id`.
 * @method $this whereIdIsLike(string $value)                              Filters rows with `like` condition on column `oz_jobs`.`id`.
 * @method $this whereIdIsNotLike(string $value)                           Filters rows with `not_like` condition on column `oz_jobs`.`id`.
 * @method $this whereIdIsIn(array $value)                                 Filters rows with `in` condition on column `oz_jobs`.`id`.
 * @method $this whereIdIsNotIn(array $value)                              Filters rows with `not_in` condition on column `oz_jobs`.`id`.
 * @method $this whereRefIs(string $value)                                 Filters rows with `eq` condition on column `oz_jobs`.`ref`.
 * @method $this whereRefIsNot(string $value)                              Filters rows with `neq` condition on column `oz_jobs`.`ref`.
 * @method $this whereRefIsLt(string $value)                               Filters rows with `lt` condition on column `oz_jobs`.`ref`.
 * @method $this whereRefIsLte(string $value)                              Filters rows with `lte` condition on column `oz_jobs`.`ref`.
 * @method $this whereRefIsGt(string $value)                               Filters rows with `gt` condition on column `oz_jobs`.`ref`.
 * @method $this whereRefIsGte(string $value)                              Filters rows with `gte` condition on column `oz_jobs`.`ref`.
 * @method $this whereRefIsLike(string $value)                             Filters rows with `like` condition on column `oz_jobs`.`ref`.
 * @method $this whereRefIsNotLike(string $value)                          Filters rows with `not_like` condition on column `oz_jobs`.`ref`.
 * @method $this whereRefIsIn(array $value)                                Filters rows with `in` condition on column `oz_jobs`.`ref`.
 * @method $this whereRefIsNotIn(array $value)                             Filters rows with `not_in` condition on column `oz_jobs`.`ref`.
 * @method $this whereStateIs(\OZONE\Core\Queue\JobState|string $value)    Filters rows with `eq` condition on column `oz_jobs`.`state`.
 * @method $this whereStateIsNot(\OZONE\Core\Queue\JobState|string $value) Filters rows with `neq` condition on column `oz_jobs`.`state`.
 * @method $this whereStateIsLt(\OZONE\Core\Queue\JobState|string $value)  Filters rows with `lt` condition on column `oz_jobs`.`state`.
 * @method $this whereStateIsLte(\OZONE\Core\Queue\JobState|string $value) Filters rows with `lte` condition on column `oz_jobs`.`state`.
 * @method $this whereStateIsGt(\OZONE\Core\Queue\JobState|string $value)  Filters rows with `gt` condition on column `oz_jobs`.`state`.
 * @method $this whereStateIsGte(\OZONE\Core\Queue\JobState|string $value) Filters rows with `gte` condition on column `oz_jobs`.`state`.
 * @method $this whereStateIsLike(string $value)                           Filters rows with `like` condition on column `oz_jobs`.`state`.
 * @method $this whereStateIsNotLike(string $value)                        Filters rows with `not_like` condition on column `oz_jobs`.`state`.
 * @method $this whereStateIsIn(array $value)                              Filters rows with `in` condition on column `oz_jobs`.`state`.
 * @method $this whereStateIsNotIn(array $value)                           Filters rows with `not_in` condition on column `oz_jobs`.`state`.
 * @method $this whereQueueIs(string $value)                               Filters rows with `eq` condition on column `oz_jobs`.`queue`.
 * @method $this whereQueueIsNot(string $value)                            Filters rows with `neq` condition on column `oz_jobs`.`queue`.
 * @method $this whereQueueIsLt(string $value)                             Filters rows with `lt` condition on column `oz_jobs`.`queue`.
 * @method $this whereQueueIsLte(string $value)                            Filters rows with `lte` condition on column `oz_jobs`.`queue`.
 * @method $this whereQueueIsGt(string $value)                             Filters rows with `gt` condition on column `oz_jobs`.`queue`.
 * @method $this whereQueueIsGte(string $value)                            Filters rows with `gte` condition on column `oz_jobs`.`queue`.
 * @method $this whereQueueIsLike(string $value)                           Filters rows with `like` condition on column `oz_jobs`.`queue`.
 * @method $this whereQueueIsNotLike(string $value)                        Filters rows with `not_like` condition on column `oz_jobs`.`queue`.
 * @method $this whereQueueIsIn(array $value)                              Filters rows with `in` condition on column `oz_jobs`.`queue`.
 * @method $this whereQueueIsNotIn(array $value)                           Filters rows with `not_in` condition on column `oz_jobs`.`queue`.
 * @method $this whereNameIs(string $value)                                Filters rows with `eq` condition on column `oz_jobs`.`name`.
 * @method $this whereNameIsNot(string $value)                             Filters rows with `neq` condition on column `oz_jobs`.`name`.
 * @method $this whereNameIsLt(string $value)                              Filters rows with `lt` condition on column `oz_jobs`.`name`.
 * @method $this whereNameIsLte(string $value)                             Filters rows with `lte` condition on column `oz_jobs`.`name`.
 * @method $this whereNameIsGt(string $value)                              Filters rows with `gt` condition on column `oz_jobs`.`name`.
 * @method $this whereNameIsGte(string $value)                             Filters rows with `gte` condition on column `oz_jobs`.`name`.
 * @method $this whereNameIsLike(string $value)                            Filters rows with `like` condition on column `oz_jobs`.`name`.
 * @method $this whereNameIsNotLike(string $value)                         Filters rows with `not_like` condition on column `oz_jobs`.`name`.
 * @method $this whereNameIsIn(array $value)                               Filters rows with `in` condition on column `oz_jobs`.`name`.
 * @method $this whereNameIsNotIn(array $value)                            Filters rows with `not_in` condition on column `oz_jobs`.`name`.
 * @method $this whereWorkerIs(string $value)                              Filters rows with `eq` condition on column `oz_jobs`.`worker`.
 * @method $this whereWorkerIsNot(string $value)                           Filters rows with `neq` condition on column `oz_jobs`.`worker`.
 * @method $this whereWorkerIsLt(string $value)                            Filters rows with `lt` condition on column `oz_jobs`.`worker`.
 * @method $this whereWorkerIsLte(string $value)                           Filters rows with `lte` condition on column `oz_jobs`.`worker`.
 * @method $this whereWorkerIsGt(string $value)                            Filters rows with `gt` condition on column `oz_jobs`.`worker`.
 * @method $this whereWorkerIsGte(string $value)                           Filters rows with `gte` condition on column `oz_jobs`.`worker`.
 * @method $this whereWorkerIsLike(string $value)                          Filters rows with `like` condition on column `oz_jobs`.`worker`.
 * @method $this whereWorkerIsNotLike(string $value)                       Filters rows with `not_like` condition on column `oz_jobs`.`worker`.
 * @method $this whereWorkerIsIn(array $value)                             Filters rows with `in` condition on column `oz_jobs`.`worker`.
 * @method $this whereWorkerIsNotIn(array $value)                          Filters rows with `not_in` condition on column `oz_jobs`.`worker`.
 * @method $this wherePriorityIs(int $value)                               Filters rows with `eq` condition on column `oz_jobs`.`priority`.
 * @method $this wherePriorityIsNot(int $value)                            Filters rows with `neq` condition on column `oz_jobs`.`priority`.
 * @method $this wherePriorityIsLt(int $value)                             Filters rows with `lt` condition on column `oz_jobs`.`priority`.
 * @method $this wherePriorityIsLte(int $value)                            Filters rows with `lte` condition on column `oz_jobs`.`priority`.
 * @method $this wherePriorityIsGt(int $value)                             Filters rows with `gt` condition on column `oz_jobs`.`priority`.
 * @method $this wherePriorityIsGte(int $value)                            Filters rows with `gte` condition on column `oz_jobs`.`priority`.
 * @method $this wherePriorityIsLike(string $value)                        Filters rows with `like` condition on column `oz_jobs`.`priority`.
 * @method $this wherePriorityIsNotLike(string $value)                     Filters rows with `not_like` condition on column `oz_jobs`.`priority`.
 * @method $this wherePriorityIsIn(array $value)                           Filters rows with `in` condition on column `oz_jobs`.`priority`.
 * @method $this wherePriorityIsNotIn(array $value)                        Filters rows with `not_in` condition on column `oz_jobs`.`priority`.
 * @method $this whereTryCountIs(int $value)                               Filters rows with `eq` condition on column `oz_jobs`.`try_count`.
 * @method $this whereTryCountIsNot(int $value)                            Filters rows with `neq` condition on column `oz_jobs`.`try_count`.
 * @method $this whereTryCountIsLt(int $value)                             Filters rows with `lt` condition on column `oz_jobs`.`try_count`.
 * @method $this whereTryCountIsLte(int $value)                            Filters rows with `lte` condition on column `oz_jobs`.`try_count`.
 * @method $this whereTryCountIsGt(int $value)                             Filters rows with `gt` condition on column `oz_jobs`.`try_count`.
 * @method $this whereTryCountIsGte(int $value)                            Filters rows with `gte` condition on column `oz_jobs`.`try_count`.
 * @method $this whereTryCountIsLike(string $value)                        Filters rows with `like` condition on column `oz_jobs`.`try_count`.
 * @method $this whereTryCountIsNotLike(string $value)                     Filters rows with `not_like` condition on column `oz_jobs`.`try_count`.
 * @method $this whereTryCountIsIn(array $value)                           Filters rows with `in` condition on column `oz_jobs`.`try_count`.
 * @method $this whereTryCountIsNotIn(array $value)                        Filters rows with `not_in` condition on column `oz_jobs`.`try_count`.
 * @method $this whereRetryMaxIs(int $value)                               Filters rows with `eq` condition on column `oz_jobs`.`retry_max`.
 * @method $this whereRetryMaxIsNot(int $value)                            Filters rows with `neq` condition on column `oz_jobs`.`retry_max`.
 * @method $this whereRetryMaxIsLt(int $value)                             Filters rows with `lt` condition on column `oz_jobs`.`retry_max`.
 * @method $this whereRetryMaxIsLte(int $value)                            Filters rows with `lte` condition on column `oz_jobs`.`retry_max`.
 * @method $this whereRetryMaxIsGt(int $value)                             Filters rows with `gt` condition on column `oz_jobs`.`retry_max`.
 * @method $this whereRetryMaxIsGte(int $value)                            Filters rows with `gte` condition on column `oz_jobs`.`retry_max`.
 * @method $this whereRetryMaxIsLike(string $value)                        Filters rows with `like` condition on column `oz_jobs`.`retry_max`.
 * @method $this whereRetryMaxIsNotLike(string $value)                     Filters rows with `not_like` condition on column `oz_jobs`.`retry_max`.
 * @method $this whereRetryMaxIsIn(array $value)                           Filters rows with `in` condition on column `oz_jobs`.`retry_max`.
 * @method $this whereRetryMaxIsNotIn(array $value)                        Filters rows with `not_in` condition on column `oz_jobs`.`retry_max`.
 * @method $this whereRetryDelayIs(int $value)                             Filters rows with `eq` condition on column `oz_jobs`.`retry_delay`.
 * @method $this whereRetryDelayIsNot(int $value)                          Filters rows with `neq` condition on column `oz_jobs`.`retry_delay`.
 * @method $this whereRetryDelayIsLt(int $value)                           Filters rows with `lt` condition on column `oz_jobs`.`retry_delay`.
 * @method $this whereRetryDelayIsLte(int $value)                          Filters rows with `lte` condition on column `oz_jobs`.`retry_delay`.
 * @method $this whereRetryDelayIsGt(int $value)                           Filters rows with `gt` condition on column `oz_jobs`.`retry_delay`.
 * @method $this whereRetryDelayIsGte(int $value)                          Filters rows with `gte` condition on column `oz_jobs`.`retry_delay`.
 * @method $this whereRetryDelayIsLike(string $value)                      Filters rows with `like` condition on column `oz_jobs`.`retry_delay`.
 * @method $this whereRetryDelayIsNotLike(string $value)                   Filters rows with `not_like` condition on column `oz_jobs`.`retry_delay`.
 * @method $this whereRetryDelayIsIn(array $value)                         Filters rows with `in` condition on column `oz_jobs`.`retry_delay`.
 * @method $this whereRetryDelayIsNotIn(array $value)                      Filters rows with `not_in` condition on column `oz_jobs`.`retry_delay`.
 * @method $this wherePayloadIs(array $value)                              Filters rows with `eq` condition on column `oz_jobs`.`payload`.
 * @method $this wherePayloadIsNot(array $value)                           Filters rows with `neq` condition on column `oz_jobs`.`payload`.
 * @method $this wherePayloadIsLike(string $value)                         Filters rows with `like` condition on column `oz_jobs`.`payload`.
 * @method $this wherePayloadIsNotLike(string $value)                      Filters rows with `not_like` condition on column `oz_jobs`.`payload`.
 * @method $this whereResultIs(array $value)                               Filters rows with `eq` condition on column `oz_jobs`.`result`.
 * @method $this whereResultIsNot(array $value)                            Filters rows with `neq` condition on column `oz_jobs`.`result`.
 * @method $this whereResultIsLike(string $value)                          Filters rows with `like` condition on column `oz_jobs`.`result`.
 * @method $this whereResultIsNotLike(string $value)                       Filters rows with `not_like` condition on column `oz_jobs`.`result`.
 * @method $this whereErrorsIs(array $value)                               Filters rows with `eq` condition on column `oz_jobs`.`errors`.
 * @method $this whereErrorsIsNot(array $value)                            Filters rows with `neq` condition on column `oz_jobs`.`errors`.
 * @method $this whereErrorsIsLike(string $value)                          Filters rows with `like` condition on column `oz_jobs`.`errors`.
 * @method $this whereErrorsIsNotLike(string $value)                       Filters rows with `not_like` condition on column `oz_jobs`.`errors`.
 * @method $this whereLockedIs(bool $value)                                Filters rows with `eq` condition on column `oz_jobs`.`locked`.
 * @method $this whereLockedIsNot(bool $value)                             Filters rows with `neq` condition on column `oz_jobs`.`locked`.
 * @method $this whereIsNotLocked()                                        Filters rows with `is_false` condition on column `oz_jobs`.`locked`.
 * @method $this whereIsLocked()                                           Filters rows with `is_true` condition on column `oz_jobs`.`locked`.
 * @method $this whereStartedAtIs(float|int|string $value)                 Filters rows with `eq` condition on column `oz_jobs`.`started_at`.
 * @method $this whereStartedAtIsNot(float|int|string $value)              Filters rows with `neq` condition on column `oz_jobs`.`started_at`.
 * @method $this whereStartedAtIsLt(float|int|string $value)               Filters rows with `lt` condition on column `oz_jobs`.`started_at`.
 * @method $this whereStartedAtIsLte(float|int|string $value)              Filters rows with `lte` condition on column `oz_jobs`.`started_at`.
 * @method $this whereStartedAtIsGt(float|int|string $value)               Filters rows with `gt` condition on column `oz_jobs`.`started_at`.
 * @method $this whereStartedAtIsGte(float|int|string $value)              Filters rows with `gte` condition on column `oz_jobs`.`started_at`.
 * @method $this whereStartedAtIsLike(string $value)                       Filters rows with `like` condition on column `oz_jobs`.`started_at`.
 * @method $this whereStartedAtIsNotLike(string $value)                    Filters rows with `not_like` condition on column `oz_jobs`.`started_at`.
 * @method $this whereStartedAtIsNull()                                    Filters rows with `is_null` condition on column `oz_jobs`.`started_at`.
 * @method $this whereStartedAtIsNotNull()                                 Filters rows with `is_not_null` condition on column `oz_jobs`.`started_at`.
 * @method $this whereStartedAtIsIn(array $value)                          Filters rows with `in` condition on column `oz_jobs`.`started_at`.
 * @method $this whereStartedAtIsNotIn(array $value)                       Filters rows with `not_in` condition on column `oz_jobs`.`started_at`.
 * @method $this whereEndedAtIs(float|int|string $value)                   Filters rows with `eq` condition on column `oz_jobs`.`ended_at`.
 * @method $this whereEndedAtIsNot(float|int|string $value)                Filters rows with `neq` condition on column `oz_jobs`.`ended_at`.
 * @method $this whereEndedAtIsLt(float|int|string $value)                 Filters rows with `lt` condition on column `oz_jobs`.`ended_at`.
 * @method $this whereEndedAtIsLte(float|int|string $value)                Filters rows with `lte` condition on column `oz_jobs`.`ended_at`.
 * @method $this whereEndedAtIsGt(float|int|string $value)                 Filters rows with `gt` condition on column `oz_jobs`.`ended_at`.
 * @method $this whereEndedAtIsGte(float|int|string $value)                Filters rows with `gte` condition on column `oz_jobs`.`ended_at`.
 * @method $this whereEndedAtIsLike(string $value)                         Filters rows with `like` condition on column `oz_jobs`.`ended_at`.
 * @method $this whereEndedAtIsNotLike(string $value)                      Filters rows with `not_like` condition on column `oz_jobs`.`ended_at`.
 * @method $this whereEndedAtIsNull()                                      Filters rows with `is_null` condition on column `oz_jobs`.`ended_at`.
 * @method $this whereEndedAtIsNotNull()                                   Filters rows with `is_not_null` condition on column `oz_jobs`.`ended_at`.
 * @method $this whereEndedAtIsIn(array $value)                            Filters rows with `in` condition on column `oz_jobs`.`ended_at`.
 * @method $this whereEndedAtIsNotIn(array $value)                         Filters rows with `not_in` condition on column `oz_jobs`.`ended_at`.
 * @method $this whereCreatedAtIs(int|string $value)                       Filters rows with `eq` condition on column `oz_jobs`.`created_at`.
 * @method $this whereCreatedAtIsNot(int|string $value)                    Filters rows with `neq` condition on column `oz_jobs`.`created_at`.
 * @method $this whereCreatedAtIsLt(int|string $value)                     Filters rows with `lt` condition on column `oz_jobs`.`created_at`.
 * @method $this whereCreatedAtIsLte(int|string $value)                    Filters rows with `lte` condition on column `oz_jobs`.`created_at`.
 * @method $this whereCreatedAtIsGt(int|string $value)                     Filters rows with `gt` condition on column `oz_jobs`.`created_at`.
 * @method $this whereCreatedAtIsGte(int|string $value)                    Filters rows with `gte` condition on column `oz_jobs`.`created_at`.
 * @method $this whereCreatedAtIsLike(string $value)                       Filters rows with `like` condition on column `oz_jobs`.`created_at`.
 * @method $this whereCreatedAtIsNotLike(string $value)                    Filters rows with `not_like` condition on column `oz_jobs`.`created_at`.
 * @method $this whereCreatedAtIsIn(array $value)                          Filters rows with `in` condition on column `oz_jobs`.`created_at`.
 * @method $this whereCreatedAtIsNotIn(array $value)                       Filters rows with `not_in` condition on column `oz_jobs`.`created_at`.
 * @method $this whereUpdatedAtIs(int|string $value)                       Filters rows with `eq` condition on column `oz_jobs`.`updated_at`.
 * @method $this whereUpdatedAtIsNot(int|string $value)                    Filters rows with `neq` condition on column `oz_jobs`.`updated_at`.
 * @method $this whereUpdatedAtIsLt(int|string $value)                     Filters rows with `lt` condition on column `oz_jobs`.`updated_at`.
 * @method $this whereUpdatedAtIsLte(int|string $value)                    Filters rows with `lte` condition on column `oz_jobs`.`updated_at`.
 * @method $this whereUpdatedAtIsGt(int|string $value)                     Filters rows with `gt` condition on column `oz_jobs`.`updated_at`.
 * @method $this whereUpdatedAtIsGte(int|string $value)                    Filters rows with `gte` condition on column `oz_jobs`.`updated_at`.
 * @method $this whereUpdatedAtIsLike(string $value)                       Filters rows with `like` condition on column `oz_jobs`.`updated_at`.
 * @method $this whereUpdatedAtIsNotLike(string $value)                    Filters rows with `not_like` condition on column `oz_jobs`.`updated_at`.
 * @method $this whereUpdatedAtIsIn(array $value)                          Filters rows with `in` condition on column `oz_jobs`.`updated_at`.
 * @method $this whereUpdatedAtIsNotIn(array $value)                       Filters rows with `not_in` condition on column `oz_jobs`.`updated_at`.
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
}
