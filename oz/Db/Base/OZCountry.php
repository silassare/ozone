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
 * @psalm-suppress UndefinedThisPropertyFetch
 *
 * @property string $cc2          Getter for column `oz_countries`.`cc2`.
 * @property string $calling_code Getter for column `oz_countries`.`calling_code`.
 * @property string $name         Getter for column `oz_countries`.`name`.
 * @property string $name_real    Getter for column `oz_countries`.`name_real`.
 * @property array  $data         Getter for column `oz_countries`.`data`.
 * @property string $created_at   Getter for column `oz_countries`.`created_at`.
 * @property string $updated_at   Getter for column `oz_countries`.`updated_at`.
 * @property bool   $is_valid     Getter for column `oz_countries`.`is_valid`.
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
	public const COL_CREATED_AT   = 'country_created_at';
	public const COL_UPDATED_AT   = 'country_updated_at';
	public const COL_IS_VALID     = 'country_is_valid';

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
	public static function createInstance(bool $is_new = true, bool $strict = true): static
	{
		return new \OZONE\Core\Db\OZCountry($is_new, $strict);
	}

	/**
	 * Getter for column `oz_countries`.`cc2`.
	 *
	 * @return string
	 */
	public function getCc2(): string
	{
		return $this->{self::COL_CC2};
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
		$this->{self::COL_CC2} = $cc2;

		return $this;
	}

	/**
	 * Getter for column `oz_countries`.`calling_code`.
	 *
	 * @return string
	 */
	public function getCallingCode(): string
	{
		return $this->{self::COL_CALLING_CODE};
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
		$this->{self::COL_CALLING_CODE} = $calling_code;

		return $this;
	}

	/**
	 * Getter for column `oz_countries`.`name`.
	 *
	 * @return string
	 */
	public function getName(): string
	{
		return $this->{self::COL_NAME};
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
		$this->{self::COL_NAME} = $name;

		return $this;
	}

	/**
	 * Getter for column `oz_countries`.`name_real`.
	 *
	 * @return string
	 */
	public function getNameReal(): string
	{
		return $this->{self::COL_NAME_REAL};
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
		$this->{self::COL_NAME_REAL} = $name_real;

		return $this;
	}

	/**
	 * Getter for column `oz_countries`.`data`.
	 *
	 * @return array
	 */
	public function getData(): array
	{
		return $this->{self::COL_DATA};
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
		$this->{self::COL_DATA} = $data;

		return $this;
	}

	/**
	 * Getter for column `oz_countries`.`created_at`.
	 *
	 * @return string
	 */
	public function getCreatedAT(): string
	{
		return $this->{self::COL_CREATED_AT};
	}

	/**
	 * Setter for column `oz_countries`.`created_at`.
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
	 * Getter for column `oz_countries`.`updated_at`.
	 *
	 * @return string
	 */
	public function getUpdatedAT(): string
	{
		return $this->{self::COL_UPDATED_AT};
	}

	/**
	 * Setter for column `oz_countries`.`updated_at`.
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
	 * Getter for column `oz_countries`.`is_valid`.
	 *
	 * @return bool
	 */
	public function isValid(): bool
	{
		return $this->{self::COL_IS_VALID};
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
		$this->{self::COL_IS_VALID} = $is_valid;

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
	 * @throws \Gobl\CRUD\Exceptions\CRUDException
	 */
	public function getUsers(array $filters = [
	], ?int $max = null, int $offset = 0, array $order_by = [
	], ?int &$total = -1): array
	{
		return (new \OZONE\Core\Db\OZUsersController())->getAllRelatives(
			$this,
			$this->_oeb_table->getRelation('users'),
			$filters,
			$max,
			$offset,
			$order_by,
			$total
		);
	}
}
