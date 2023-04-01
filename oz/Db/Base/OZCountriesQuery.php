<?php
/**
 * Auto generated file
 * 
 * WARNING: please don't edit.
 * 
 * Proudly With: gobl v2.0.0
 * Time: 2023-03-31T23:29:45+00:00
 */
declare(strict_types=1);

namespace OZONE\OZ\Db\Base;

/**
 * Class OZCountriesQuery.
 * 
 * @method \OZONE\OZ\Db\OZCountriesResults find(?int $max = null, int $offset = 0, array $order_by = [])
 */
abstract class OZCountriesQuery extends \Gobl\ORM\ORMTableQuery
{
	/**
	 * OZCountriesQuery constructor.
	 */
	public function __construct()
	{
		parent::__construct(
			\OZONE\OZ\Db\OZCountry::TABLE_NAMESPACE,
			\OZONE\OZ\Db\OZCountry::TABLE_NAME
		);

	}

	/**
	 * @inheritDoc
	 * 
	 * @return static
	 */
	public static function createInstance(): static
	{
		return new \OZONE\OZ\Db\OZCountriesQuery;
	}

	/**
	 * {@inheritDoc}
	 */
	public function subGroup(): static
	{
		$instance              = new static();
		$instance->qb          = $this->qb;
		$instance->filters     = $this->filters->subGroup();
		$instance->table_alias = $this->table_alias;

		return $instance;
	}

	/**
	 * Filters rows with `eq` condition on column `oz_countries`.`cc2`.
	 * 
	 * @param string $value the filter value
	 * 
	 * @return static
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereCc2Is(string $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('eq'),
			\OZONE\OZ\Db\OZCountry::COL_CC2,
			$value
		);
	}

	/**
	 * Filters rows with `neq` condition on column `oz_countries`.`cc2`.
	 * 
	 * @param string $value the filter value
	 * 
	 * @return static
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereCc2IsNot(string $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('neq'),
			\OZONE\OZ\Db\OZCountry::COL_CC2,
			$value
		);
	}

	/**
	 * Filters rows with `lt` condition on column `oz_countries`.`cc2`.
	 * 
	 * @param string $value the filter value
	 * 
	 * @return static
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereCc2IsLt(string $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('lt'),
			\OZONE\OZ\Db\OZCountry::COL_CC2,
			$value
		);
	}

	/**
	 * Filters rows with `lte` condition on column `oz_countries`.`cc2`.
	 * 
	 * @param string $value the filter value
	 * 
	 * @return static
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereCc2IsLte(string $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('lte'),
			\OZONE\OZ\Db\OZCountry::COL_CC2,
			$value
		);
	}

	/**
	 * Filters rows with `gt` condition on column `oz_countries`.`cc2`.
	 * 
	 * @param string $value the filter value
	 * 
	 * @return static
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereCc2IsGt(string $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('gt'),
			\OZONE\OZ\Db\OZCountry::COL_CC2,
			$value
		);
	}

	/**
	 * Filters rows with `gte` condition on column `oz_countries`.`cc2`.
	 * 
	 * @param string $value the filter value
	 * 
	 * @return static
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereCc2IsGte(string $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('gte'),
			\OZONE\OZ\Db\OZCountry::COL_CC2,
			$value
		);
	}

	/**
	 * Filters rows with `like` condition on column `oz_countries`.`cc2`.
	 * 
	 * @param string $value the filter value
	 * 
	 * @return static
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereCc2IsLike(string $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('like'),
			\OZONE\OZ\Db\OZCountry::COL_CC2,
			$value
		);
	}

	/**
	 * Filters rows with `not_like` condition on column `oz_countries`.`cc2`.
	 * 
	 * @param string $value the filter value
	 * 
	 * @return static
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereCc2IsNotLike(string $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('not_like'),
			\OZONE\OZ\Db\OZCountry::COL_CC2,
			$value
		);
	}

	/**
	 * Filters rows with `in` condition on column `oz_countries`.`cc2`.
	 * 
	 * @param array $value the filter value
	 * 
	 * @return static
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereCc2IsIn(array $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('in'),
			\OZONE\OZ\Db\OZCountry::COL_CC2,
			$value
		);
	}

	/**
	 * Filters rows with `not_in` condition on column `oz_countries`.`cc2`.
	 * 
	 * @param array $value the filter value
	 * 
	 * @return static
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereCc2IsNotIn(array $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('not_in'),
			\OZONE\OZ\Db\OZCountry::COL_CC2,
			$value
		);
	}

	/**
	 * Filters rows with `eq` condition on column `oz_countries`.`code`.
	 * 
	 * @param string $value the filter value
	 * 
	 * @return static
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereCodeIs(string $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('eq'),
			\OZONE\OZ\Db\OZCountry::COL_CODE,
			$value
		);
	}

	/**
	 * Filters rows with `neq` condition on column `oz_countries`.`code`.
	 * 
	 * @param string $value the filter value
	 * 
	 * @return static
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereCodeIsNot(string $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('neq'),
			\OZONE\OZ\Db\OZCountry::COL_CODE,
			$value
		);
	}

	/**
	 * Filters rows with `lt` condition on column `oz_countries`.`code`.
	 * 
	 * @param string $value the filter value
	 * 
	 * @return static
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereCodeIsLt(string $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('lt'),
			\OZONE\OZ\Db\OZCountry::COL_CODE,
			$value
		);
	}

	/**
	 * Filters rows with `lte` condition on column `oz_countries`.`code`.
	 * 
	 * @param string $value the filter value
	 * 
	 * @return static
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereCodeIsLte(string $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('lte'),
			\OZONE\OZ\Db\OZCountry::COL_CODE,
			$value
		);
	}

	/**
	 * Filters rows with `gt` condition on column `oz_countries`.`code`.
	 * 
	 * @param string $value the filter value
	 * 
	 * @return static
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereCodeIsGt(string $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('gt'),
			\OZONE\OZ\Db\OZCountry::COL_CODE,
			$value
		);
	}

	/**
	 * Filters rows with `gte` condition on column `oz_countries`.`code`.
	 * 
	 * @param string $value the filter value
	 * 
	 * @return static
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereCodeIsGte(string $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('gte'),
			\OZONE\OZ\Db\OZCountry::COL_CODE,
			$value
		);
	}

	/**
	 * Filters rows with `like` condition on column `oz_countries`.`code`.
	 * 
	 * @param string $value the filter value
	 * 
	 * @return static
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereCodeIsLike(string $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('like'),
			\OZONE\OZ\Db\OZCountry::COL_CODE,
			$value
		);
	}

	/**
	 * Filters rows with `not_like` condition on column `oz_countries`.`code`.
	 * 
	 * @param string $value the filter value
	 * 
	 * @return static
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereCodeIsNotLike(string $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('not_like'),
			\OZONE\OZ\Db\OZCountry::COL_CODE,
			$value
		);
	}

	/**
	 * Filters rows with `in` condition on column `oz_countries`.`code`.
	 * 
	 * @param array $value the filter value
	 * 
	 * @return static
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereCodeIsIn(array $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('in'),
			\OZONE\OZ\Db\OZCountry::COL_CODE,
			$value
		);
	}

	/**
	 * Filters rows with `not_in` condition on column `oz_countries`.`code`.
	 * 
	 * @param array $value the filter value
	 * 
	 * @return static
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereCodeIsNotIn(array $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('not_in'),
			\OZONE\OZ\Db\OZCountry::COL_CODE,
			$value
		);
	}

	/**
	 * Filters rows with `eq` condition on column `oz_countries`.`name`.
	 * 
	 * @param string $value the filter value
	 * 
	 * @return static
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereNameIs(string $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('eq'),
			\OZONE\OZ\Db\OZCountry::COL_NAME,
			$value
		);
	}

	/**
	 * Filters rows with `neq` condition on column `oz_countries`.`name`.
	 * 
	 * @param string $value the filter value
	 * 
	 * @return static
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereNameIsNot(string $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('neq'),
			\OZONE\OZ\Db\OZCountry::COL_NAME,
			$value
		);
	}

	/**
	 * Filters rows with `lt` condition on column `oz_countries`.`name`.
	 * 
	 * @param string $value the filter value
	 * 
	 * @return static
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereNameIsLt(string $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('lt'),
			\OZONE\OZ\Db\OZCountry::COL_NAME,
			$value
		);
	}

	/**
	 * Filters rows with `lte` condition on column `oz_countries`.`name`.
	 * 
	 * @param string $value the filter value
	 * 
	 * @return static
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereNameIsLte(string $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('lte'),
			\OZONE\OZ\Db\OZCountry::COL_NAME,
			$value
		);
	}

	/**
	 * Filters rows with `gt` condition on column `oz_countries`.`name`.
	 * 
	 * @param string $value the filter value
	 * 
	 * @return static
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereNameIsGt(string $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('gt'),
			\OZONE\OZ\Db\OZCountry::COL_NAME,
			$value
		);
	}

	/**
	 * Filters rows with `gte` condition on column `oz_countries`.`name`.
	 * 
	 * @param string $value the filter value
	 * 
	 * @return static
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereNameIsGte(string $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('gte'),
			\OZONE\OZ\Db\OZCountry::COL_NAME,
			$value
		);
	}

	/**
	 * Filters rows with `like` condition on column `oz_countries`.`name`.
	 * 
	 * @param string $value the filter value
	 * 
	 * @return static
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereNameIsLike(string $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('like'),
			\OZONE\OZ\Db\OZCountry::COL_NAME,
			$value
		);
	}

	/**
	 * Filters rows with `not_like` condition on column `oz_countries`.`name`.
	 * 
	 * @param string $value the filter value
	 * 
	 * @return static
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereNameIsNotLike(string $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('not_like'),
			\OZONE\OZ\Db\OZCountry::COL_NAME,
			$value
		);
	}

	/**
	 * Filters rows with `in` condition on column `oz_countries`.`name`.
	 * 
	 * @param array $value the filter value
	 * 
	 * @return static
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereNameIsIn(array $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('in'),
			\OZONE\OZ\Db\OZCountry::COL_NAME,
			$value
		);
	}

	/**
	 * Filters rows with `not_in` condition on column `oz_countries`.`name`.
	 * 
	 * @param array $value the filter value
	 * 
	 * @return static
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereNameIsNotIn(array $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('not_in'),
			\OZONE\OZ\Db\OZCountry::COL_NAME,
			$value
		);
	}

	/**
	 * Filters rows with `eq` condition on column `oz_countries`.`name_real`.
	 * 
	 * @param string $value the filter value
	 * 
	 * @return static
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereNameRealIs(string $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('eq'),
			\OZONE\OZ\Db\OZCountry::COL_NAME_REAL,
			$value
		);
	}

	/**
	 * Filters rows with `neq` condition on column `oz_countries`.`name_real`.
	 * 
	 * @param string $value the filter value
	 * 
	 * @return static
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereNameRealIsNot(string $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('neq'),
			\OZONE\OZ\Db\OZCountry::COL_NAME_REAL,
			$value
		);
	}

	/**
	 * Filters rows with `lt` condition on column `oz_countries`.`name_real`.
	 * 
	 * @param string $value the filter value
	 * 
	 * @return static
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereNameRealIsLt(string $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('lt'),
			\OZONE\OZ\Db\OZCountry::COL_NAME_REAL,
			$value
		);
	}

	/**
	 * Filters rows with `lte` condition on column `oz_countries`.`name_real`.
	 * 
	 * @param string $value the filter value
	 * 
	 * @return static
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereNameRealIsLte(string $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('lte'),
			\OZONE\OZ\Db\OZCountry::COL_NAME_REAL,
			$value
		);
	}

	/**
	 * Filters rows with `gt` condition on column `oz_countries`.`name_real`.
	 * 
	 * @param string $value the filter value
	 * 
	 * @return static
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereNameRealIsGt(string $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('gt'),
			\OZONE\OZ\Db\OZCountry::COL_NAME_REAL,
			$value
		);
	}

	/**
	 * Filters rows with `gte` condition on column `oz_countries`.`name_real`.
	 * 
	 * @param string $value the filter value
	 * 
	 * @return static
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereNameRealIsGte(string $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('gte'),
			\OZONE\OZ\Db\OZCountry::COL_NAME_REAL,
			$value
		);
	}

	/**
	 * Filters rows with `like` condition on column `oz_countries`.`name_real`.
	 * 
	 * @param string $value the filter value
	 * 
	 * @return static
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereNameRealIsLike(string $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('like'),
			\OZONE\OZ\Db\OZCountry::COL_NAME_REAL,
			$value
		);
	}

	/**
	 * Filters rows with `not_like` condition on column `oz_countries`.`name_real`.
	 * 
	 * @param string $value the filter value
	 * 
	 * @return static
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereNameRealIsNotLike(string $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('not_like'),
			\OZONE\OZ\Db\OZCountry::COL_NAME_REAL,
			$value
		);
	}

	/**
	 * Filters rows with `in` condition on column `oz_countries`.`name_real`.
	 * 
	 * @param array $value the filter value
	 * 
	 * @return static
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereNameRealIsIn(array $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('in'),
			\OZONE\OZ\Db\OZCountry::COL_NAME_REAL,
			$value
		);
	}

	/**
	 * Filters rows with `not_in` condition on column `oz_countries`.`name_real`.
	 * 
	 * @param array $value the filter value
	 * 
	 * @return static
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereNameRealIsNotIn(array $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('not_in'),
			\OZONE\OZ\Db\OZCountry::COL_NAME_REAL,
			$value
		);
	}

	/**
	 * Filters rows with `eq` condition on column `oz_countries`.`data`.
	 * 
	 * @param array $value the filter value
	 * 
	 * @return static
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereDataIs(array $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('eq'),
			\OZONE\OZ\Db\OZCountry::COL_DATA,
			$value
		);
	}

	/**
	 * Filters rows with `neq` condition on column `oz_countries`.`data`.
	 * 
	 * @param array $value the filter value
	 * 
	 * @return static
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereDataIsNot(array $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('neq'),
			\OZONE\OZ\Db\OZCountry::COL_DATA,
			$value
		);
	}

	/**
	 * Filters rows with `like` condition on column `oz_countries`.`data`.
	 * 
	 * @param string $value the filter value
	 * 
	 * @return static
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereDataIsLike(string $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('like'),
			\OZONE\OZ\Db\OZCountry::COL_DATA,
			$value
		);
	}

	/**
	 * Filters rows with `not_like` condition on column `oz_countries`.`data`.
	 * 
	 * @param string $value the filter value
	 * 
	 * @return static
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereDataIsNotLike(string $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('not_like'),
			\OZONE\OZ\Db\OZCountry::COL_DATA,
			$value
		);
	}

	/**
	 * Filters rows with `eq` condition on column `oz_countries`.`created_at`.
	 * 
	 * @param string|int $value the filter value
	 * 
	 * @return static
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereCreatedAtIs(string|int $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('eq'),
			\OZONE\OZ\Db\OZCountry::COL_CREATED_AT,
			$value
		);
	}

	/**
	 * Filters rows with `neq` condition on column `oz_countries`.`created_at`.
	 * 
	 * @param string|int $value the filter value
	 * 
	 * @return static
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereCreatedAtIsNot(string|int $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('neq'),
			\OZONE\OZ\Db\OZCountry::COL_CREATED_AT,
			$value
		);
	}

	/**
	 * Filters rows with `lt` condition on column `oz_countries`.`created_at`.
	 * 
	 * @param string|int $value the filter value
	 * 
	 * @return static
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereCreatedAtIsLt(string|int $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('lt'),
			\OZONE\OZ\Db\OZCountry::COL_CREATED_AT,
			$value
		);
	}

	/**
	 * Filters rows with `lte` condition on column `oz_countries`.`created_at`.
	 * 
	 * @param string|int $value the filter value
	 * 
	 * @return static
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereCreatedAtIsLte(string|int $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('lte'),
			\OZONE\OZ\Db\OZCountry::COL_CREATED_AT,
			$value
		);
	}

	/**
	 * Filters rows with `gt` condition on column `oz_countries`.`created_at`.
	 * 
	 * @param string|int $value the filter value
	 * 
	 * @return static
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereCreatedAtIsGt(string|int $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('gt'),
			\OZONE\OZ\Db\OZCountry::COL_CREATED_AT,
			$value
		);
	}

	/**
	 * Filters rows with `gte` condition on column `oz_countries`.`created_at`.
	 * 
	 * @param string|int $value the filter value
	 * 
	 * @return static
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereCreatedAtIsGte(string|int $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('gte'),
			\OZONE\OZ\Db\OZCountry::COL_CREATED_AT,
			$value
		);
	}

	/**
	 * Filters rows with `like` condition on column `oz_countries`.`created_at`.
	 * 
	 * @param string $value the filter value
	 * 
	 * @return static
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereCreatedAtIsLike(string $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('like'),
			\OZONE\OZ\Db\OZCountry::COL_CREATED_AT,
			$value
		);
	}

	/**
	 * Filters rows with `not_like` condition on column `oz_countries`.`created_at`.
	 * 
	 * @param string $value the filter value
	 * 
	 * @return static
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereCreatedAtIsNotLike(string $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('not_like'),
			\OZONE\OZ\Db\OZCountry::COL_CREATED_AT,
			$value
		);
	}

	/**
	 * Filters rows with `in` condition on column `oz_countries`.`created_at`.
	 * 
	 * @param array $value the filter value
	 * 
	 * @return static
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereCreatedAtIsIn(array $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('in'),
			\OZONE\OZ\Db\OZCountry::COL_CREATED_AT,
			$value
		);
	}

	/**
	 * Filters rows with `not_in` condition on column `oz_countries`.`created_at`.
	 * 
	 * @param array $value the filter value
	 * 
	 * @return static
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereCreatedAtIsNotIn(array $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('not_in'),
			\OZONE\OZ\Db\OZCountry::COL_CREATED_AT,
			$value
		);
	}

	/**
	 * Filters rows with `eq` condition on column `oz_countries`.`updated_at`.
	 * 
	 * @param string|int $value the filter value
	 * 
	 * @return static
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereUpdatedAtIs(string|int $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('eq'),
			\OZONE\OZ\Db\OZCountry::COL_UPDATED_AT,
			$value
		);
	}

	/**
	 * Filters rows with `neq` condition on column `oz_countries`.`updated_at`.
	 * 
	 * @param string|int $value the filter value
	 * 
	 * @return static
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereUpdatedAtIsNot(string|int $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('neq'),
			\OZONE\OZ\Db\OZCountry::COL_UPDATED_AT,
			$value
		);
	}

	/**
	 * Filters rows with `lt` condition on column `oz_countries`.`updated_at`.
	 * 
	 * @param string|int $value the filter value
	 * 
	 * @return static
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereUpdatedAtIsLt(string|int $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('lt'),
			\OZONE\OZ\Db\OZCountry::COL_UPDATED_AT,
			$value
		);
	}

	/**
	 * Filters rows with `lte` condition on column `oz_countries`.`updated_at`.
	 * 
	 * @param string|int $value the filter value
	 * 
	 * @return static
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereUpdatedAtIsLte(string|int $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('lte'),
			\OZONE\OZ\Db\OZCountry::COL_UPDATED_AT,
			$value
		);
	}

	/**
	 * Filters rows with `gt` condition on column `oz_countries`.`updated_at`.
	 * 
	 * @param string|int $value the filter value
	 * 
	 * @return static
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereUpdatedAtIsGt(string|int $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('gt'),
			\OZONE\OZ\Db\OZCountry::COL_UPDATED_AT,
			$value
		);
	}

	/**
	 * Filters rows with `gte` condition on column `oz_countries`.`updated_at`.
	 * 
	 * @param string|int $value the filter value
	 * 
	 * @return static
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereUpdatedAtIsGte(string|int $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('gte'),
			\OZONE\OZ\Db\OZCountry::COL_UPDATED_AT,
			$value
		);
	}

	/**
	 * Filters rows with `like` condition on column `oz_countries`.`updated_at`.
	 * 
	 * @param string $value the filter value
	 * 
	 * @return static
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereUpdatedAtIsLike(string $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('like'),
			\OZONE\OZ\Db\OZCountry::COL_UPDATED_AT,
			$value
		);
	}

	/**
	 * Filters rows with `not_like` condition on column `oz_countries`.`updated_at`.
	 * 
	 * @param string $value the filter value
	 * 
	 * @return static
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereUpdatedAtIsNotLike(string $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('not_like'),
			\OZONE\OZ\Db\OZCountry::COL_UPDATED_AT,
			$value
		);
	}

	/**
	 * Filters rows with `in` condition on column `oz_countries`.`updated_at`.
	 * 
	 * @param array $value the filter value
	 * 
	 * @return static
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereUpdatedAtIsIn(array $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('in'),
			\OZONE\OZ\Db\OZCountry::COL_UPDATED_AT,
			$value
		);
	}

	/**
	 * Filters rows with `not_in` condition on column `oz_countries`.`updated_at`.
	 * 
	 * @param array $value the filter value
	 * 
	 * @return static
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereUpdatedAtIsNotIn(array $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('not_in'),
			\OZONE\OZ\Db\OZCountry::COL_UPDATED_AT,
			$value
		);
	}

	/**
	 * Filters rows with `eq` condition on column `oz_countries`.`valid`.
	 * 
	 * @param bool $value the filter value
	 * 
	 * @return static
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereValidIs(bool $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('eq'),
			\OZONE\OZ\Db\OZCountry::COL_VALID,
			$value
		);
	}

	/**
	 * Filters rows with `neq` condition on column `oz_countries`.`valid`.
	 * 
	 * @param bool $value the filter value
	 * 
	 * @return static
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereValidIsNot(bool $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('neq'),
			\OZONE\OZ\Db\OZCountry::COL_VALID,
			$value
		);
	}

	/**
	 * Filters rows with `is_false` condition on column `oz_countries`.`valid`.
	 * 
	 * @return static
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereValidIsFalse(): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('is_false'),
			\OZONE\OZ\Db\OZCountry::COL_VALID
		);
	}

	/**
	 * Filters rows with `is_true` condition on column `oz_countries`.`valid`.
	 * 
	 * @return static
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereValidIsTrue(): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('is_true'),
			\OZONE\OZ\Db\OZCountry::COL_VALID
		);
	}
}
