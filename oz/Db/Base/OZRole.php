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
	public static function new(bool $is_new = true, bool $strict = true): static
	{
		return new \OZONE\Core\Db\OZRole($is_new, $strict);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @return \OZONE\Core\Db\OZRolesCrud
	 */
	public static function crud(): \OZONE\Core\Db\OZRolesCrud
	{
		return \OZONE\Core\Db\OZRolesCrud::new();
	}

	/**
	 * {@inheritDoc}
	 *
	 * @return \OZONE\Core\Db\OZRolesController
	 */
	public static function ctrl(): \OZONE\Core\Db\OZRolesController
	{
		return \OZONE\Core\Db\OZRolesController::new();
	}

	/**
	 * {@inheritDoc}
	 *
	 * @return \OZONE\Core\Db\OZRolesQuery
	 */
	public static function qb(): \OZONE\Core\Db\OZRolesQuery
	{
		return \OZONE\Core\Db\OZRolesQuery::new();
	}

	/**
	 * {@inheritDoc}
	 *
	 * @return \OZONE\Core\Db\OZRolesResults
	 */
	public static function results(\Gobl\DBAL\Queries\QBSelect $query): \OZONE\Core\Db\OZRolesResults
	{
		return \OZONE\Core\Db\OZRolesResults::new($query);
	}

	/**
	 * {@inheritDoc}
	 */
	public static function table(): \Gobl\DBAL\Table
	{
		return \Gobl\ORM\ORM::table(static::TABLE_NAMESPACE, static::TABLE_NAME);
	}

	/**
	 * Getter for column `oz_roles`.`id`.
	 *
	 * @return null|string
	 */
	public function getID(): string|null
	{
		return $this->id;
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
		$this->id = $id;

		return $this;
	}

	/**
	 * Getter for column `oz_roles`.`user_id`.
	 *
	 * @return string
	 */
	public function getUserID(): string
	{
		return $this->user_id;
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
		$this->user_id = $user_id;

		return $this;
	}

	/**
	 * Getter for column `oz_roles`.`name`.
	 *
	 * @return string
	 */
	public function getName(): string
	{
		return $this->name;
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
		$this->name = $name;

		return $this;
	}

	/**
	 * Getter for column `oz_roles`.`data`.
	 *
	 * @return array
	 */
	public function getData(): array
	{
		return $this->data;
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
		$this->data = $data;

		return $this;
	}

	/**
	 * Getter for column `oz_roles`.`created_at`.
	 *
	 * @return string
	 */
	public function getCreatedAT(): string
	{
		return $this->created_at;
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
		$this->created_at = $created_at;

		return $this;
	}

	/**
	 * Getter for column `oz_roles`.`updated_at`.
	 *
	 * @return string
	 */
	public function getUpdatedAT(): string
	{
		return $this->updated_at;
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
		$this->updated_at = $updated_at;

		return $this;
	}

	/**
	 * Getter for column `oz_roles`.`is_valid`.
	 *
	 * @return bool
	 */
	public function isValid(): bool
	{
		return $this->is_valid;
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
		$this->is_valid = $is_valid;

		return $this;
	}

	/**
	 * OneToOne relation between `oz_roles` and `oz_users`.
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
