<?php
/**
 * Auto generated file
 * 
 * WARNING: please don't edit.
 * 
 * Proudly With: gobl v2.0.0
 * Time: 2023-05-09T07:41:19+00:00
 */
declare(strict_types=1);

namespace OZONE\OZ\Db\Base;

/**
 * Class OZCountry.
 * 
 * @property-read string $cc2 Getter for column `oz_countries`.`cc2`.
 * @property-read string $code Getter for column `oz_countries`.`code`.
 * @property-read string $name Getter for column `oz_countries`.`name`.
 * @property-read string $name_real Getter for column `oz_countries`.`name_real`.
 * @property-read array $data Getter for column `oz_countries`.`data`.
 * @property-read string $created_at Getter for column `oz_countries`.`created_at`.
 * @property-read string $updated_at Getter for column `oz_countries`.`updated_at`.
 * @property-read bool $is_valid Getter for column `oz_countries`.`is_valid`.
 */
abstract class OZCountry extends \Gobl\ORM\ORMEntity
{
	public const TABLE_NAME = 'oz_countries';
	public const TABLE_NAMESPACE = 'OZONE\\OZ\\Db';
	public const COL_CC2 = 'country_cc2';
	public const COL_CODE = 'country_code';
	public const COL_NAME = 'country_name';
	public const COL_NAME_REAL = 'country_name_real';
	public const COL_DATA = 'country_data';
	public const COL_CREATED_AT = 'country_created_at';
	public const COL_UPDATED_AT = 'country_updated_at';
	public const COL_IS_VALID = 'country_is_valid';
	/**
	 * OZCountry constructor.
	 * 
	 * @param bool $is_new true for new entity false for entity fetched
	 *                      from the database, default is true
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
	 * @inheritDoc
	 * 
	 * @return static
	 */
	public static function createInstance(bool $is_new = true, bool $strict = true): static
	{
		return new \OZONE\OZ\Db\OZCountry($is_new, $strict);
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
	 * Getter for column `oz_countries`.`code`.
	 * 
	 * @return string
	 */
	public function getCode(): string
	{
		return $this->{self::COL_CODE};
	}

	/**
	 * Setter for column `oz_countries`.`code`.
	 * 
	 * @param string $code
	 * 
	 * @return static
	 */
	public function setCode(string $code): static
	{
		$this->{self::COL_CODE} = $code;

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
	 * @param string|int $created_at
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
	 * @param string|int $updated_at
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
	 * @return \OZONE\OZ\Db\OZUser[]
	 */
	public function getUsers(array $filters = array (
	), ?int $max = NULL, int $offset = 0, array $order_by = array (
	), ?int &$total = -1): array
	{
		
		$filters_bundle = $this->buildRelationFilter($getters, $filters);

		if (null === $filters_bundle) {
			return [];
		}

		return (new \OZONE\OZ\Db\OZUsersController())->getAllItems($filters_bundle, $max, $offset, $order_by, $total);
	}
}
