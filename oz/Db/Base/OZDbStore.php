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
 * Class OZDbStore.
 *
 * @property null|string $id         Getter for column `oz_db_stores`.`id`.
 * @property string      $group      Getter for column `oz_db_stores`.`group`.
 * @property string      $key        Getter for column `oz_db_stores`.`key`.
 * @property null|string $value      Getter for column `oz_db_stores`.`value`.
 * @property string      $label      Getter for column `oz_db_stores`.`label`.
 * @property array       $data       Getter for column `oz_db_stores`.`data`.
 * @property string      $created_at Getter for column `oz_db_stores`.`created_at`.
 * @property string      $updated_at Getter for column `oz_db_stores`.`updated_at`.
 * @property bool        $is_valid   Getter for column `oz_db_stores`.`is_valid`.
 */
abstract class OZDbStore extends \Gobl\ORM\ORMEntity
{
	public const TABLE_NAME      = 'oz_db_stores';
	public const TABLE_NAMESPACE = 'OZONE\\Core\\Db';
	public const COL_ID          = 'store_id';
	public const COL_GROUP       = 'store_group';
	public const COL_KEY         = 'store_key';
	public const COL_VALUE       = 'store_value';
	public const COL_LABEL       = 'store_label';
	public const COL_DATA        = 'store_data';
	public const COL_CREATED_AT  = 'store_created_at';
	public const COL_UPDATED_AT  = 'store_updated_at';
	public const COL_IS_VALID    = 'store_is_valid';

	/**
	 * OZDbStore constructor.
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
		return new \OZONE\Core\Db\OZDbStore($is_new, $strict);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @return \OZONE\Core\Db\OZDbStoresCrud
	 */
	public static function crud(): \OZONE\Core\Db\OZDbStoresCrud
	{
		return \OZONE\Core\Db\OZDbStoresCrud::new();
	}

	/**
	 * {@inheritDoc}
	 *
	 * @return \OZONE\Core\Db\OZDbStoresController
	 */
	public static function ctrl(): \OZONE\Core\Db\OZDbStoresController
	{
		return \OZONE\Core\Db\OZDbStoresController::new();
	}

	/**
	 * {@inheritDoc}
	 *
	 * @return \OZONE\Core\Db\OZDbStoresQuery
	 */
	public static function qb(): \OZONE\Core\Db\OZDbStoresQuery
	{
		return \OZONE\Core\Db\OZDbStoresQuery::new();
	}

	/**
	 * {@inheritDoc}
	 *
	 * @return \OZONE\Core\Db\OZDbStoresResults
	 */
	public static function results(\Gobl\DBAL\Queries\QBSelect $query): \OZONE\Core\Db\OZDbStoresResults
	{
		return \OZONE\Core\Db\OZDbStoresResults::new($query);
	}

	/**
	 * {@inheritDoc}
	 */
	public static function table(): \Gobl\DBAL\Table
	{
		return \Gobl\ORM\ORM::table(static::TABLE_NAMESPACE, static::TABLE_NAME);
	}

	/**
	 * Getter for column `oz_db_stores`.`id`.
	 *
	 * @return null|string
	 */
	public function getID(): string|null
	{
		return $this->id;
	}

	/**
	 * Setter for column `oz_db_stores`.`id`.
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
	 * Getter for column `oz_db_stores`.`group`.
	 *
	 * @return string
	 */
	public function getGroup(): string
	{
		return $this->group;
	}

	/**
	 * Setter for column `oz_db_stores`.`group`.
	 *
	 * @param string $group
	 *
	 * @return static
	 */
	public function setGroup(string $group): static
	{
		$this->group = $group;

		return $this;
	}

	/**
	 * Getter for column `oz_db_stores`.`key`.
	 *
	 * @return string
	 */
	public function getKey(): string
	{
		return $this->key;
	}

	/**
	 * Setter for column `oz_db_stores`.`key`.
	 *
	 * @param string $key
	 *
	 * @return static
	 */
	public function setKey(string $key): static
	{
		$this->key = $key;

		return $this;
	}

	/**
	 * Getter for column `oz_db_stores`.`value`.
	 *
	 * @return null|string
	 */
	public function getValue(): string|null
	{
		return $this->value;
	}

	/**
	 * Setter for column `oz_db_stores`.`value`.
	 *
	 * @param null|string $value
	 *
	 * @return static
	 */
	public function setValue(string|null $value): static
	{
		$this->value = $value;

		return $this;
	}

	/**
	 * Getter for column `oz_db_stores`.`label`.
	 *
	 * @return string
	 */
	public function getLabel(): string
	{
		return $this->label;
	}

	/**
	 * Setter for column `oz_db_stores`.`label`.
	 *
	 * @param string $label
	 *
	 * @return static
	 */
	public function setLabel(string $label): static
	{
		$this->label = $label;

		return $this;
	}

	/**
	 * Getter for column `oz_db_stores`.`data`.
	 *
	 * @return array
	 */
	public function getData(): array
	{
		return $this->data;
	}

	/**
	 * Setter for column `oz_db_stores`.`data`.
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
	 * Getter for column `oz_db_stores`.`created_at`.
	 *
	 * @return string
	 */
	public function getCreatedAT(): string
	{
		return $this->created_at;
	}

	/**
	 * Setter for column `oz_db_stores`.`created_at`.
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
	 * Getter for column `oz_db_stores`.`updated_at`.
	 *
	 * @return string
	 */
	public function getUpdatedAT(): string
	{
		return $this->updated_at;
	}

	/**
	 * Setter for column `oz_db_stores`.`updated_at`.
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
	 * Getter for column `oz_db_stores`.`is_valid`.
	 *
	 * @return bool
	 */
	public function isValid(): bool
	{
		return $this->is_valid;
	}

	/**
	 * Setter for column `oz_db_stores`.`is_valid`.
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
}
