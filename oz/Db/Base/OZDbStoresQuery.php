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

use Gobl\DBAL\Operator;

/**
 * Class OZDbStoresQuery.
 *
 * @method \OZONE\Core\Db\OZDbStoresResults find(?int $max = null, int $offset = 0, array $order_by = [])
 */
abstract class OZDbStoresQuery extends \Gobl\ORM\ORMTableQuery
{
	/**
	 * OZDbStoresQuery constructor.
	 */
	public function __construct()
	{
		parent::__construct(
			\OZONE\Core\Db\OZDbStore::TABLE_NAMESPACE,
			\OZONE\Core\Db\OZDbStore::TABLE_NAME
		);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @return static
	 */
	public static function createInstance(): static
	{
		return new \OZONE\Core\Db\OZDbStoresQuery();
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
	 * Filters rows with `eq` condition on column `oz_db_stores`.`id`.
	 *
	 * @param int|string $value the filter value
	 *
	 * @return static
	 */
	public function whereIdIs(string|int $value): self
	{
		return $this->filterBy(
			Operator::from('eq'),
			\OZONE\Core\Db\OZDbStore::COL_ID,
			$value
		);
	}

	/**
	 * Filters rows with `neq` condition on column `oz_db_stores`.`id`.
	 *
	 * @param int|string $value the filter value
	 *
	 * @return static
	 */
	public function whereIdIsNot(string|int $value): self
	{
		return $this->filterBy(
			Operator::from('neq'),
			\OZONE\Core\Db\OZDbStore::COL_ID,
			$value
		);
	}

	/**
	 * Filters rows with `lt` condition on column `oz_db_stores`.`id`.
	 *
	 * @param int|string $value the filter value
	 *
	 * @return static
	 */
	public function whereIdIsLt(string|int $value): self
	{
		return $this->filterBy(
			Operator::from('lt'),
			\OZONE\Core\Db\OZDbStore::COL_ID,
			$value
		);
	}

	/**
	 * Filters rows with `lte` condition on column `oz_db_stores`.`id`.
	 *
	 * @param int|string $value the filter value
	 *
	 * @return static
	 */
	public function whereIdIsLte(string|int $value): self
	{
		return $this->filterBy(
			Operator::from('lte'),
			\OZONE\Core\Db\OZDbStore::COL_ID,
			$value
		);
	}

	/**
	 * Filters rows with `gt` condition on column `oz_db_stores`.`id`.
	 *
	 * @param int|string $value the filter value
	 *
	 * @return static
	 */
	public function whereIdIsGt(string|int $value): self
	{
		return $this->filterBy(
			Operator::from('gt'),
			\OZONE\Core\Db\OZDbStore::COL_ID,
			$value
		);
	}

	/**
	 * Filters rows with `gte` condition on column `oz_db_stores`.`id`.
	 *
	 * @param int|string $value the filter value
	 *
	 * @return static
	 */
	public function whereIdIsGte(string|int $value): self
	{
		return $this->filterBy(
			Operator::from('gte'),
			\OZONE\Core\Db\OZDbStore::COL_ID,
			$value
		);
	}

	/**
	 * Filters rows with `like` condition on column `oz_db_stores`.`id`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 */
	public function whereIdIsLike(string $value): self
	{
		return $this->filterBy(
			Operator::from('like'),
			\OZONE\Core\Db\OZDbStore::COL_ID,
			$value
		);
	}

	/**
	 * Filters rows with `not_like` condition on column `oz_db_stores`.`id`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 */
	public function whereIdIsNotLike(string $value): self
	{
		return $this->filterBy(
			Operator::from('not_like'),
			\OZONE\Core\Db\OZDbStore::COL_ID,
			$value
		);
	}

	/**
	 * Filters rows with `in` condition on column `oz_db_stores`.`id`.
	 *
	 * @param array $value the filter value
	 *
	 * @return static
	 */
	public function whereIdIsIn(array $value): self
	{
		return $this->filterBy(
			Operator::from('in'),
			\OZONE\Core\Db\OZDbStore::COL_ID,
			$value
		);
	}

	/**
	 * Filters rows with `not_in` condition on column `oz_db_stores`.`id`.
	 *
	 * @param array $value the filter value
	 *
	 * @return static
	 */
	public function whereIdIsNotIn(array $value): self
	{
		return $this->filterBy(
			Operator::from('not_in'),
			\OZONE\Core\Db\OZDbStore::COL_ID,
			$value
		);
	}

	/**
	 * Filters rows with `eq` condition on column `oz_db_stores`.`group`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 */
	public function whereGroupIs(string $value): self
	{
		return $this->filterBy(
			Operator::from('eq'),
			\OZONE\Core\Db\OZDbStore::COL_GROUP,
			$value
		);
	}

	/**
	 * Filters rows with `neq` condition on column `oz_db_stores`.`group`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 */
	public function whereGroupIsNot(string $value): self
	{
		return $this->filterBy(
			Operator::from('neq'),
			\OZONE\Core\Db\OZDbStore::COL_GROUP,
			$value
		);
	}

	/**
	 * Filters rows with `lt` condition on column `oz_db_stores`.`group`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 */
	public function whereGroupIsLt(string $value): self
	{
		return $this->filterBy(
			Operator::from('lt'),
			\OZONE\Core\Db\OZDbStore::COL_GROUP,
			$value
		);
	}

	/**
	 * Filters rows with `lte` condition on column `oz_db_stores`.`group`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 */
	public function whereGroupIsLte(string $value): self
	{
		return $this->filterBy(
			Operator::from('lte'),
			\OZONE\Core\Db\OZDbStore::COL_GROUP,
			$value
		);
	}

	/**
	 * Filters rows with `gt` condition on column `oz_db_stores`.`group`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 */
	public function whereGroupIsGt(string $value): self
	{
		return $this->filterBy(
			Operator::from('gt'),
			\OZONE\Core\Db\OZDbStore::COL_GROUP,
			$value
		);
	}

	/**
	 * Filters rows with `gte` condition on column `oz_db_stores`.`group`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 */
	public function whereGroupIsGte(string $value): self
	{
		return $this->filterBy(
			Operator::from('gte'),
			\OZONE\Core\Db\OZDbStore::COL_GROUP,
			$value
		);
	}

	/**
	 * Filters rows with `like` condition on column `oz_db_stores`.`group`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 */
	public function whereGroupIsLike(string $value): self
	{
		return $this->filterBy(
			Operator::from('like'),
			\OZONE\Core\Db\OZDbStore::COL_GROUP,
			$value
		);
	}

	/**
	 * Filters rows with `not_like` condition on column `oz_db_stores`.`group`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 */
	public function whereGroupIsNotLike(string $value): self
	{
		return $this->filterBy(
			Operator::from('not_like'),
			\OZONE\Core\Db\OZDbStore::COL_GROUP,
			$value
		);
	}

	/**
	 * Filters rows with `in` condition on column `oz_db_stores`.`group`.
	 *
	 * @param array $value the filter value
	 *
	 * @return static
	 */
	public function whereGroupIsIn(array $value): self
	{
		return $this->filterBy(
			Operator::from('in'),
			\OZONE\Core\Db\OZDbStore::COL_GROUP,
			$value
		);
	}

	/**
	 * Filters rows with `not_in` condition on column `oz_db_stores`.`group`.
	 *
	 * @param array $value the filter value
	 *
	 * @return static
	 */
	public function whereGroupIsNotIn(array $value): self
	{
		return $this->filterBy(
			Operator::from('not_in'),
			\OZONE\Core\Db\OZDbStore::COL_GROUP,
			$value
		);
	}

	/**
	 * Filters rows with `eq` condition on column `oz_db_stores`.`key`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 */
	public function whereKeyIs(string $value): self
	{
		return $this->filterBy(
			Operator::from('eq'),
			\OZONE\Core\Db\OZDbStore::COL_KEY,
			$value
		);
	}

	/**
	 * Filters rows with `neq` condition on column `oz_db_stores`.`key`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 */
	public function whereKeyIsNot(string $value): self
	{
		return $this->filterBy(
			Operator::from('neq'),
			\OZONE\Core\Db\OZDbStore::COL_KEY,
			$value
		);
	}

	/**
	 * Filters rows with `lt` condition on column `oz_db_stores`.`key`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 */
	public function whereKeyIsLt(string $value): self
	{
		return $this->filterBy(
			Operator::from('lt'),
			\OZONE\Core\Db\OZDbStore::COL_KEY,
			$value
		);
	}

	/**
	 * Filters rows with `lte` condition on column `oz_db_stores`.`key`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 */
	public function whereKeyIsLte(string $value): self
	{
		return $this->filterBy(
			Operator::from('lte'),
			\OZONE\Core\Db\OZDbStore::COL_KEY,
			$value
		);
	}

	/**
	 * Filters rows with `gt` condition on column `oz_db_stores`.`key`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 */
	public function whereKeyIsGt(string $value): self
	{
		return $this->filterBy(
			Operator::from('gt'),
			\OZONE\Core\Db\OZDbStore::COL_KEY,
			$value
		);
	}

	/**
	 * Filters rows with `gte` condition on column `oz_db_stores`.`key`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 */
	public function whereKeyIsGte(string $value): self
	{
		return $this->filterBy(
			Operator::from('gte'),
			\OZONE\Core\Db\OZDbStore::COL_KEY,
			$value
		);
	}

	/**
	 * Filters rows with `like` condition on column `oz_db_stores`.`key`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 */
	public function whereKeyIsLike(string $value): self
	{
		return $this->filterBy(
			Operator::from('like'),
			\OZONE\Core\Db\OZDbStore::COL_KEY,
			$value
		);
	}

	/**
	 * Filters rows with `not_like` condition on column `oz_db_stores`.`key`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 */
	public function whereKeyIsNotLike(string $value): self
	{
		return $this->filterBy(
			Operator::from('not_like'),
			\OZONE\Core\Db\OZDbStore::COL_KEY,
			$value
		);
	}

	/**
	 * Filters rows with `in` condition on column `oz_db_stores`.`key`.
	 *
	 * @param array $value the filter value
	 *
	 * @return static
	 */
	public function whereKeyIsIn(array $value): self
	{
		return $this->filterBy(
			Operator::from('in'),
			\OZONE\Core\Db\OZDbStore::COL_KEY,
			$value
		);
	}

	/**
	 * Filters rows with `not_in` condition on column `oz_db_stores`.`key`.
	 *
	 * @param array $value the filter value
	 *
	 * @return static
	 */
	public function whereKeyIsNotIn(array $value): self
	{
		return $this->filterBy(
			Operator::from('not_in'),
			\OZONE\Core\Db\OZDbStore::COL_KEY,
			$value
		);
	}

	/**
	 * Filters rows with `eq` condition on column `oz_db_stores`.`value`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 */
	public function whereValueIs(string $value): self
	{
		return $this->filterBy(
			Operator::from('eq'),
			\OZONE\Core\Db\OZDbStore::COL_VALUE,
			$value
		);
	}

	/**
	 * Filters rows with `neq` condition on column `oz_db_stores`.`value`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 */
	public function whereValueIsNot(string $value): self
	{
		return $this->filterBy(
			Operator::from('neq'),
			\OZONE\Core\Db\OZDbStore::COL_VALUE,
			$value
		);
	}

	/**
	 * Filters rows with `lt` condition on column `oz_db_stores`.`value`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 */
	public function whereValueIsLt(string $value): self
	{
		return $this->filterBy(
			Operator::from('lt'),
			\OZONE\Core\Db\OZDbStore::COL_VALUE,
			$value
		);
	}

	/**
	 * Filters rows with `lte` condition on column `oz_db_stores`.`value`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 */
	public function whereValueIsLte(string $value): self
	{
		return $this->filterBy(
			Operator::from('lte'),
			\OZONE\Core\Db\OZDbStore::COL_VALUE,
			$value
		);
	}

	/**
	 * Filters rows with `gt` condition on column `oz_db_stores`.`value`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 */
	public function whereValueIsGt(string $value): self
	{
		return $this->filterBy(
			Operator::from('gt'),
			\OZONE\Core\Db\OZDbStore::COL_VALUE,
			$value
		);
	}

	/**
	 * Filters rows with `gte` condition on column `oz_db_stores`.`value`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 */
	public function whereValueIsGte(string $value): self
	{
		return $this->filterBy(
			Operator::from('gte'),
			\OZONE\Core\Db\OZDbStore::COL_VALUE,
			$value
		);
	}

	/**
	 * Filters rows with `like` condition on column `oz_db_stores`.`value`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 */
	public function whereValueIsLike(string $value): self
	{
		return $this->filterBy(
			Operator::from('like'),
			\OZONE\Core\Db\OZDbStore::COL_VALUE,
			$value
		);
	}

	/**
	 * Filters rows with `not_like` condition on column `oz_db_stores`.`value`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 */
	public function whereValueIsNotLike(string $value): self
	{
		return $this->filterBy(
			Operator::from('not_like'),
			\OZONE\Core\Db\OZDbStore::COL_VALUE,
			$value
		);
	}

	/**
	 * Filters rows with `is_null` condition on column `oz_db_stores`.`value`.
	 *
	 * @return static
	 */
	public function whereValueIsNull(): self
	{
		return $this->filterBy(
			Operator::from('is_null'),
			\OZONE\Core\Db\OZDbStore::COL_VALUE
		);
	}

	/**
	 * Filters rows with `is_not_null` condition on column `oz_db_stores`.`value`.
	 *
	 * @return static
	 */
	public function whereValueIsNotNull(): self
	{
		return $this->filterBy(
			Operator::from('is_not_null'),
			\OZONE\Core\Db\OZDbStore::COL_VALUE
		);
	}

	/**
	 * Filters rows with `in` condition on column `oz_db_stores`.`value`.
	 *
	 * @param array $value the filter value
	 *
	 * @return static
	 */
	public function whereValueIsIn(array $value): self
	{
		return $this->filterBy(
			Operator::from('in'),
			\OZONE\Core\Db\OZDbStore::COL_VALUE,
			$value
		);
	}

	/**
	 * Filters rows with `not_in` condition on column `oz_db_stores`.`value`.
	 *
	 * @param array $value the filter value
	 *
	 * @return static
	 */
	public function whereValueIsNotIn(array $value): self
	{
		return $this->filterBy(
			Operator::from('not_in'),
			\OZONE\Core\Db\OZDbStore::COL_VALUE,
			$value
		);
	}

	/**
	 * Filters rows with `eq` condition on column `oz_db_stores`.`label`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 */
	public function whereLabelIs(string $value): self
	{
		return $this->filterBy(
			Operator::from('eq'),
			\OZONE\Core\Db\OZDbStore::COL_LABEL,
			$value
		);
	}

	/**
	 * Filters rows with `neq` condition on column `oz_db_stores`.`label`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 */
	public function whereLabelIsNot(string $value): self
	{
		return $this->filterBy(
			Operator::from('neq'),
			\OZONE\Core\Db\OZDbStore::COL_LABEL,
			$value
		);
	}

	/**
	 * Filters rows with `lt` condition on column `oz_db_stores`.`label`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 */
	public function whereLabelIsLt(string $value): self
	{
		return $this->filterBy(
			Operator::from('lt'),
			\OZONE\Core\Db\OZDbStore::COL_LABEL,
			$value
		);
	}

	/**
	 * Filters rows with `lte` condition on column `oz_db_stores`.`label`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 */
	public function whereLabelIsLte(string $value): self
	{
		return $this->filterBy(
			Operator::from('lte'),
			\OZONE\Core\Db\OZDbStore::COL_LABEL,
			$value
		);
	}

	/**
	 * Filters rows with `gt` condition on column `oz_db_stores`.`label`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 */
	public function whereLabelIsGt(string $value): self
	{
		return $this->filterBy(
			Operator::from('gt'),
			\OZONE\Core\Db\OZDbStore::COL_LABEL,
			$value
		);
	}

	/**
	 * Filters rows with `gte` condition on column `oz_db_stores`.`label`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 */
	public function whereLabelIsGte(string $value): self
	{
		return $this->filterBy(
			Operator::from('gte'),
			\OZONE\Core\Db\OZDbStore::COL_LABEL,
			$value
		);
	}

	/**
	 * Filters rows with `like` condition on column `oz_db_stores`.`label`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 */
	public function whereLabelIsLike(string $value): self
	{
		return $this->filterBy(
			Operator::from('like'),
			\OZONE\Core\Db\OZDbStore::COL_LABEL,
			$value
		);
	}

	/**
	 * Filters rows with `not_like` condition on column `oz_db_stores`.`label`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 */
	public function whereLabelIsNotLike(string $value): self
	{
		return $this->filterBy(
			Operator::from('not_like'),
			\OZONE\Core\Db\OZDbStore::COL_LABEL,
			$value
		);
	}

	/**
	 * Filters rows with `in` condition on column `oz_db_stores`.`label`.
	 *
	 * @param array $value the filter value
	 *
	 * @return static
	 */
	public function whereLabelIsIn(array $value): self
	{
		return $this->filterBy(
			Operator::from('in'),
			\OZONE\Core\Db\OZDbStore::COL_LABEL,
			$value
		);
	}

	/**
	 * Filters rows with `not_in` condition on column `oz_db_stores`.`label`.
	 *
	 * @param array $value the filter value
	 *
	 * @return static
	 */
	public function whereLabelIsNotIn(array $value): self
	{
		return $this->filterBy(
			Operator::from('not_in'),
			\OZONE\Core\Db\OZDbStore::COL_LABEL,
			$value
		);
	}

	/**
	 * Filters rows with `eq` condition on column `oz_db_stores`.`data`.
	 *
	 * @param array $value the filter value
	 *
	 * @return static
	 */
	public function whereDataIs(array $value): self
	{
		return $this->filterBy(
			Operator::from('eq'),
			\OZONE\Core\Db\OZDbStore::COL_DATA,
			$value
		);
	}

	/**
	 * Filters rows with `neq` condition on column `oz_db_stores`.`data`.
	 *
	 * @param array $value the filter value
	 *
	 * @return static
	 */
	public function whereDataIsNot(array $value): self
	{
		return $this->filterBy(
			Operator::from('neq'),
			\OZONE\Core\Db\OZDbStore::COL_DATA,
			$value
		);
	}

	/**
	 * Filters rows with `like` condition on column `oz_db_stores`.`data`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 */
	public function whereDataIsLike(string $value): self
	{
		return $this->filterBy(
			Operator::from('like'),
			\OZONE\Core\Db\OZDbStore::COL_DATA,
			$value
		);
	}

	/**
	 * Filters rows with `not_like` condition on column `oz_db_stores`.`data`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 */
	public function whereDataIsNotLike(string $value): self
	{
		return $this->filterBy(
			Operator::from('not_like'),
			\OZONE\Core\Db\OZDbStore::COL_DATA,
			$value
		);
	}

	/**
	 * Filters rows with `eq` condition on column `oz_db_stores`.`created_at`.
	 *
	 * @param int|string $value the filter value
	 *
	 * @return static
	 */
	public function whereCreatedAtIs(string|int $value): self
	{
		return $this->filterBy(
			Operator::from('eq'),
			\OZONE\Core\Db\OZDbStore::COL_CREATED_AT,
			$value
		);
	}

	/**
	 * Filters rows with `neq` condition on column `oz_db_stores`.`created_at`.
	 *
	 * @param int|string $value the filter value
	 *
	 * @return static
	 */
	public function whereCreatedAtIsNot(string|int $value): self
	{
		return $this->filterBy(
			Operator::from('neq'),
			\OZONE\Core\Db\OZDbStore::COL_CREATED_AT,
			$value
		);
	}

	/**
	 * Filters rows with `lt` condition on column `oz_db_stores`.`created_at`.
	 *
	 * @param int|string $value the filter value
	 *
	 * @return static
	 */
	public function whereCreatedAtIsLt(string|int $value): self
	{
		return $this->filterBy(
			Operator::from('lt'),
			\OZONE\Core\Db\OZDbStore::COL_CREATED_AT,
			$value
		);
	}

	/**
	 * Filters rows with `lte` condition on column `oz_db_stores`.`created_at`.
	 *
	 * @param int|string $value the filter value
	 *
	 * @return static
	 */
	public function whereCreatedAtIsLte(string|int $value): self
	{
		return $this->filterBy(
			Operator::from('lte'),
			\OZONE\Core\Db\OZDbStore::COL_CREATED_AT,
			$value
		);
	}

	/**
	 * Filters rows with `gt` condition on column `oz_db_stores`.`created_at`.
	 *
	 * @param int|string $value the filter value
	 *
	 * @return static
	 */
	public function whereCreatedAtIsGt(string|int $value): self
	{
		return $this->filterBy(
			Operator::from('gt'),
			\OZONE\Core\Db\OZDbStore::COL_CREATED_AT,
			$value
		);
	}

	/**
	 * Filters rows with `gte` condition on column `oz_db_stores`.`created_at`.
	 *
	 * @param int|string $value the filter value
	 *
	 * @return static
	 */
	public function whereCreatedAtIsGte(string|int $value): self
	{
		return $this->filterBy(
			Operator::from('gte'),
			\OZONE\Core\Db\OZDbStore::COL_CREATED_AT,
			$value
		);
	}

	/**
	 * Filters rows with `like` condition on column `oz_db_stores`.`created_at`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 */
	public function whereCreatedAtIsLike(string $value): self
	{
		return $this->filterBy(
			Operator::from('like'),
			\OZONE\Core\Db\OZDbStore::COL_CREATED_AT,
			$value
		);
	}

	/**
	 * Filters rows with `not_like` condition on column `oz_db_stores`.`created_at`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 */
	public function whereCreatedAtIsNotLike(string $value): self
	{
		return $this->filterBy(
			Operator::from('not_like'),
			\OZONE\Core\Db\OZDbStore::COL_CREATED_AT,
			$value
		);
	}

	/**
	 * Filters rows with `in` condition on column `oz_db_stores`.`created_at`.
	 *
	 * @param array $value the filter value
	 *
	 * @return static
	 */
	public function whereCreatedAtIsIn(array $value): self
	{
		return $this->filterBy(
			Operator::from('in'),
			\OZONE\Core\Db\OZDbStore::COL_CREATED_AT,
			$value
		);
	}

	/**
	 * Filters rows with `not_in` condition on column `oz_db_stores`.`created_at`.
	 *
	 * @param array $value the filter value
	 *
	 * @return static
	 */
	public function whereCreatedAtIsNotIn(array $value): self
	{
		return $this->filterBy(
			Operator::from('not_in'),
			\OZONE\Core\Db\OZDbStore::COL_CREATED_AT,
			$value
		);
	}

	/**
	 * Filters rows with `eq` condition on column `oz_db_stores`.`updated_at`.
	 *
	 * @param int|string $value the filter value
	 *
	 * @return static
	 */
	public function whereUpdatedAtIs(string|int $value): self
	{
		return $this->filterBy(
			Operator::from('eq'),
			\OZONE\Core\Db\OZDbStore::COL_UPDATED_AT,
			$value
		);
	}

	/**
	 * Filters rows with `neq` condition on column `oz_db_stores`.`updated_at`.
	 *
	 * @param int|string $value the filter value
	 *
	 * @return static
	 */
	public function whereUpdatedAtIsNot(string|int $value): self
	{
		return $this->filterBy(
			Operator::from('neq'),
			\OZONE\Core\Db\OZDbStore::COL_UPDATED_AT,
			$value
		);
	}

	/**
	 * Filters rows with `lt` condition on column `oz_db_stores`.`updated_at`.
	 *
	 * @param int|string $value the filter value
	 *
	 * @return static
	 */
	public function whereUpdatedAtIsLt(string|int $value): self
	{
		return $this->filterBy(
			Operator::from('lt'),
			\OZONE\Core\Db\OZDbStore::COL_UPDATED_AT,
			$value
		);
	}

	/**
	 * Filters rows with `lte` condition on column `oz_db_stores`.`updated_at`.
	 *
	 * @param int|string $value the filter value
	 *
	 * @return static
	 */
	public function whereUpdatedAtIsLte(string|int $value): self
	{
		return $this->filterBy(
			Operator::from('lte'),
			\OZONE\Core\Db\OZDbStore::COL_UPDATED_AT,
			$value
		);
	}

	/**
	 * Filters rows with `gt` condition on column `oz_db_stores`.`updated_at`.
	 *
	 * @param int|string $value the filter value
	 *
	 * @return static
	 */
	public function whereUpdatedAtIsGt(string|int $value): self
	{
		return $this->filterBy(
			Operator::from('gt'),
			\OZONE\Core\Db\OZDbStore::COL_UPDATED_AT,
			$value
		);
	}

	/**
	 * Filters rows with `gte` condition on column `oz_db_stores`.`updated_at`.
	 *
	 * @param int|string $value the filter value
	 *
	 * @return static
	 */
	public function whereUpdatedAtIsGte(string|int $value): self
	{
		return $this->filterBy(
			Operator::from('gte'),
			\OZONE\Core\Db\OZDbStore::COL_UPDATED_AT,
			$value
		);
	}

	/**
	 * Filters rows with `like` condition on column `oz_db_stores`.`updated_at`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 */
	public function whereUpdatedAtIsLike(string $value): self
	{
		return $this->filterBy(
			Operator::from('like'),
			\OZONE\Core\Db\OZDbStore::COL_UPDATED_AT,
			$value
		);
	}

	/**
	 * Filters rows with `not_like` condition on column `oz_db_stores`.`updated_at`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 */
	public function whereUpdatedAtIsNotLike(string $value): self
	{
		return $this->filterBy(
			Operator::from('not_like'),
			\OZONE\Core\Db\OZDbStore::COL_UPDATED_AT,
			$value
		);
	}

	/**
	 * Filters rows with `in` condition on column `oz_db_stores`.`updated_at`.
	 *
	 * @param array $value the filter value
	 *
	 * @return static
	 */
	public function whereUpdatedAtIsIn(array $value): self
	{
		return $this->filterBy(
			Operator::from('in'),
			\OZONE\Core\Db\OZDbStore::COL_UPDATED_AT,
			$value
		);
	}

	/**
	 * Filters rows with `not_in` condition on column `oz_db_stores`.`updated_at`.
	 *
	 * @param array $value the filter value
	 *
	 * @return static
	 */
	public function whereUpdatedAtIsNotIn(array $value): self
	{
		return $this->filterBy(
			Operator::from('not_in'),
			\OZONE\Core\Db\OZDbStore::COL_UPDATED_AT,
			$value
		);
	}

	/**
	 * Filters rows with `eq` condition on column `oz_db_stores`.`is_valid`.
	 *
	 * @param bool $value the filter value
	 *
	 * @return static
	 */
	public function whereIsValidIs(bool $value): self
	{
		return $this->filterBy(
			Operator::from('eq'),
			\OZONE\Core\Db\OZDbStore::COL_IS_VALID,
			$value
		);
	}

	/**
	 * Filters rows with `neq` condition on column `oz_db_stores`.`is_valid`.
	 *
	 * @param bool $value the filter value
	 *
	 * @return static
	 */
	public function whereIsValidIsNot(bool $value): self
	{
		return $this->filterBy(
			Operator::from('neq'),
			\OZONE\Core\Db\OZDbStore::COL_IS_VALID,
			$value
		);
	}

	/**
	 * Filters rows with `is_false` condition on column `oz_db_stores`.`is_valid`.
	 *
	 * @return static
	 */
	public function whereIsNotValid(): self
	{
		return $this->filterBy(
			Operator::from('is_false'),
			\OZONE\Core\Db\OZDbStore::COL_IS_VALID
		);
	}

	/**
	 * Filters rows with `is_true` condition on column `oz_db_stores`.`is_valid`.
	 *
	 * @return static
	 */
	public function whereIsValid(): self
	{
		return $this->filterBy(
			Operator::from('is_true'),
			\OZONE\Core\Db\OZDbStore::COL_IS_VALID
		);
	}
}
