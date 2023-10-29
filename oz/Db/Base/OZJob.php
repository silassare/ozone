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
 * Class OZJob.
 *
 * @property null|string                $id          Getter for column `oz_jobs`.`id`.
 * @property string                     $ref         Getter for column `oz_jobs`.`ref`.
 * @property \OZONE\Core\Queue\JobState $state       Getter for column `oz_jobs`.`state`.
 * @property string                     $queue       Getter for column `oz_jobs`.`queue`.
 * @property string                     $name        Getter for column `oz_jobs`.`name`.
 * @property string                     $worker      Getter for column `oz_jobs`.`worker`.
 * @property int                        $priority    Getter for column `oz_jobs`.`priority`.
 * @property int                        $try_count   Getter for column `oz_jobs`.`try_count`.
 * @property int                        $retry_max   Getter for column `oz_jobs`.`retry_max`.
 * @property int                        $retry_delay Getter for column `oz_jobs`.`retry_delay`.
 * @property array                      $payload     Getter for column `oz_jobs`.`payload`.
 * @property array                      $result      Getter for column `oz_jobs`.`result`.
 * @property array                      $errors      Getter for column `oz_jobs`.`errors`.
 * @property bool                       $locked      Getter for column `oz_jobs`.`locked`.
 * @property null|string                $started_at  Getter for column `oz_jobs`.`started_at`.
 * @property null|string                $ended_at    Getter for column `oz_jobs`.`ended_at`.
 * @property string                     $created_at  Getter for column `oz_jobs`.`created_at`.
 * @property string                     $updated_at  Getter for column `oz_jobs`.`updated_at`.
 */
abstract class OZJob extends \Gobl\ORM\ORMEntity
{
	public const TABLE_NAME      = 'oz_jobs';
	public const TABLE_NAMESPACE = 'OZONE\\Core\\Db';
	public const COL_ID          = 'job_id';
	public const COL_REF         = 'job_ref';
	public const COL_STATE       = 'job_state';
	public const COL_QUEUE       = 'job_queue';
	public const COL_NAME        = 'job_name';
	public const COL_WORKER      = 'job_worker';
	public const COL_PRIORITY    = 'job_priority';
	public const COL_TRY_COUNT   = 'job_try_count';
	public const COL_RETRY_MAX   = 'job_retry_max';
	public const COL_RETRY_DELAY = 'job_retry_delay';
	public const COL_PAYLOAD     = 'job_payload';
	public const COL_RESULT      = 'job_result';
	public const COL_ERRORS      = 'job_errors';
	public const COL_LOCKED      = 'job_locked';
	public const COL_STARTED_AT  = 'job_started_at';
	public const COL_ENDED_AT    = 'job_ended_at';
	public const COL_CREATED_AT  = 'job_created_at';
	public const COL_UPDATED_AT  = 'job_updated_at';

	/**
	 * OZJob constructor.
	 *
	 * @param bool $is_new true for new entity false for entity fetched
	 *                     from the database, default is true
	 * @param bool $strict Enable/disable strict mode
	 */
	public function __construct(bool $is_new = true, bool $strict = true)
	{
		parent::__construct(
			self::TABLE_NAMESPACE,
			self::TABLE_NAME,
			$is_new,
			$strict
		);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @return static
	 */
	public static function new(bool $is_new = true, bool $strict = true): static
	{
		return new \OZONE\Core\Db\OZJob($is_new, $strict);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @return \OZONE\Core\Db\OZJobsCrud
	 */
	public static function crud(): \OZONE\Core\Db\OZJobsCrud
	{
		return \OZONE\Core\Db\OZJobsCrud::new();
	}

	/**
	 * {@inheritDoc}
	 *
	 * @return \OZONE\Core\Db\OZJobsController
	 */
	public static function ctrl(): \OZONE\Core\Db\OZJobsController
	{
		return \OZONE\Core\Db\OZJobsController::new();
	}

	/**
	 * {@inheritDoc}
	 *
	 * @return \OZONE\Core\Db\OZJobsQuery
	 */
	public static function qb(): \OZONE\Core\Db\OZJobsQuery
	{
		return \OZONE\Core\Db\OZJobsQuery::new();
	}

	/**
	 * {@inheritDoc}
	 *
	 * @return \OZONE\Core\Db\OZJobsResults
	 */
	public static function results(\Gobl\DBAL\Queries\QBSelect $query): \OZONE\Core\Db\OZJobsResults
	{
		return \OZONE\Core\Db\OZJobsResults::new($query);
	}

	/**
	 * {@inheritDoc}
	 */
	public static function table(): \Gobl\DBAL\Table
	{
		return \Gobl\ORM\ORM::table(static::TABLE_NAMESPACE, static::TABLE_NAME);
	}

	/**
	 * Getter for column `oz_jobs`.`id`.
	 *
	 * @return null|string
	 */
	public function getID(): null|string
	{
		return $this->id;
	}

	/**
	 * Setter for column `oz_jobs`.`id`.
	 *
	 * @param null|int|string $id
	 *
	 * @return static
	 */
	public function setID(null|int|string $id): static
	{
		$this->id = $id;

		return $this;
	}

	/**
	 * Getter for column `oz_jobs`.`ref`.
	 *
	 * @return string
	 */
	public function getRef(): string
	{
		return $this->ref;
	}

	/**
	 * Setter for column `oz_jobs`.`ref`.
	 *
	 * @param string $ref
	 *
	 * @return static
	 */
	public function setRef(string $ref): static
	{
		$this->ref = $ref;

		return $this;
	}

	/**
	 * Getter for column `oz_jobs`.`state`.
	 *
	 * @return \OZONE\Core\Queue\JobState
	 */
	public function getState(): \OZONE\Core\Queue\JobState
	{
		return $this->state;
	}

	/**
	 * Setter for column `oz_jobs`.`state`.
	 *
	 * @param \OZONE\Core\Queue\JobState|string $state
	 *
	 * @return static
	 */
	public function setState(\OZONE\Core\Queue\JobState|string $state): static
	{
		$this->state = $state;

		return $this;
	}

	/**
	 * Getter for column `oz_jobs`.`queue`.
	 *
	 * @return string
	 */
	public function getQueue(): string
	{
		return $this->queue;
	}

	/**
	 * Setter for column `oz_jobs`.`queue`.
	 *
	 * @param string $queue
	 *
	 * @return static
	 */
	public function setQueue(string $queue): static
	{
		$this->queue = $queue;

		return $this;
	}

	/**
	 * Getter for column `oz_jobs`.`name`.
	 *
	 * @return string
	 */
	public function getName(): string
	{
		return $this->name;
	}

	/**
	 * Setter for column `oz_jobs`.`name`.
	 *
	 * @param string $name
	 *
	 * @return static
	 */
	public function setName(string $name): static
	{
		$this->name = $name;

		return $this;
	}

	/**
	 * Getter for column `oz_jobs`.`worker`.
	 *
	 * @return string
	 */
	public function getWorker(): string
	{
		return $this->worker;
	}

	/**
	 * Setter for column `oz_jobs`.`worker`.
	 *
	 * @param string $worker
	 *
	 * @return static
	 */
	public function setWorker(string $worker): static
	{
		$this->worker = $worker;

		return $this;
	}

	/**
	 * Getter for column `oz_jobs`.`priority`.
	 *
	 * @return int
	 */
	public function getPriority(): int
	{
		return $this->priority;
	}

	/**
	 * Setter for column `oz_jobs`.`priority`.
	 *
	 * @param int $priority
	 *
	 * @return static
	 */
	public function setPriority(int $priority): static
	{
		$this->priority = $priority;

		return $this;
	}

	/**
	 * Getter for column `oz_jobs`.`try_count`.
	 *
	 * @return int
	 */
	public function getTryCount(): int
	{
		return $this->try_count;
	}

	/**
	 * Setter for column `oz_jobs`.`try_count`.
	 *
	 * @param int $try_count
	 *
	 * @return static
	 */
	public function setTryCount(int $try_count): static
	{
		$this->try_count = $try_count;

		return $this;
	}

	/**
	 * Getter for column `oz_jobs`.`retry_max`.
	 *
	 * @return int
	 */
	public function getRetryMax(): int
	{
		return $this->retry_max;
	}

	/**
	 * Setter for column `oz_jobs`.`retry_max`.
	 *
	 * @param int $retry_max
	 *
	 * @return static
	 */
	public function setRetryMax(int $retry_max): static
	{
		$this->retry_max = $retry_max;

		return $this;
	}

	/**
	 * Getter for column `oz_jobs`.`retry_delay`.
	 *
	 * @return int
	 */
	public function getRetryDelay(): int
	{
		return $this->retry_delay;
	}

	/**
	 * Setter for column `oz_jobs`.`retry_delay`.
	 *
	 * @param int $retry_delay
	 *
	 * @return static
	 */
	public function setRetryDelay(int $retry_delay): static
	{
		$this->retry_delay = $retry_delay;

		return $this;
	}

	/**
	 * Getter for column `oz_jobs`.`payload`.
	 *
	 * @return array
	 */
	public function getPayload(): array
	{
		return $this->payload;
	}

	/**
	 * Setter for column `oz_jobs`.`payload`.
	 *
	 * @param array $payload
	 *
	 * @return static
	 */
	public function setPayload(array $payload): static
	{
		$this->payload = $payload;

		return $this;
	}

	/**
	 * Getter for column `oz_jobs`.`result`.
	 *
	 * @return array
	 */
	public function getResult(): array
	{
		return $this->result;
	}

	/**
	 * Setter for column `oz_jobs`.`result`.
	 *
	 * @param array $result
	 *
	 * @return static
	 */
	public function setResult(array $result): static
	{
		$this->result = $result;

		return $this;
	}

	/**
	 * Getter for column `oz_jobs`.`errors`.
	 *
	 * @return array
	 */
	public function getErrors(): array
	{
		return $this->errors;
	}

	/**
	 * Setter for column `oz_jobs`.`errors`.
	 *
	 * @param array $errors
	 *
	 * @return static
	 */
	public function setErrors(array $errors): static
	{
		$this->errors = $errors;

		return $this;
	}

	/**
	 * Getter for column `oz_jobs`.`locked`.
	 *
	 * @return bool
	 */
	public function isLocked(): bool
	{
		return $this->locked;
	}

	/**
	 * Setter for column `oz_jobs`.`locked`.
	 *
	 * @param bool $locked
	 *
	 * @return static
	 */
	public function setLocked(bool $locked): static
	{
		$this->locked = $locked;

		return $this;
	}

	/**
	 * Getter for column `oz_jobs`.`started_at`.
	 *
	 * @return null|string
	 */
	public function getStartedAT(): null|string
	{
		return $this->started_at;
	}

	/**
	 * Setter for column `oz_jobs`.`started_at`.
	 *
	 * @param null|float|int|string $started_at
	 *
	 * @return static
	 */
	public function setStartedAT(null|float|int|string $started_at): static
	{
		$this->started_at = $started_at;

		return $this;
	}

	/**
	 * Getter for column `oz_jobs`.`ended_at`.
	 *
	 * @return null|string
	 */
	public function getEndedAT(): null|string
	{
		return $this->ended_at;
	}

	/**
	 * Setter for column `oz_jobs`.`ended_at`.
	 *
	 * @param null|float|int|string $ended_at
	 *
	 * @return static
	 */
	public function setEndedAT(null|float|int|string $ended_at): static
	{
		$this->ended_at = $ended_at;

		return $this;
	}

	/**
	 * Getter for column `oz_jobs`.`created_at`.
	 *
	 * @return string
	 */
	public function getCreatedAT(): string
	{
		return $this->created_at;
	}

	/**
	 * Setter for column `oz_jobs`.`created_at`.
	 *
	 * @param int|string $created_at
	 *
	 * @return static
	 */
	public function setCreatedAT(int|string $created_at): static
	{
		$this->created_at = $created_at;

		return $this;
	}

	/**
	 * Getter for column `oz_jobs`.`updated_at`.
	 *
	 * @return string
	 */
	public function getUpdatedAT(): string
	{
		return $this->updated_at;
	}

	/**
	 * Setter for column `oz_jobs`.`updated_at`.
	 *
	 * @param int|string $updated_at
	 *
	 * @return static
	 */
	public function setUpdatedAT(int|string $updated_at): static
	{
		$this->updated_at = $updated_at;

		return $this;
	}
}
