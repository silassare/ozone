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
 * Class OZSession.
 *
 * @property string      $id                 Getter for column `oz_sessions`.`id`.
 * @property string      $request_source_key Getter for column `oz_sessions`.`request_source_key`.
 * @property string      $expire             Getter for column `oz_sessions`.`expire`.
 * @property string      $last_seen          Getter for column `oz_sessions`.`last_seen`.
 * @property array       $data               Getter for column `oz_sessions`.`data`.
 * @property bool        $is_valid           Getter for column `oz_sessions`.`is_valid`.
 * @property string      $created_at         Getter for column `oz_sessions`.`created_at`.
 * @property string      $updated_at         Getter for column `oz_sessions`.`updated_at`.
 * @property null|string $user_id            Getter for column `oz_sessions`.`user_id`.
 */
abstract class OZSession extends \Gobl\ORM\ORMEntity
{
	public const TABLE_NAME             = 'oz_sessions';
	public const TABLE_NAMESPACE        = 'OZONE\\Core\\Db';
	public const COL_ID                 = 'session_id';
	public const COL_REQUEST_SOURCE_KEY = 'session_request_source_key';
	public const COL_EXPIRE             = 'session_expire';
	public const COL_LAST_SEEN          = 'session_last_seen';
	public const COL_DATA               = 'session_data';
	public const COL_IS_VALID           = 'session_is_valid';
	public const COL_CREATED_AT         = 'session_created_at';
	public const COL_UPDATED_AT         = 'session_updated_at';
	public const COL_USER_ID            = 'session_user_id';

	/**
	 * OZSession constructor.
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
		return new \OZONE\Core\Db\OZSession($is_new, $strict);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @return \OZONE\Core\Db\OZSessionsCrud
	 */
	public static function crud(): \OZONE\Core\Db\OZSessionsCrud
	{
		return \OZONE\Core\Db\OZSessionsCrud::new();
	}

	/**
	 * {@inheritDoc}
	 *
	 * @return \OZONE\Core\Db\OZSessionsController
	 */
	public static function ctrl(): \OZONE\Core\Db\OZSessionsController
	{
		return \OZONE\Core\Db\OZSessionsController::new();
	}

	/**
	 * {@inheritDoc}
	 *
	 * @return \OZONE\Core\Db\OZSessionsQuery
	 */
	public static function qb(): \OZONE\Core\Db\OZSessionsQuery
	{
		return \OZONE\Core\Db\OZSessionsQuery::new();
	}

	/**
	 * {@inheritDoc}
	 *
	 * @return \OZONE\Core\Db\OZSessionsResults
	 */
	public static function results(\Gobl\DBAL\Queries\QBSelect $query): \OZONE\Core\Db\OZSessionsResults
	{
		return \OZONE\Core\Db\OZSessionsResults::new($query);
	}

	/**
	 * {@inheritDoc}
	 */
	public static function table(): \Gobl\DBAL\Table
	{
		return \Gobl\ORM\ORM::table(static::TABLE_NAMESPACE, static::TABLE_NAME);
	}

	/**
	 * Getter for column `oz_sessions`.`id`.
	 *
	 * @return string
	 */
	public function getID(): string
	{
		return $this->id;
	}

	/**
	 * Setter for column `oz_sessions`.`id`.
	 *
	 * @param string $id
	 *
	 * @return static
	 */
	public function setID(string $id): static
	{
		$this->id = $id;

		return $this;
	}

	/**
	 * Getter for column `oz_sessions`.`request_source_key`.
	 *
	 * @return string
	 */
	public function getRequestSourceKey(): string
	{
		return $this->request_source_key;
	}

	/**
	 * Setter for column `oz_sessions`.`request_source_key`.
	 *
	 * @param string $request_source_key
	 *
	 * @return static
	 */
	public function setRequestSourceKey(string $request_source_key): static
	{
		$this->request_source_key = $request_source_key;

		return $this;
	}

	/**
	 * Getter for column `oz_sessions`.`expire`.
	 *
	 * @return string
	 */
	public function getExpire(): string
	{
		return $this->expire;
	}

	/**
	 * Setter for column `oz_sessions`.`expire`.
	 *
	 * @param int|string $expire
	 *
	 * @return static
	 */
	public function setExpire(int|string $expire): static
	{
		$this->expire = $expire;

		return $this;
	}

	/**
	 * Getter for column `oz_sessions`.`last_seen`.
	 *
	 * @return string
	 */
	public function getLastSeen(): string
	{
		return $this->last_seen;
	}

	/**
	 * Setter for column `oz_sessions`.`last_seen`.
	 *
	 * @param int|string $last_seen
	 *
	 * @return static
	 */
	public function setLastSeen(int|string $last_seen): static
	{
		$this->last_seen = $last_seen;

		return $this;
	}

	/**
	 * Getter for column `oz_sessions`.`data`.
	 *
	 * @return array
	 */
	public function getData(): array
	{
		return $this->data;
	}

	/**
	 * Setter for column `oz_sessions`.`data`.
	 *
	 * @param array $data
	 *
	 * @return static
	 */
	public function setData(array $data): static
	{
		$this->data = $data;

		return $this;
	}

	/**
	 * Getter for column `oz_sessions`.`is_valid`.
	 *
	 * @return bool
	 */
	public function isValid(): bool
	{
		return $this->is_valid;
	}

	/**
	 * Setter for column `oz_sessions`.`is_valid`.
	 *
	 * @param bool $is_valid
	 *
	 * @return static
	 */
	public function setISValid(bool $is_valid): static
	{
		$this->is_valid = $is_valid;

		return $this;
	}

	/**
	 * Getter for column `oz_sessions`.`created_at`.
	 *
	 * @return string
	 */
	public function getCreatedAT(): string
	{
		return $this->created_at;
	}

	/**
	 * Setter for column `oz_sessions`.`created_at`.
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
	 * Getter for column `oz_sessions`.`updated_at`.
	 *
	 * @return string
	 */
	public function getUpdatedAT(): string
	{
		return $this->updated_at;
	}

	/**
	 * Setter for column `oz_sessions`.`updated_at`.
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

	/**
	 * Getter for column `oz_sessions`.`user_id`.
	 *
	 * @return null|string
	 */
	public function getUserID(): null|string
	{
		return $this->user_id;
	}

	/**
	 * Setter for column `oz_sessions`.`user_id`.
	 *
	 * @param null|int|string $user_id
	 *
	 * @return static
	 */
	public function setUserID(null|int|string $user_id): static
	{
		$this->user_id = $user_id;

		return $this;
	}

	/**
	 * ManyToOne relation between `oz_sessions` and `oz_users`.
	 *
	 * @return ?\OZONE\Core\Db\OZUser
	 *
	 * @throws \Gobl\Exceptions\GoblException
	 */
	public function getUser(): ?\OZONE\Core\Db\OZUser
	{
		return \OZONE\Core\Db\OZUser::ctrl()->getRelative(
			$this,
			static::table()->getRelation('user')
		);
	}
}
