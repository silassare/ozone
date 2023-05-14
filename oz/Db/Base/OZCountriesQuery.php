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

namespace OZONE\OZ\Db\Base;

use Gobl\DBAL\Operator;

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
	 * {@inheritDoc}
	 *
	 * @return static
	 */
	public static function createInstance(): static
	{
		return new \OZONE\OZ\Db\OZCountriesQuery();
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
	 */
	public function whereCc2Is(string $value): self
	{
		return $this->filterBy(
			Operator::from('eq'),
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
	 */
	public function whereCc2IsNot(string $value): self
	{
		return $this->filterBy(
			Operator::from('neq'),
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
	 */
	public function whereCc2IsLt(string $value): self
	{
		return $this->filterBy(
			Operator::from('lt'),
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
	 */
	public function whereCc2IsLte(string $value): self
	{
		return $this->filterBy(
			Operator::from('lte'),
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
	 */
	public function whereCc2IsGt(string $value): self
	{
		return $this->filterBy(
			Operator::from('gt'),
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
	 */
	public function whereCc2IsGte(string $value): self
	{
		return $this->filterBy(
			Operator::from('gte'),
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
	 */
	public function whereCc2IsLike(string $value): self
	{
		return $this->filterBy(
			Operator::from('like'),
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
	 */
	public function whereCc2IsNotLike(string $value): self
	{
		return $this->filterBy(
			Operator::from('not_like'),
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
	 */
	public function whereCc2IsIn(array $value): self
	{
		return $this->filterBy(
			Operator::from('in'),
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
	 */
	public function whereCc2IsNotIn(array $value): self
	{
		return $this->filterBy(
			Operator::from('not_in'),
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
	 */
	public function whereCodeIs(string $value): self
	{
		return $this->filterBy(
			Operator::from('eq'),
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
	 */
	public function whereCodeIsNot(string $value): self
	{
		return $this->filterBy(
			Operator::from('neq'),
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
	 */
	public function whereCodeIsLt(string $value): self
	{
		return $this->filterBy(
			Operator::from('lt'),
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
	 */
	public function whereCodeIsLte(string $value): self
	{
		return $this->filterBy(
			Operator::from('lte'),
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
	 */
	public function whereCodeIsGt(string $value): self
	{
		return $this->filterBy(
			Operator::from('gt'),
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
	 */
	public function whereCodeIsGte(string $value): self
	{
		return $this->filterBy(
			Operator::from('gte'),
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
	 */
	public function whereCodeIsLike(string $value): self
	{
		return $this->filterBy(
			Operator::from('like'),
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
	 */
	public function whereCodeIsNotLike(string $value): self
	{
		return $this->filterBy(
			Operator::from('not_like'),
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
	 */
	public function whereCodeIsIn(array $value): self
	{
		return $this->filterBy(
			Operator::from('in'),
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
	 */
	public function whereCodeIsNotIn(array $value): self
	{
		return $this->filterBy(
			Operator::from('not_in'),
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
	 */
	public function whereNameIs(string $value): self
	{
		return $this->filterBy(
			Operator::from('eq'),
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
	 */
	public function whereNameIsNot(string $value): self
	{
		return $this->filterBy(
			Operator::from('neq'),
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
	 */
	public function whereNameIsLt(string $value): self
	{
		return $this->filterBy(
			Operator::from('lt'),
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
	 */
	public function whereNameIsLte(string $value): self
	{
		return $this->filterBy(
			Operator::from('lte'),
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
	 */
	public function whereNameIsGt(string $value): self
	{
		return $this->filterBy(
			Operator::from('gt'),
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
	 */
	public function whereNameIsGte(string $value): self
	{
		return $this->filterBy(
			Operator::from('gte'),
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
	 */
	public function whereNameIsLike(string $value): self
	{
		return $this->filterBy(
			Operator::from('like'),
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
	 */
	public function whereNameIsNotLike(string $value): self
	{
		return $this->filterBy(
			Operator::from('not_like'),
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
	 */
	public function whereNameIsIn(array $value): self
	{
		return $this->filterBy(
			Operator::from('in'),
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
	 */
	public function whereNameIsNotIn(array $value): self
	{
		return $this->filterBy(
			Operator::from('not_in'),
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
	 */
	public function whereNameRealIs(string $value): self
	{
		return $this->filterBy(
			Operator::from('eq'),
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
	 */
	public function whereNameRealIsNot(string $value): self
	{
		return $this->filterBy(
			Operator::from('neq'),
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
	 */
	public function whereNameRealIsLt(string $value): self
	{
		return $this->filterBy(
			Operator::from('lt'),
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
	 */
	public function whereNameRealIsLte(string $value): self
	{
		return $this->filterBy(
			Operator::from('lte'),
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
	 */
	public function whereNameRealIsGt(string $value): self
	{
		return $this->filterBy(
			Operator::from('gt'),
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
	 */
	public function whereNameRealIsGte(string $value): self
	{
		return $this->filterBy(
			Operator::from('gte'),
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
	 */
	public function whereNameRealIsLike(string $value): self
	{
		return $this->filterBy(
			Operator::from('like'),
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
	 */
	public function whereNameRealIsNotLike(string $value): self
	{
		return $this->filterBy(
			Operator::from('not_like'),
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
	 */
	public function whereNameRealIsIn(array $value): self
	{
		return $this->filterBy(
			Operator::from('in'),
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
	 */
	public function whereNameRealIsNotIn(array $value): self
	{
		return $this->filterBy(
			Operator::from('not_in'),
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
	 */
	public function whereDataIs(array $value): self
	{
		return $this->filterBy(
			Operator::from('eq'),
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
	 */
	public function whereDataIsNot(array $value): self
	{
		return $this->filterBy(
			Operator::from('neq'),
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
	 */
	public function whereDataIsLike(string $value): self
	{
		return $this->filterBy(
			Operator::from('like'),
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
	 */
	public function whereDataIsNotLike(string $value): self
	{
		return $this->filterBy(
			Operator::from('not_like'),
			\OZONE\OZ\Db\OZCountry::COL_DATA,
			$value
		);
	}

	/**
	 * Filters rows with `eq` condition on column `oz_countries`.`created_at`.
	 *
	 * @param int|string $value the filter value
	 *
	 * @return static
	 */
	public function whereCreatedAtIs(string|int $value): self
	{
		return $this->filterBy(
			Operator::from('eq'),
			\OZONE\OZ\Db\OZCountry::COL_CREATED_AT,
			$value
		);
	}

	/**
	 * Filters rows with `neq` condition on column `oz_countries`.`created_at`.
	 *
	 * @param int|string $value the filter value
	 *
	 * @return static
	 */
	public function whereCreatedAtIsNot(string|int $value): self
	{
		return $this->filterBy(
			Operator::from('neq'),
			\OZONE\OZ\Db\OZCountry::COL_CREATED_AT,
			$value
		);
	}

	/**
	 * Filters rows with `lt` condition on column `oz_countries`.`created_at`.
	 *
	 * @param int|string $value the filter value
	 *
	 * @return static
	 */
	public function whereCreatedAtIsLt(string|int $value): self
	{
		return $this->filterBy(
			Operator::from('lt'),
			\OZONE\OZ\Db\OZCountry::COL_CREATED_AT,
			$value
		);
	}

	/**
	 * Filters rows with `lte` condition on column `oz_countries`.`created_at`.
	 *
	 * @param int|string $value the filter value
	 *
	 * @return static
	 */
	public function whereCreatedAtIsLte(string|int $value): self
	{
		return $this->filterBy(
			Operator::from('lte'),
			\OZONE\OZ\Db\OZCountry::COL_CREATED_AT,
			$value
		);
	}

	/**
	 * Filters rows with `gt` condition on column `oz_countries`.`created_at`.
	 *
	 * @param int|string $value the filter value
	 *
	 * @return static
	 */
	public function whereCreatedAtIsGt(string|int $value): self
	{
		return $this->filterBy(
			Operator::from('gt'),
			\OZONE\OZ\Db\OZCountry::COL_CREATED_AT,
			$value
		);
	}

	/**
	 * Filters rows with `gte` condition on column `oz_countries`.`created_at`.
	 *
	 * @param int|string $value the filter value
	 *
	 * @return static
	 */
	public function whereCreatedAtIsGte(string|int $value): self
	{
		return $this->filterBy(
			Operator::from('gte'),
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
	 */
	public function whereCreatedAtIsLike(string $value): self
	{
		return $this->filterBy(
			Operator::from('like'),
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
	 */
	public function whereCreatedAtIsNotLike(string $value): self
	{
		return $this->filterBy(
			Operator::from('not_like'),
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
	 */
	public function whereCreatedAtIsIn(array $value): self
	{
		return $this->filterBy(
			Operator::from('in'),
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
	 */
	public function whereCreatedAtIsNotIn(array $value): self
	{
		return $this->filterBy(
			Operator::from('not_in'),
			\OZONE\OZ\Db\OZCountry::COL_CREATED_AT,
			$value
		);
	}

	/**
	 * Filters rows with `eq` condition on column `oz_countries`.`updated_at`.
	 *
	 * @param int|string $value the filter value
	 *
	 * @return static
	 */
	public function whereUpdatedAtIs(string|int $value): self
	{
		return $this->filterBy(
			Operator::from('eq'),
			\OZONE\OZ\Db\OZCountry::COL_UPDATED_AT,
			$value
		);
	}

	/**
	 * Filters rows with `neq` condition on column `oz_countries`.`updated_at`.
	 *
	 * @param int|string $value the filter value
	 *
	 * @return static
	 */
	public function whereUpdatedAtIsNot(string|int $value): self
	{
		return $this->filterBy(
			Operator::from('neq'),
			\OZONE\OZ\Db\OZCountry::COL_UPDATED_AT,
			$value
		);
	}

	/**
	 * Filters rows with `lt` condition on column `oz_countries`.`updated_at`.
	 *
	 * @param int|string $value the filter value
	 *
	 * @return static
	 */
	public function whereUpdatedAtIsLt(string|int $value): self
	{
		return $this->filterBy(
			Operator::from('lt'),
			\OZONE\OZ\Db\OZCountry::COL_UPDATED_AT,
			$value
		);
	}

	/**
	 * Filters rows with `lte` condition on column `oz_countries`.`updated_at`.
	 *
	 * @param int|string $value the filter value
	 *
	 * @return static
	 */
	public function whereUpdatedAtIsLte(string|int $value): self
	{
		return $this->filterBy(
			Operator::from('lte'),
			\OZONE\OZ\Db\OZCountry::COL_UPDATED_AT,
			$value
		);
	}

	/**
	 * Filters rows with `gt` condition on column `oz_countries`.`updated_at`.
	 *
	 * @param int|string $value the filter value
	 *
	 * @return static
	 */
	public function whereUpdatedAtIsGt(string|int $value): self
	{
		return $this->filterBy(
			Operator::from('gt'),
			\OZONE\OZ\Db\OZCountry::COL_UPDATED_AT,
			$value
		);
	}

	/**
	 * Filters rows with `gte` condition on column `oz_countries`.`updated_at`.
	 *
	 * @param int|string $value the filter value
	 *
	 * @return static
	 */
	public function whereUpdatedAtIsGte(string|int $value): self
	{
		return $this->filterBy(
			Operator::from('gte'),
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
	 */
	public function whereUpdatedAtIsLike(string $value): self
	{
		return $this->filterBy(
			Operator::from('like'),
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
	 */
	public function whereUpdatedAtIsNotLike(string $value): self
	{
		return $this->filterBy(
			Operator::from('not_like'),
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
	 */
	public function whereUpdatedAtIsIn(array $value): self
	{
		return $this->filterBy(
			Operator::from('in'),
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
	 */
	public function whereUpdatedAtIsNotIn(array $value): self
	{
		return $this->filterBy(
			Operator::from('not_in'),
			\OZONE\OZ\Db\OZCountry::COL_UPDATED_AT,
			$value
		);
	}

	/**
	 * Filters rows with `eq` condition on column `oz_countries`.`is_valid`.
	 *
	 * @param bool $value the filter value
	 *
	 * @return static
	 */
	public function whereIsValidIs(bool $value): self
	{
		return $this->filterBy(
			Operator::from('eq'),
			\OZONE\OZ\Db\OZCountry::COL_IS_VALID,
			$value
		);
	}

	/**
	 * Filters rows with `neq` condition on column `oz_countries`.`is_valid`.
	 *
	 * @param bool $value the filter value
	 *
	 * @return static
	 */
	public function whereIsValidIsNot(bool $value): self
	{
		return $this->filterBy(
			Operator::from('neq'),
			\OZONE\OZ\Db\OZCountry::COL_IS_VALID,
			$value
		);
	}

	/**
	 * Filters rows with `is_false` condition on column `oz_countries`.`is_valid`.
	 *
	 * @return static
	 */
	public function whereIsNotValid(): self
	{
		return $this->filterBy(
			Operator::from('is_false'),
			\OZONE\OZ\Db\OZCountry::COL_IS_VALID
		);
	}

	/**
	 * Filters rows with `is_true` condition on column `oz_countries`.`is_valid`.
	 *
	 * @return static
	 */
	public function whereIsValid(): self
	{
		return $this->filterBy(
			Operator::from('is_true'),
			\OZONE\OZ\Db\OZCountry::COL_IS_VALID
		);
	}
}
