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
 * Class OZRole.
 *
 * @psalm-suppress UndefinedThisPropertyFetch
 *
 * @property null|string $id         Getter for column `oz_roles`.`id`.
 * @property string      $user_id    Getter for column `oz_roles`.`user_id`.
 * @property string      $name       Getter for column `oz_roles`.`name`.
 * @property array       $data       Getter for column `oz_roles`.`data`.
 * @property string      $created_at Getter for column `oz_roles`.`created_at`.
 * @property string      $updated_at Getter for column `oz_roles`.`updated_at`.
 * @property bool        $is_valid   Getter for column `oz_roles`.`is_valid`.
 */
abstract class OZRole extends \Gobl\ORM\ORMEntity
{
	public const TABLE_NAME      = 'oz_roles';
	public const TABLE_NAMESPACE = 'OZONE\\Core\\Db';
	public const COL_ID          = 'role_id';
	public const COL_USER_ID     = 'role_user_id';
	public const COL_NAME        = 'role_name';
	public const COL_DATA        = 'role_data';
	public const COL_CREATED_AT  = 'role_created_at';
	public const COL_UPDATED_AT  = 'role_updated_at';
	public const COL_IS_VALID    = 'role_is_valid';

	/**
	 * OZRole constructor.
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
		return new \OZONE\Core\Db\OZRole($is_new, $strict);
	}

	/**
	 * Getter for column `oz_roles`.`id`.
	 *
	 * @return null|string
	 */
	public function getID(): string|null
	{
		return $this->{self::COL_ID};
	}

	/**
	 * Setter for column `oz_roles`.`id`.
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
	 * Getter for column `oz_roles`.`user_id`.
	 *
	 * @return string
	 */
	public function getUserID(): string
	{
		return $this->{self::COL_USER_ID};
	}

	/**
	 * Setter for column `oz_roles`.`user_id`.
	 *
	 * @param int|string $user_id
	 *
	 * @return static
	 */
	public function setUserID(string|int $user_id): static
	{
		$this->{self::COL_USER_ID} = $user_id;

		return $this;
	}

	/**
	 * Getter for column `oz_roles`.`name`.
	 *
	 * @return string
	 */
	public function getName(): string
	{
		return $this->{self::COL_NAME};
	}

	/**
	 * Setter for column `oz_roles`.`name`.
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
	 * Getter for column `oz_roles`.`data`.
	 *
	 * @return array
	 */
	public function getData(): array
	{
		return $this->{self::COL_DATA};
	}

	/**
	 * Setter for column `oz_roles`.`data`.
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
	 * Getter for column `oz_roles`.`created_at`.
	 *
	 * @return string
	 */
	public function getCreatedAT(): string
	{
		return $this->{self::COL_CREATED_AT};
	}

	/**
	 * Setter for column `oz_roles`.`created_at`.
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
	 * Getter for column `oz_roles`.`updated_at`.
	 *
	 * @return string
	 */
	public function getUpdatedAT(): string
	{
		return $this->{self::COL_UPDATED_AT};
	}

	/**
	 * Setter for column `oz_roles`.`updated_at`.
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
	 * Getter for column `oz_roles`.`is_valid`.
	 *
	 * @return bool
	 */
	public function isValid(): bool
	{
		return $this->{self::COL_IS_VALID};
	}

	/**
	 * Setter for column `oz_roles`.`is_valid`.
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
	 * OneToOne relation between `oz_roles` and `oz_users`.
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
