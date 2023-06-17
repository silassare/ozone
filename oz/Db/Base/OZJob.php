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
 * @psalm-suppress UndefinedThisPropertyFetch
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
	public static function createInstance(bool $is_new = true, bool $strict = true): static
	{
		return new \OZONE\Core\Db\OZJob($is_new, $strict);
	}

	/**
	 * Getter for column `oz_jobs`.`id`.
	 *
	 * @return null|string
	 */
	public function getID(): string|null
	{
		return $this->{self::COL_ID};
	}

	/**
	 * Setter for column `oz_jobs`.`id`.
	 *
	 * @param null|int|string $id
	 *
	 * @return static
	 */
	public function setID(string|int|null $id): static
	{
		$this->{self::COL_ID} = $id;

		return $this;
	}

	/**
	 * Getter for column `oz_jobs`.`ref`.
	 *
	 * @return string
	 */
	public function getRef(): string
	{
		return $this->{self::COL_REF};
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
		$this->{self::COL_REF} = $ref;

		return $this;
	}

	/**
	 * Getter for column `oz_jobs`.`state`.
	 *
	 * @return \OZONE\Core\Queue\JobState
	 */
	public function getState(): \OZONE\Core\Queue\JobState
	{
		return $this->{self::COL_STATE};
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
		$this->{self::COL_STATE} = $state;

		return $this;
	}

	/**
	 * Getter for column `oz_jobs`.`queue`.
	 *
	 * @return string
	 */
	public function getQueue(): string
	{
		return $this->{self::COL_QUEUE};
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
		$this->{self::COL_QUEUE} = $queue;

		return $this;
	}

	/**
	 * Getter for column `oz_jobs`.`name`.
	 *
	 * @return string
	 */
	public function getName(): string
	{
		return $this->{self::COL_NAME};
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
		$this->{self::COL_NAME} = $name;

		return $this;
	}

	/**
	 * Getter for column `oz_jobs`.`worker`.
	 *
	 * @return string
	 */
	public function getWorker(): string
	{
		return $this->{self::COL_WORKER};
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
		$this->{self::COL_WORKER} = $worker;

		return $this;
	}

	/**
	 * Getter for column `oz_jobs`.`priority`.
	 *
	 * @return int
	 */
	public function getPriority(): int
	{
		return $this->{self::COL_PRIORITY};
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
		$this->{self::COL_PRIORITY} = $priority;

		return $this;
	}

	/**
	 * Getter for column `oz_jobs`.`try_count`.
	 *
	 * @return int
	 */
	public function getTryCount(): int
	{
		return $this->{self::COL_TRY_COUNT};
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
		$this->{self::COL_TRY_COUNT} = $try_count;

		return $this;
	}

	/**
	 * Getter for column `oz_jobs`.`retry_max`.
	 *
	 * @return int
	 */
	public function getRetryMax(): int
	{
		return $this->{self::COL_RETRY_MAX};
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
		$this->{self::COL_RETRY_MAX} = $retry_max;

		return $this;
	}

	/**
	 * Getter for column `oz_jobs`.`retry_delay`.
	 *
	 * @return int
	 */
	public function getRetryDelay(): int
	{
		return $this->{self::COL_RETRY_DELAY};
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
		$this->{self::COL_RETRY_DELAY} = $retry_delay;

		return $this;
	}

	/**
	 * Getter for column `oz_jobs`.`payload`.
	 *
	 * @return array
	 */
	public function getPayload(): array
	{
		return $this->{self::COL_PAYLOAD};
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
		$this->{self::COL_PAYLOAD} = $payload;

		return $this;
	}

	/**
	 * Getter for column `oz_jobs`.`result`.
	 *
	 * @return array
	 */
	public function getResult(): array
	{
		return $this->{self::COL_RESULT};
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
		$this->{self::COL_RESULT} = $result;

		return $this;
	}

	/**
	 * Getter for column `oz_jobs`.`errors`.
	 *
	 * @return array
	 */
	public function getErrors(): array
	{
		return $this->{self::COL_ERRORS};
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
		$this->{self::COL_ERRORS} = $errors;

		return $this;
	}

	/**
	 * Getter for column `oz_jobs`.`locked`.
	 *
	 * @return bool
	 */
	public function isLocked(): bool
	{
		return $this->{self::COL_LOCKED};
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
		$this->{self::COL_LOCKED} = $locked;

		return $this;
	}

	/**
	 * Getter for column `oz_jobs`.`started_at`.
	 *
	 * @return null|string
	 */
	public function getStartedAT(): string|null
	{
		return $this->{self::COL_STARTED_AT};
	}

	/**
	 * Setter for column `oz_jobs`.`started_at`.
	 *
	 * @param null|float|int|string $started_at
	 *
	 * @return static
	 */
	public function setStartedAT(string|float|int|null $started_at): static
	{
		$this->{self::COL_STARTED_AT} = $started_at;

		return $this;
	}

	/**
	 * Getter for column `oz_jobs`.`ended_at`.
	 *
	 * @return null|string
	 */
	public function getEndedAT(): string|null
	{
		return $this->{self::COL_ENDED_AT};
	}

	/**
	 * Setter for column `oz_jobs`.`ended_at`.
	 *
	 * @param null|float|int|string $ended_at
	 *
	 * @return static
	 */
	public function setEndedAT(string|float|int|null $ended_at): static
	{
		$this->{self::COL_ENDED_AT} = $ended_at;

		return $this;
	}

	/**
	 * Getter for column `oz_jobs`.`created_at`.
	 *
	 * @return string
	 */
	public function getCreatedAT(): string
	{
		return $this->{self::COL_CREATED_AT};
	}

	/**
	 * Setter for column `oz_jobs`.`created_at`.
	 *
	 * @param int|string $created_at
	 *
	 * @return static
	 */
	public function setCreatedAT(string|int $created_at): static
	{
		$this->{self::COL_CREATED_AT} = $created_at;

		return $this;
	}

	/**
	 * Getter for column `oz_jobs`.`updated_at`.
	 *
	 * @return string
	 */
	public function getUpdatedAT(): string
	{
		return $this->{self::COL_UPDATED_AT};
	}

	/**
	 * Setter for column `oz_jobs`.`updated_at`.
	 *
	 * @param int|string $updated_at
	 *
	 * @return static
	 */
	public function setUpdatedAT(string|int $updated_at): static
	{
		$this->{self::COL_UPDATED_AT} = $updated_at;

		return $this;
	}
}
