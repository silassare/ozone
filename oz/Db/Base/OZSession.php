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
 * @psalm-suppress UndefinedThisPropertyFetch
 *
 * @property string      $id                 Getter for column `oz_sessions`.`id`.
 * @property null|string $user_id            Getter for column `oz_sessions`.`user_id`.
 * @property string      $request_source_key Getter for column `oz_sessions`.`request_source_key`.
 * @property string      $expire             Getter for column `oz_sessions`.`expire`.
 * @property string      $last_seen          Getter for column `oz_sessions`.`last_seen`.
 * @property array       $data               Getter for column `oz_sessions`.`data`.
 * @property string      $created_at         Getter for column `oz_sessions`.`created_at`.
 * @property string      $updated_at         Getter for column `oz_sessions`.`updated_at`.
 * @property bool        $is_valid           Getter for column `oz_sessions`.`is_valid`.
 */
abstract class OZSession extends \Gobl\ORM\ORMEntity
{
	public const TABLE_NAME             = 'oz_sessions';
	public const TABLE_NAMESPACE        = 'OZONE\\Core\\Db';
	public const COL_ID                 = 'session_id';
	public const COL_USER_ID            = 'session_user_id';
	public const COL_REQUEST_SOURCE_KEY = 'session_request_source_key';
	public const COL_EXPIRE             = 'session_expire';
	public const COL_LAST_SEEN          = 'session_last_seen';
	public const COL_DATA               = 'session_data';
	public const COL_CREATED_AT         = 'session_created_at';
	public const COL_UPDATED_AT         = 'session_updated_at';
	public const COL_IS_VALID           = 'session_is_valid';

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
	public static function createInstance(bool $is_new = true, bool $strict = true): static
	{
		return new \OZONE\Core\Db\OZSession($is_new, $strict);
	}

	/**
	 * Getter for column `oz_sessions`.`id`.
	 *
	 * @return string
	 */
	public function getID(): string
	{
		return $this->{self::COL_ID};
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
		$this->{self::COL_ID} = $id;

		return $this;
	}

	/**
	 * Getter for column `oz_sessions`.`user_id`.
	 *
	 * @return null|string
	 */
	public function getUserID(): string|null
	{
		return $this->{self::COL_USER_ID};
	}

	/**
	 * Setter for column `oz_sessions`.`user_id`.
	 *
	 * @param null|int|string $user_id
	 *
	 * @return static
	 */
	public function setUserID(string|int|null $user_id): static
	{
		$this->{self::COL_USER_ID} = $user_id;

		return $this;
	}

	/**
	 * Getter for column `oz_sessions`.`request_source_key`.
	 *
	 * @return string
	 */
	public function getRequestSourceKey(): string
	{
		return $this->{self::COL_REQUEST_SOURCE_KEY};
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
		$this->{self::COL_REQUEST_SOURCE_KEY} = $request_source_key;

		return $this;
	}

	/**
	 * Getter for column `oz_sessions`.`expire`.
	 *
	 * @return string
	 */
	public function getExpire(): string
	{
		return $this->{self::COL_EXPIRE};
	}

	/**
	 * Setter for column `oz_sessions`.`expire`.
	 *
	 * @param int|string $expire
	 *
	 * @return static
	 */
	public function setExpire(string|int $expire): static
	{
		$this->{self::COL_EXPIRE} = $expire;

		return $this;
	}

	/**
	 * Getter for column `oz_sessions`.`last_seen`.
	 *
	 * @return string
	 */
	public function getLastSeen(): string
	{
		return $this->{self::COL_LAST_SEEN};
	}

	/**
	 * Setter for column `oz_sessions`.`last_seen`.
	 *
	 * @param int|string $last_seen
	 *
	 * @return static
	 */
	public function setLastSeen(string|int $last_seen): static
	{
		$this->{self::COL_LAST_SEEN} = $last_seen;

		return $this;
	}

	/**
	 * Getter for column `oz_sessions`.`data`.
	 *
	 * @return array
	 */
	public function getData(): array
	{
		return $this->{self::COL_DATA};
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
		$this->{self::COL_DATA} = $data;

		return $this;
	}

	/**
	 * Getter for column `oz_sessions`.`created_at`.
	 *
	 * @return string
	 */
	public function getCreatedAT(): string
	{
		return $this->{self::COL_CREATED_AT};
	}

	/**
	 * Setter for column `oz_sessions`.`created_at`.
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
	 * Getter for column `oz_sessions`.`updated_at`.
	 *
	 * @return string
	 */
	public function getUpdatedAT(): string
	{
		return $this->{self::COL_UPDATED_AT};
	}

	/**
	 * Setter for column `oz_sessions`.`updated_at`.
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

	/**
	 * Getter for column `oz_sessions`.`is_valid`.
	 *
	 * @return bool
	 */
	public function isValid(): bool
	{
		return $this->{self::COL_IS_VALID};
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
		$this->{self::COL_IS_VALID} = $is_valid;

		return $this;
	}

	/**
	 * ManyToOne relation between `oz_sessions` and `oz_users`.
	 *
	 * @return ?\OZONE\Core\Db\OZUser
	 *
	 * @throws \Gobl\CRUD\Exceptions\CRUDException
	 */
	public function getUser(): ?\OZONE\Core\Db\OZUser
	{
		return (new \OZONE\Core\Db\OZUsersController())->getRelative(
			$this,
			$this->_oeb_table->getRelation('user')
		);
	}
}
