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

use Gobl\DBAL\Queries\QBSelect;
use Gobl\DBAL\Table;
use Gobl\Exceptions\GoblException;
use Gobl\ORM\ORM;
use Gobl\ORM\ORMEntity;
use OZONE\Core\Db\OZCountry;
use OZONE\Core\Db\OZFile;
use OZONE\Core\Db\OZRole;
use OZONE\Core\Db\OZSession;
use OZONE\Core\Db\OZUser as OZUserReal;
use OZONE\Core\Db\OZUsersController;
use OZONE\Core\Db\OZUsersCrud;
use OZONE\Core\Db\OZUsersQuery;
use OZONE\Core\Db\OZUsersResults;

/**
 * Class OZUser.
 *
 * @property null|string $id         Getter for column `oz_users`.`id`.
 * @property null|string $phone      Getter for column `oz_users`.`phone`.
 * @property string      $email      Getter for column `oz_users`.`email`.
 * @property string      $pass       Getter for column `oz_users`.`pass`.
 * @property string      $name       Getter for column `oz_users`.`name`.
 * @property string      $gender     Getter for column `oz_users`.`gender`.
 * @property string      $birth_date Getter for column `oz_users`.`birth_date`.
 * @property null|string $pic        Getter for column `oz_users`.`pic`.
 * @property array       $data       Getter for column `oz_users`.`data`.
 * @property bool        $is_valid   Getter for column `oz_users`.`is_valid`.
 * @property string      $created_at Getter for column `oz_users`.`created_at`.
 * @property string      $updated_at Getter for column `oz_users`.`updated_at`.
 * @property bool        $deleted    Getter for column `oz_users`.`deleted`.
 * @property null|string $deleted_at Getter for column `oz_users`.`deleted_at`.
 * @property string      $cc2        Getter for column `oz_users`.`cc2`.
 */
abstract class OZUser extends ORMEntity
{
	public const TABLE_NAME      = 'oz_users';
	public const TABLE_NAMESPACE = 'OZONE\\Core\\Db';
	public const COL_ID          = 'user_id';
	public const COL_PHONE       = 'user_phone';
	public const COL_EMAIL       = 'user_email';
	public const COL_PASS        = 'user_pass';
	public const COL_NAME        = 'user_name';
	public const COL_GENDER      = 'user_gender';
	public const COL_BIRTH_DATE  = 'user_birth_date';
	public const COL_PIC         = 'user_pic';
	public const COL_DATA        = 'user_data';
	public const COL_IS_VALID    = 'user_is_valid';
	public const COL_CREATED_AT  = 'user_created_at';
	public const COL_UPDATED_AT  = 'user_updated_at';
	public const COL_DELETED     = 'user_deleted';
	public const COL_DELETED_AT  = 'user_deleted_at';
	public const COL_CC2         = 'user_cc2';

	/**
	 * OZUser constructor.
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
		return new OZUserReal($is_new, $strict);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @return OZUsersCrud
	 */
	public static function crud(): OZUsersCrud
	{
		return OZUsersCrud::new();
	}

	/**
	 * {@inheritDoc}
	 *
	 * @return OZUsersController
	 */
	public static function ctrl(): OZUsersController
	{
		return OZUsersController::new();
	}

	/**
	 * {@inheritDoc}
	 *
	 * @return OZUsersQuery
	 */
	public static function qb(): OZUsersQuery
	{
		return OZUsersQuery::new();
	}

	/**
	 * {@inheritDoc}
	 *
	 * @return OZUsersResults
	 */
	public static function results(QBSelect $query): OZUsersResults
	{
		return OZUsersResults::new($query);
	}

	/**
	 * {@inheritDoc}
	 */
	public static function table(): Table
	{
		return ORM::table(static::TABLE_NAMESPACE, static::TABLE_NAME);
	}

	/**
	 * Getter for column `oz_users`.`id`.
	 *
	 * @return null|string
	 */
	public function getID(): null|string
	{
		return $this->id;
	}

	/**
	 * Setter for column `oz_users`.`id`.
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
	 * Getter for column `oz_users`.`phone`.
	 *
	 * @return null|string
	 */
	public function getPhone(): null|string
	{
		return $this->phone;
	}

	/**
	 * Setter for column `oz_users`.`phone`.
	 *
	 * @param null|string $phone
	 *
	 * @return static
	 */
	public function setPhone(null|string $phone): static
	{
		$this->phone = $phone;

		return $this;
	}

	/**
	 * Getter for column `oz_users`.`email`.
	 *
	 * @return string
	 */
	public function getEmail(): string
	{
		return $this->email;
	}

	/**
	 * Setter for column `oz_users`.`email`.
	 *
	 * @param string $email
	 *
	 * @return static
	 */
	public function setEmail(string $email): static
	{
		$this->email = $email;

		return $this;
	}

	/**
	 * Getter for column `oz_users`.`pass`.
	 *
	 * @return string
	 */
	public function getPass(): string
	{
		return $this->pass;
	}

	/**
	 * Setter for column `oz_users`.`pass`.
	 *
	 * @param string $pass
	 *
	 * @return static
	 */
	public function setPass(string $pass): static
	{
		$this->pass = $pass;

		return $this;
	}

	/**
	 * Getter for column `oz_users`.`name`.
	 *
	 * @return string
	 */
	public function getName(): string
	{
		return $this->name;
	}

	/**
	 * Setter for column `oz_users`.`name`.
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
	 * Getter for column `oz_users`.`gender`.
	 *
	 * @return string
	 */
	public function getGender(): string
	{
		return $this->gender;
	}

	/**
	 * Setter for column `oz_users`.`gender`.
	 *
	 * @param string $gender
	 *
	 * @return static
	 */
	public function setGender(string $gender): static
	{
		$this->gender = $gender;

		return $this;
	}

	/**
	 * Getter for column `oz_users`.`birth_date`.
	 *
	 * @return string
	 */
	public function getBirthDate(): string
	{
		return $this->birth_date;
	}

	/**
	 * Setter for column `oz_users`.`birth_date`.
	 *
	 * @param int|string $birth_date
	 *
	 * @return static
	 */
	public function setBirthDate(int|string $birth_date): static
	{
		$this->birth_date = $birth_date;

		return $this;
	}

	/**
	 * Getter for column `oz_users`.`pic`.
	 *
	 * @return null|string
	 */
	public function getPic(): null|string
	{
		return $this->pic;
	}

	/**
	 * Setter for column `oz_users`.`pic`.
	 *
	 * @param null|string $pic
	 *
	 * @return static
	 */
	public function setPic(null|string $pic): static
	{
		$this->pic = $pic;

		return $this;
	}

	/**
	 * Getter for column `oz_users`.`data`.
	 *
	 * @return array
	 */
	public function getData(): array
	{
		return $this->data;
	}

	/**
	 * Setter for column `oz_users`.`data`.
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
	 * Getter for column `oz_users`.`is_valid`.
	 *
	 * @return bool
	 */
	public function isValid(): bool
	{
		return $this->is_valid;
	}

	/**
	 * Setter for column `oz_users`.`is_valid`.
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
	 * Getter for column `oz_users`.`created_at`.
	 *
	 * @return string
	 */
	public function getCreatedAT(): string
	{
		return $this->created_at;
	}

	/**
	 * Setter for column `oz_users`.`created_at`.
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
	 * Getter for column `oz_users`.`updated_at`.
	 *
	 * @return string
	 */
	public function getUpdatedAT(): string
	{
		return $this->updated_at;
	}

	/**
	 * Setter for column `oz_users`.`updated_at`.
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
	 * Getter for column `oz_users`.`deleted`.
	 *
	 * @return bool
	 */
	public function isDeleted(): bool
	{
		return $this->deleted;
	}

	/**
	 * Setter for column `oz_users`.`deleted`.
	 *
	 * @param bool $deleted
	 *
	 * @return static
	 */
	public function setDeleted(bool $deleted): static
	{
		$this->deleted = $deleted;

		return $this;
	}

	/**
	 * Getter for column `oz_users`.`deleted_at`.
	 *
	 * @return null|string
	 */
	public function getDeletedAT(): null|string
	{
		return $this->deleted_at;
	}

	/**
	 * Setter for column `oz_users`.`deleted_at`.
	 *
	 * @param null|int|string $deleted_at
	 *
	 * @return static
	 */
	public function setDeletedAT(null|int|string $deleted_at): static
	{
		$this->deleted_at = $deleted_at;

		return $this;
	}

	/**
	 * Getter for column `oz_users`.`cc2`.
	 *
	 * @return string
	 */
	public function getCc2(): string
	{
		return $this->cc2;
	}

	/**
	 * Setter for column `oz_users`.`cc2`.
	 *
	 * @param string $cc2
	 *
	 * @return static
	 */
	public function setCc2(string $cc2): static
	{
		$this->cc2 = $cc2;

		return $this;
	}

	/**
	 * OneToMany relation between `oz_users` and `oz_roles`.
	 *
	 * @param array    $filters  the row filters
	 * @param null|int $max      maximum row to retrieve
	 * @param int      $offset   first row offset
	 * @param array    $order_by order by rules
	 * @param null|int $total    total rows without limit
	 *
	 * @return \OZONE\Core\Db\OZRole[]
	 *
	 * @throws GoblException
	 */
	public function getRoles(array $filters =  [
	], ?int $max = null, int $offset = 0, array $order_by =  [
	], ?int &$total = -1): array
	{
		return OZRole::ctrl()->getAllRelatives(
			$this,
			static::table()->getRelation('roles'),
			$filters,
			$max,
			$offset,
			$order_by,
			$total
		);
	}

	/**
	 * OneToMany relation between `oz_users` and `oz_files`.
	 *
	 * @param array    $filters  the row filters
	 * @param null|int $max      maximum row to retrieve
	 * @param int      $offset   first row offset
	 * @param array    $order_by order by rules
	 * @param null|int $total    total rows without limit
	 *
	 * @return \OZONE\Core\Db\OZFile[]
	 *
	 * @throws GoblException
	 */
	public function getFiles(array $filters =  [
	], ?int $max = null, int $offset = 0, array $order_by =  [
	], ?int &$total = -1): array
	{
		return OZFile::ctrl()->getAllRelatives(
			$this,
			static::table()->getRelation('files'),
			$filters,
			$max,
			$offset,
			$order_by,
			$total
		);
	}

	/**
	 * OneToMany relation between `oz_users` and `oz_sessions`.
	 *
	 * @param array    $filters  the row filters
	 * @param null|int $max      maximum row to retrieve
	 * @param int      $offset   first row offset
	 * @param array    $order_by order by rules
	 * @param null|int $total    total rows without limit
	 *
	 * @return \OZONE\Core\Db\OZSession[]
	 *
	 * @throws GoblException
	 */
	public function getSessions(array $filters =  [
	], ?int $max = null, int $offset = 0, array $order_by =  [
	], ?int &$total = -1): array
	{
		return OZSession::ctrl()->getAllRelatives(
			$this,
			static::table()->getRelation('sessions'),
			$filters,
			$max,
			$offset,
			$order_by,
			$total
		);
	}

	/**
	 * ManyToOne relation between `oz_users` and `oz_countries`.
	 *
	 * @return ?\OZONE\Core\Db\OZCountry
	 *
	 * @throws GoblException
	 */
	public function getCountry(): ?OZCountry
	{
		return OZCountry::ctrl()->getRelative(
			$this,
			static::table()->getRelation('country')
		);
	}
}
