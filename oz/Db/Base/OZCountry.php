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
 * Class OZCountry.
 *
 * @property string      $cc2          Getter for column `oz_countries`.`cc2`.
 * @property string      $calling_code Getter for column `oz_countries`.`calling_code`.
 * @property string      $name         Getter for column `oz_countries`.`name`.
 * @property string      $name_real    Getter for column `oz_countries`.`name_real`.
 * @property array       $data         Getter for column `oz_countries`.`data`.
 * @property bool        $is_valid     Getter for column `oz_countries`.`is_valid`.
 * @property string      $created_at   Getter for column `oz_countries`.`created_at`.
 * @property string      $updated_at   Getter for column `oz_countries`.`updated_at`.
 * @property bool        $deleted      Getter for column `oz_countries`.`deleted`.
 * @property null|string $deleted_at   Getter for column `oz_countries`.`deleted_at`.
 */
abstract class OZCountry extends \Gobl\ORM\ORMEntity
{
	public const TABLE_NAME       = 'oz_countries';
	public const TABLE_NAMESPACE  = 'OZONE\\Core\\Db';
	public const COL_CC2          = 'country_cc2';
	public const COL_CALLING_CODE = 'country_calling_code';
	public const COL_NAME         = 'country_name';
	public const COL_NAME_REAL    = 'country_name_real';
	public const COL_DATA         = 'country_data';
	public const COL_IS_VALID     = 'country_is_valid';
	public const COL_CREATED_AT   = 'country_created_at';
	public const COL_UPDATED_AT   = 'country_updated_at';
	public const COL_DELETED      = 'country_deleted';
	public const COL_DELETED_AT   = 'country_deleted_at';

	/**
	 * OZCountry constructor.
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
		return new \OZONE\Core\Db\OZCountry($is_new, $strict);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @return \OZONE\Core\Db\OZCountriesCrud
	 */
	public static function crud(): \OZONE\Core\Db\OZCountriesCrud
	{
		return \OZONE\Core\Db\OZCountriesCrud::new();
	}

	/**
	 * {@inheritDoc}
	 *
	 * @return \OZONE\Core\Db\OZCountriesController
	 */
	public static function ctrl(): \OZONE\Core\Db\OZCountriesController
	{
		return \OZONE\Core\Db\OZCountriesController::new();
	}

	/**
	 * {@inheritDoc}
	 *
	 * @return \OZONE\Core\Db\OZCountriesQuery
	 */
	public static function qb(): \OZONE\Core\Db\OZCountriesQuery
	{
		return \OZONE\Core\Db\OZCountriesQuery::new();
	}

	/**
	 * {@inheritDoc}
	 *
	 * @return \OZONE\Core\Db\OZCountriesResults
	 */
	public static function results(\Gobl\DBAL\Queries\QBSelect $query): \OZONE\Core\Db\OZCountriesResults
	{
		return \OZONE\Core\Db\OZCountriesResults::new($query);
	}

	/**
	 * {@inheritDoc}
	 */
	public static function table(): \Gobl\DBAL\Table
	{
		return \Gobl\ORM\ORM::table(static::TABLE_NAMESPACE, static::TABLE_NAME);
	}

	/**
	 * Getter for column `oz_countries`.`cc2`.
	 *
	 * @return string
	 */
	public function getCc2(): string
	{
		return $this->cc2;
	}

	/**
	 * Setter for column `oz_countries`.`cc2`.
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
	 * Getter for column `oz_countries`.`calling_code`.
	 *
	 * @return string
	 */
	public function getCallingCode(): string
	{
		return $this->calling_code;
	}

	/**
	 * Setter for column `oz_countries`.`calling_code`.
	 *
	 * @param string $calling_code
	 *
	 * @return static
	 */
	public function setCallingCode(string $calling_code): static
	{
		$this->calling_code = $calling_code;

		return $this;
	}

	/**
	 * Getter for column `oz_countries`.`name`.
	 *
	 * @return string
	 */
	public function getName(): string
	{
		return $this->name;
	}

	/**
	 * Setter for column `oz_countries`.`name`.
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
	 * Getter for column `oz_countries`.`name_real`.
	 *
	 * @return string
	 */
	public function getNameReal(): string
	{
		return $this->name_real;
	}

	/**
	 * Setter for column `oz_countries`.`name_real`.
	 *
	 * @param string $name_real
	 *
	 * @return static
	 */
	public function setNameReal(string $name_real): static
	{
		$this->name_real = $name_real;

		return $this;
	}

	/**
	 * Getter for column `oz_countries`.`data`.
	 *
	 * @return array
	 */
	public function getData(): array
	{
		return $this->data;
	}

	/**
	 * Setter for column `oz_countries`.`data`.
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
	 * Getter for column `oz_countries`.`is_valid`.
	 *
	 * @return bool
	 */
	public function isValid(): bool
	{
		return $this->is_valid;
	}

	/**
	 * Setter for column `oz_countries`.`is_valid`.
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
	 * Getter for column `oz_countries`.`created_at`.
	 *
	 * @return string
	 */
	public function getCreatedAT(): string
	{
		return $this->created_at;
	}

	/**
	 * Setter for column `oz_countries`.`created_at`.
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
	 * Getter for column `oz_countries`.`updated_at`.
	 *
	 * @return string
	 */
	public function getUpdatedAT(): string
	{
		return $this->updated_at;
	}

	/**
	 * Setter for column `oz_countries`.`updated_at`.
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
	 * Getter for column `oz_countries`.`deleted`.
	 *
	 * @return bool
	 */
	public function isDeleted(): bool
	{
		return $this->deleted;
	}

	/**
	 * Setter for column `oz_countries`.`deleted`.
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
	 * Getter for column `oz_countries`.`deleted_at`.
	 *
	 * @return null|string
	 */
	public function getDeletedAT(): null|string
	{
		return $this->deleted_at;
	}

	/**
	 * Setter for column `oz_countries`.`deleted_at`.
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
	 * OneToMany relation between `oz_countries` and `oz_users`.
	 *
	 * @param array    $filters  the row filters
	 * @param null|int $max      maximum row to retrieve
	 * @param int      $offset   first row offset
	 * @param array    $order_by order by rules
	 * @param null|int $total    total rows without limit
	 *
	 * @return \OZONE\Core\Db\OZUser[]
	 *
	 * @throws \Gobl\Exceptions\GoblException
	 */
	public function getCitizens(array $filters =  [
	], ?int $max = null, int $offset = 0, array $order_by =  [
	], ?int &$total = -1): array
	{
		return \OZONE\Core\Db\OZUser::ctrl()->getAllRelatives(
			$this,
			static::table()->getRelation('citizens'),
			$filters,
			$max,
			$offset,
			$order_by,
			$total
		);
	}
}
