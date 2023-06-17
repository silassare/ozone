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
 * Class OZSessionsQuery.
 *
 * @method \OZONE\Core\Db\OZSessionsResults find(?int $max = null, int $offset = 0, array $order_by = [])
 */
abstract class OZSessionsQuery extends \Gobl\ORM\ORMTableQuery
{
	/**
	 * OZSessionsQuery constructor.
	 */
	public function __construct()
	{
		parent::__construct(
			\OZONE\Core\Db\OZSession::TABLE_NAMESPACE,
			\OZONE\Core\Db\OZSession::TABLE_NAME
		);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @return static
	 */
	public static function createInstance(): static
	{
		return new \OZONE\Core\Db\OZSessionsQuery();
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
	 * Filters rows with `eq` condition on column `oz_sessions`.`id`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 */
	public function whereIdIs(string $value): self
	{
		return $this->filterBy(
			Operator::from('eq'),
			\OZONE\Core\Db\OZSession::COL_ID,
			$value
		);
	}

	/**
	 * Filters rows with `neq` condition on column `oz_sessions`.`id`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 */
	public function whereIdIsNot(string $value): self
	{
		return $this->filterBy(
			Operator::from('neq'),
			\OZONE\Core\Db\OZSession::COL_ID,
			$value
		);
	}

	/**
	 * Filters rows with `lt` condition on column `oz_sessions`.`id`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 */
	public function whereIdIsLt(string $value): self
	{
		return $this->filterBy(
			Operator::from('lt'),
			\OZONE\Core\Db\OZSession::COL_ID,
			$value
		);
	}

	/**
	 * Filters rows with `lte` condition on column `oz_sessions`.`id`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 */
	public function whereIdIsLte(string $value): self
	{
		return $this->filterBy(
			Operator::from('lte'),
			\OZONE\Core\Db\OZSession::COL_ID,
			$value
		);
	}

	/**
	 * Filters rows with `gt` condition on column `oz_sessions`.`id`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 */
	public function whereIdIsGt(string $value): self
	{
		return $this->filterBy(
			Operator::from('gt'),
			\OZONE\Core\Db\OZSession::COL_ID,
			$value
		);
	}

	/**
	 * Filters rows with `gte` condition on column `oz_sessions`.`id`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 */
	public function whereIdIsGte(string $value): self
	{
		return $this->filterBy(
			Operator::from('gte'),
			\OZONE\Core\Db\OZSession::COL_ID,
			$value
		);
	}

	/**
	 * Filters rows with `like` condition on column `oz_sessions`.`id`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 */
	public function whereIdIsLike(string $value): self
	{
		return $this->filterBy(
			Operator::from('like'),
			\OZONE\Core\Db\OZSession::COL_ID,
			$value
		);
	}

	/**
	 * Filters rows with `not_like` condition on column `oz_sessions`.`id`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 */
	public function whereIdIsNotLike(string $value): self
	{
		return $this->filterBy(
			Operator::from('not_like'),
			\OZONE\Core\Db\OZSession::COL_ID,
			$value
		);
	}

	/**
	 * Filters rows with `in` condition on column `oz_sessions`.`id`.
	 *
	 * @param array $value the filter value
	 *
	 * @return static
	 */
	public function whereIdIsIn(array $value): self
	{
		return $this->filterBy(
			Operator::from('in'),
			\OZONE\Core\Db\OZSession::COL_ID,
			$value
		);
	}

	/**
	 * Filters rows with `not_in` condition on column `oz_sessions`.`id`.
	 *
	 * @param array $value the filter value
	 *
	 * @return static
	 */
	public function whereIdIsNotIn(array $value): self
	{
		return $this->filterBy(
			Operator::from('not_in'),
			\OZONE\Core\Db\OZSession::COL_ID,
			$value
		);
	}

	/**
	 * Filters rows with `eq` condition on column `oz_sessions`.`user_id`.
	 *
	 * @param int|string $value the filter value
	 *
	 * @return static
	 */
	public function whereUserIdIs(string|int $value): self
	{
		return $this->filterBy(
			Operator::from('eq'),
			\OZONE\Core\Db\OZSession::COL_USER_ID,
			$value
		);
	}

	/**
	 * Filters rows with `neq` condition on column `oz_sessions`.`user_id`.
	 *
	 * @param int|string $value the filter value
	 *
	 * @return static
	 */
	public function whereUserIdIsNot(string|int $value): self
	{
		return $this->filterBy(
			Operator::from('neq'),
			\OZONE\Core\Db\OZSession::COL_USER_ID,
			$value
		);
	}

	/**
	 * Filters rows with `lt` condition on column `oz_sessions`.`user_id`.
	 *
	 * @param int|string $value the filter value
	 *
	 * @return static
	 */
	public function whereUserIdIsLt(string|int $value): self
	{
		return $this->filterBy(
			Operator::from('lt'),
			\OZONE\Core\Db\OZSession::COL_USER_ID,
			$value
		);
	}

	/**
	 * Filters rows with `lte` condition on column `oz_sessions`.`user_id`.
	 *
	 * @param int|string $value the filter value
	 *
	 * @return static
	 */
	public function whereUserIdIsLte(string|int $value): self
	{
		return $this->filterBy(
			Operator::from('lte'),
			\OZONE\Core\Db\OZSession::COL_USER_ID,
			$value
		);
	}

	/**
	 * Filters rows with `gt` condition on column `oz_sessions`.`user_id`.
	 *
	 * @param int|string $value the filter value
	 *
	 * @return static
	 */
	public function whereUserIdIsGt(string|int $value): self
	{
		return $this->filterBy(
			Operator::from('gt'),
			\OZONE\Core\Db\OZSession::COL_USER_ID,
			$value
		);
	}

	/**
	 * Filters rows with `gte` condition on column `oz_sessions`.`user_id`.
	 *
	 * @param int|string $value the filter value
	 *
	 * @return static
	 */
	public function whereUserIdIsGte(string|int $value): self
	{
		return $this->filterBy(
			Operator::from('gte'),
			\OZONE\Core\Db\OZSession::COL_USER_ID,
			$value
		);
	}

	/**
	 * Filters rows with `like` condition on column `oz_sessions`.`user_id`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 */
	public function whereUserIdIsLike(string $value): self
	{
		return $this->filterBy(
			Operator::from('like'),
			\OZONE\Core\Db\OZSession::COL_USER_ID,
			$value
		);
	}

	/**
	 * Filters rows with `not_like` condition on column `oz_sessions`.`user_id`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 */
	public function whereUserIdIsNotLike(string $value): self
	{
		return $this->filterBy(
			Operator::from('not_like'),
			\OZONE\Core\Db\OZSession::COL_USER_ID,
			$value
		);
	}

	/**
	 * Filters rows with `is_null` condition on column `oz_sessions`.`user_id`.
	 *
	 * @return static
	 */
	public function whereUserIdIsNull(): self
	{
		return $this->filterBy(
			Operator::from('is_null'),
			\OZONE\Core\Db\OZSession::COL_USER_ID
		);
	}

	/**
	 * Filters rows with `is_not_null` condition on column `oz_sessions`.`user_id`.
	 *
	 * @return static
	 */
	public function whereUserIdIsNotNull(): self
	{
		return $this->filterBy(
			Operator::from('is_not_null'),
			\OZONE\Core\Db\OZSession::COL_USER_ID
		);
	}

	/**
	 * Filters rows with `in` condition on column `oz_sessions`.`user_id`.
	 *
	 * @param array $value the filter value
	 *
	 * @return static
	 */
	public function whereUserIdIsIn(array $value): self
	{
		return $this->filterBy(
			Operator::from('in'),
			\OZONE\Core\Db\OZSession::COL_USER_ID,
			$value
		);
	}

	/**
	 * Filters rows with `not_in` condition on column `oz_sessions`.`user_id`.
	 *
	 * @param array $value the filter value
	 *
	 * @return static
	 */
	public function whereUserIdIsNotIn(array $value): self
	{
		return $this->filterBy(
			Operator::from('not_in'),
			\OZONE\Core\Db\OZSession::COL_USER_ID,
			$value
		);
	}

	/**
	 * Filters rows with `eq` condition on column `oz_sessions`.`request_source_key`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 */
	public function whereRequestSourceKeyIs(string $value): self
	{
		return $this->filterBy(
			Operator::from('eq'),
			\OZONE\Core\Db\OZSession::COL_REQUEST_SOURCE_KEY,
			$value
		);
	}

	/**
	 * Filters rows with `neq` condition on column `oz_sessions`.`request_source_key`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 */
	public function whereRequestSourceKeyIsNot(string $value): self
	{
		return $this->filterBy(
			Operator::from('neq'),
			\OZONE\Core\Db\OZSession::COL_REQUEST_SOURCE_KEY,
			$value
		);
	}

	/**
	 * Filters rows with `lt` condition on column `oz_sessions`.`request_source_key`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 */
	public function whereRequestSourceKeyIsLt(string $value): self
	{
		return $this->filterBy(
			Operator::from('lt'),
			\OZONE\Core\Db\OZSession::COL_REQUEST_SOURCE_KEY,
			$value
		);
	}

	/**
	 * Filters rows with `lte` condition on column `oz_sessions`.`request_source_key`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 */
	public function whereRequestSourceKeyIsLte(string $value): self
	{
		return $this->filterBy(
			Operator::from('lte'),
			\OZONE\Core\Db\OZSession::COL_REQUEST_SOURCE_KEY,
			$value
		);
	}

	/**
	 * Filters rows with `gt` condition on column `oz_sessions`.`request_source_key`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 */
	public function whereRequestSourceKeyIsGt(string $value): self
	{
		return $this->filterBy(
			Operator::from('gt'),
			\OZONE\Core\Db\OZSession::COL_REQUEST_SOURCE_KEY,
			$value
		);
	}

	/**
	 * Filters rows with `gte` condition on column `oz_sessions`.`request_source_key`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 */
	public function whereRequestSourceKeyIsGte(string $value): self
	{
		return $this->filterBy(
			Operator::from('gte'),
			\OZONE\Core\Db\OZSession::COL_REQUEST_SOURCE_KEY,
			$value
		);
	}

	/**
	 * Filters rows with `like` condition on column `oz_sessions`.`request_source_key`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 */
	public function whereRequestSourceKeyIsLike(string $value): self
	{
		return $this->filterBy(
			Operator::from('like'),
			\OZONE\Core\Db\OZSession::COL_REQUEST_SOURCE_KEY,
			$value
		);
	}

	/**
	 * Filters rows with `not_like` condition on column `oz_sessions`.`request_source_key`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 */
	public function whereRequestSourceKeyIsNotLike(string $value): self
	{
		return $this->filterBy(
			Operator::from('not_like'),
			\OZONE\Core\Db\OZSession::COL_REQUEST_SOURCE_KEY,
			$value
		);
	}

	/**
	 * Filters rows with `in` condition on column `oz_sessions`.`request_source_key`.
	 *
	 * @param array $value the filter value
	 *
	 * @return static
	 */
	public function whereRequestSourceKeyIsIn(array $value): self
	{
		return $this->filterBy(
			Operator::from('in'),
			\OZONE\Core\Db\OZSession::COL_REQUEST_SOURCE_KEY,
			$value
		);
	}

	/**
	 * Filters rows with `not_in` condition on column `oz_sessions`.`request_source_key`.
	 *
	 * @param array $value the filter value
	 *
	 * @return static
	 */
	public function whereRequestSourceKeyIsNotIn(array $value): self
	{
		return $this->filterBy(
			Operator::from('not_in'),
			\OZONE\Core\Db\OZSession::COL_REQUEST_SOURCE_KEY,
			$value
		);
	}

	/**
	 * Filters rows with `eq` condition on column `oz_sessions`.`expire`.
	 *
	 * @param int|string $value the filter value
	 *
	 * @return static
	 */
	public function whereExpireIs(string|int $value): self
	{
		return $this->filterBy(
			Operator::from('eq'),
			\OZONE\Core\Db\OZSession::COL_EXPIRE,
			$value
		);
	}

	/**
	 * Filters rows with `neq` condition on column `oz_sessions`.`expire`.
	 *
	 * @param int|string $value the filter value
	 *
	 * @return static
	 */
	public function whereExpireIsNot(string|int $value): self
	{
		return $this->filterBy(
			Operator::from('neq'),
			\OZONE\Core\Db\OZSession::COL_EXPIRE,
			$value
		);
	}

	/**
	 * Filters rows with `lt` condition on column `oz_sessions`.`expire`.
	 *
	 * @param int|string $value the filter value
	 *
	 * @return static
	 */
	public function whereExpireIsLt(string|int $value): self
	{
		return $this->filterBy(
			Operator::from('lt'),
			\OZONE\Core\Db\OZSession::COL_EXPIRE,
			$value
		);
	}

	/**
	 * Filters rows with `lte` condition on column `oz_sessions`.`expire`.
	 *
	 * @param int|string $value the filter value
	 *
	 * @return static
	 */
	public function whereExpireIsLte(string|int $value): self
	{
		return $this->filterBy(
			Operator::from('lte'),
			\OZONE\Core\Db\OZSession::COL_EXPIRE,
			$value
		);
	}

	/**
	 * Filters rows with `gt` condition on column `oz_sessions`.`expire`.
	 *
	 * @param int|string $value the filter value
	 *
	 * @return static
	 */
	public function whereExpireIsGt(string|int $value): self
	{
		return $this->filterBy(
			Operator::from('gt'),
			\OZONE\Core\Db\OZSession::COL_EXPIRE,
			$value
		);
	}

	/**
	 * Filters rows with `gte` condition on column `oz_sessions`.`expire`.
	 *
	 * @param int|string $value the filter value
	 *
	 * @return static
	 */
	public function whereExpireIsGte(string|int $value): self
	{
		return $this->filterBy(
			Operator::from('gte'),
			\OZONE\Core\Db\OZSession::COL_EXPIRE,
			$value
		);
	}

	/**
	 * Filters rows with `like` condition on column `oz_sessions`.`expire`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 */
	public function whereExpireIsLike(string $value): self
	{
		return $this->filterBy(
			Operator::from('like'),
			\OZONE\Core\Db\OZSession::COL_EXPIRE,
			$value
		);
	}

	/**
	 * Filters rows with `not_like` condition on column `oz_sessions`.`expire`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 */
	public function whereExpireIsNotLike(string $value): self
	{
		return $this->filterBy(
			Operator::from('not_like'),
			\OZONE\Core\Db\OZSession::COL_EXPIRE,
			$value
		);
	}

	/**
	 * Filters rows with `in` condition on column `oz_sessions`.`expire`.
	 *
	 * @param array $value the filter value
	 *
	 * @return static
	 */
	public function whereExpireIsIn(array $value): self
	{
		return $this->filterBy(
			Operator::from('in'),
			\OZONE\Core\Db\OZSession::COL_EXPIRE,
			$value
		);
	}

	/**
	 * Filters rows with `not_in` condition on column `oz_sessions`.`expire`.
	 *
	 * @param array $value the filter value
	 *
	 * @return static
	 */
	public function whereExpireIsNotIn(array $value): self
	{
		return $this->filterBy(
			Operator::from('not_in'),
			\OZONE\Core\Db\OZSession::COL_EXPIRE,
			$value
		);
	}

	/**
	 * Filters rows with `eq` condition on column `oz_sessions`.`last_seen`.
	 *
	 * @param int|string $value the filter value
	 *
	 * @return static
	 */
	public function whereLastSeenIs(string|int $value): self
	{
		return $this->filterBy(
			Operator::from('eq'),
			\OZONE\Core\Db\OZSession::COL_LAST_SEEN,
			$value
		);
	}

	/**
	 * Filters rows with `neq` condition on column `oz_sessions`.`last_seen`.
	 *
	 * @param int|string $value the filter value
	 *
	 * @return static
	 */
	public function whereLastSeenIsNot(string|int $value): self
	{
		return $this->filterBy(
			Operator::from('neq'),
			\OZONE\Core\Db\OZSession::COL_LAST_SEEN,
			$value
		);
	}

	/**
	 * Filters rows with `lt` condition on column `oz_sessions`.`last_seen`.
	 *
	 * @param int|string $value the filter value
	 *
	 * @return static
	 */
	public function whereLastSeenIsLt(string|int $value): self
	{
		return $this->filterBy(
			Operator::from('lt'),
			\OZONE\Core\Db\OZSession::COL_LAST_SEEN,
			$value
		);
	}

	/**
	 * Filters rows with `lte` condition on column `oz_sessions`.`last_seen`.
	 *
	 * @param int|string $value the filter value
	 *
	 * @return static
	 */
	public function whereLastSeenIsLte(string|int $value): self
	{
		return $this->filterBy(
			Operator::from('lte'),
			\OZONE\Core\Db\OZSession::COL_LAST_SEEN,
			$value
		);
	}

	/**
	 * Filters rows with `gt` condition on column `oz_sessions`.`last_seen`.
	 *
	 * @param int|string $value the filter value
	 *
	 * @return static
	 */
	public function whereLastSeenIsGt(string|int $value): self
	{
		return $this->filterBy(
			Operator::from('gt'),
			\OZONE\Core\Db\OZSession::COL_LAST_SEEN,
			$value
		);
	}

	/**
	 * Filters rows with `gte` condition on column `oz_sessions`.`last_seen`.
	 *
	 * @param int|string $value the filter value
	 *
	 * @return static
	 */
	public function whereLastSeenIsGte(string|int $value): self
	{
		return $this->filterBy(
			Operator::from('gte'),
			\OZONE\Core\Db\OZSession::COL_LAST_SEEN,
			$value
		);
	}

	/**
	 * Filters rows with `like` condition on column `oz_sessions`.`last_seen`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 */
	public function whereLastSeenIsLike(string $value): self
	{
		return $this->filterBy(
			Operator::from('like'),
			\OZONE\Core\Db\OZSession::COL_LAST_SEEN,
			$value
		);
	}

	/**
	 * Filters rows with `not_like` condition on column `oz_sessions`.`last_seen`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 */
	public function whereLastSeenIsNotLike(string $value): self
	{
		return $this->filterBy(
			Operator::from('not_like'),
			\OZONE\Core\Db\OZSession::COL_LAST_SEEN,
			$value
		);
	}

	/**
	 * Filters rows with `in` condition on column `oz_sessions`.`last_seen`.
	 *
	 * @param array $value the filter value
	 *
	 * @return static
	 */
	public function whereLastSeenIsIn(array $value): self
	{
		return $this->filterBy(
			Operator::from('in'),
			\OZONE\Core\Db\OZSession::COL_LAST_SEEN,
			$value
		);
	}

	/**
	 * Filters rows with `not_in` condition on column `oz_sessions`.`last_seen`.
	 *
	 * @param array $value the filter value
	 *
	 * @return static
	 */
	public function whereLastSeenIsNotIn(array $value): self
	{
		return $this->filterBy(
			Operator::from('not_in'),
			\OZONE\Core\Db\OZSession::COL_LAST_SEEN,
			$value
		);
	}

	/**
	 * Filters rows with `eq` condition on column `oz_sessions`.`data`.
	 *
	 * @param array $value the filter value
	 *
	 * @return static
	 */
	public function whereDataIs(array $value): self
	{
		return $this->filterBy(
			Operator::from('eq'),
			\OZONE\Core\Db\OZSession::COL_DATA,
			$value
		);
	}

	/**
	 * Filters rows with `neq` condition on column `oz_sessions`.`data`.
	 *
	 * @param array $value the filter value
	 *
	 * @return static
	 */
	public function whereDataIsNot(array $value): self
	{
		return $this->filterBy(
			Operator::from('neq'),
			\OZONE\Core\Db\OZSession::COL_DATA,
			$value
		);
	}

	/**
	 * Filters rows with `like` condition on column `oz_sessions`.`data`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 */
	public function whereDataIsLike(string $value): self
	{
		return $this->filterBy(
			Operator::from('like'),
			\OZONE\Core\Db\OZSession::COL_DATA,
			$value
		);
	}

	/**
	 * Filters rows with `not_like` condition on column `oz_sessions`.`data`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 */
	public function whereDataIsNotLike(string $value): self
	{
		return $this->filterBy(
			Operator::from('not_like'),
			\OZONE\Core\Db\OZSession::COL_DATA,
			$value
		);
	}

	/**
	 * Filters rows with `eq` condition on column `oz_sessions`.`created_at`.
	 *
	 * @param int|string $value the filter value
	 *
	 * @return static
	 */
	public function whereCreatedAtIs(string|int $value): self
	{
		return $this->filterBy(
			Operator::from('eq'),
			\OZONE\Core\Db\OZSession::COL_CREATED_AT,
			$value
		);
	}

	/**
	 * Filters rows with `neq` condition on column `oz_sessions`.`created_at`.
	 *
	 * @param int|string $value the filter value
	 *
	 * @return static
	 */
	public function whereCreatedAtIsNot(string|int $value): self
	{
		return $this->filterBy(
			Operator::from('neq'),
			\OZONE\Core\Db\OZSession::COL_CREATED_AT,
			$value
		);
	}

	/**
	 * Filters rows with `lt` condition on column `oz_sessions`.`created_at`.
	 *
	 * @param int|string $value the filter value
	 *
	 * @return static
	 */
	public function whereCreatedAtIsLt(string|int $value): self
	{
		return $this->filterBy(
			Operator::from('lt'),
			\OZONE\Core\Db\OZSession::COL_CREATED_AT,
			$value
		);
	}

	/**
	 * Filters rows with `lte` condition on column `oz_sessions`.`created_at`.
	 *
	 * @param int|string $value the filter value
	 *
	 * @return static
	 */
	public function whereCreatedAtIsLte(string|int $value): self
	{
		return $this->filterBy(
			Operator::from('lte'),
			\OZONE\Core\Db\OZSession::COL_CREATED_AT,
			$value
		);
	}

	/**
	 * Filters rows with `gt` condition on column `oz_sessions`.`created_at`.
	 *
	 * @param int|string $value the filter value
	 *
	 * @return static
	 */
	public function whereCreatedAtIsGt(string|int $value): self
	{
		return $this->filterBy(
			Operator::from('gt'),
			\OZONE\Core\Db\OZSession::COL_CREATED_AT,
			$value
		);
	}

	/**
	 * Filters rows with `gte` condition on column `oz_sessions`.`created_at`.
	 *
	 * @param int|string $value the filter value
	 *
	 * @return static
	 */
	public function whereCreatedAtIsGte(string|int $value): self
	{
		return $this->filterBy(
			Operator::from('gte'),
			\OZONE\Core\Db\OZSession::COL_CREATED_AT,
			$value
		);
	}

	/**
	 * Filters rows with `like` condition on column `oz_sessions`.`created_at`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 */
	public function whereCreatedAtIsLike(string $value): self
	{
		return $this->filterBy(
			Operator::from('like'),
			\OZONE\Core\Db\OZSession::COL_CREATED_AT,
			$value
		);
	}

	/**
	 * Filters rows with `not_like` condition on column `oz_sessions`.`created_at`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 */
	public function whereCreatedAtIsNotLike(string $value): self
	{
		return $this->filterBy(
			Operator::from('not_like'),
			\OZONE\Core\Db\OZSession::COL_CREATED_AT,
			$value
		);
	}

	/**
	 * Filters rows with `in` condition on column `oz_sessions`.`created_at`.
	 *
	 * @param array $value the filter value
	 *
	 * @return static
	 */
	public function whereCreatedAtIsIn(array $value): self
	{
		return $this->filterBy(
			Operator::from('in'),
			\OZONE\Core\Db\OZSession::COL_CREATED_AT,
			$value
		);
	}

	/**
	 * Filters rows with `not_in` condition on column `oz_sessions`.`created_at`.
	 *
	 * @param array $value the filter value
	 *
	 * @return static
	 */
	public function whereCreatedAtIsNotIn(array $value): self
	{
		return $this->filterBy(
			Operator::from('not_in'),
			\OZONE\Core\Db\OZSession::COL_CREATED_AT,
			$value
		);
	}

	/**
	 * Filters rows with `eq` condition on column `oz_sessions`.`updated_at`.
	 *
	 * @param int|string $value the filter value
	 *
	 * @return static
	 */
	public function whereUpdatedAtIs(string|int $value): self
	{
		return $this->filterBy(
			Operator::from('eq'),
			\OZONE\Core\Db\OZSession::COL_UPDATED_AT,
			$value
		);
	}

	/**
	 * Filters rows with `neq` condition on column `oz_sessions`.`updated_at`.
	 *
	 * @param int|string $value the filter value
	 *
	 * @return static
	 */
	public function whereUpdatedAtIsNot(string|int $value): self
	{
		return $this->filterBy(
			Operator::from('neq'),
			\OZONE\Core\Db\OZSession::COL_UPDATED_AT,
			$value
		);
	}

	/**
	 * Filters rows with `lt` condition on column `oz_sessions`.`updated_at`.
	 *
	 * @param int|string $value the filter value
	 *
	 * @return static
	 */
	public function whereUpdatedAtIsLt(string|int $value): self
	{
		return $this->filterBy(
			Operator::from('lt'),
			\OZONE\Core\Db\OZSession::COL_UPDATED_AT,
			$value
		);
	}

	/**
	 * Filters rows with `lte` condition on column `oz_sessions`.`updated_at`.
	 *
	 * @param int|string $value the filter value
	 *
	 * @return static
	 */
	public function whereUpdatedAtIsLte(string|int $value): self
	{
		return $this->filterBy(
			Operator::from('lte'),
			\OZONE\Core\Db\OZSession::COL_UPDATED_AT,
			$value
		);
	}

	/**
	 * Filters rows with `gt` condition on column `oz_sessions`.`updated_at`.
	 *
	 * @param int|string $value the filter value
	 *
	 * @return static
	 */
	public function whereUpdatedAtIsGt(string|int $value): self
	{
		return $this->filterBy(
			Operator::from('gt'),
			\OZONE\Core\Db\OZSession::COL_UPDATED_AT,
			$value
		);
	}

	/**
	 * Filters rows with `gte` condition on column `oz_sessions`.`updated_at`.
	 *
	 * @param int|string $value the filter value
	 *
	 * @return static
	 */
	public function whereUpdatedAtIsGte(string|int $value): self
	{
		return $this->filterBy(
			Operator::from('gte'),
			\OZONE\Core\Db\OZSession::COL_UPDATED_AT,
			$value
		);
	}

	/**
	 * Filters rows with `like` condition on column `oz_sessions`.`updated_at`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 */
	public function whereUpdatedAtIsLike(string $value): self
	{
		return $this->filterBy(
			Operator::from('like'),
			\OZONE\Core\Db\OZSession::COL_UPDATED_AT,
			$value
		);
	}

	/**
	 * Filters rows with `not_like` condition on column `oz_sessions`.`updated_at`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 */
	public function whereUpdatedAtIsNotLike(string $value): self
	{
		return $this->filterBy(
			Operator::from('not_like'),
			\OZONE\Core\Db\OZSession::COL_UPDATED_AT,
			$value
		);
	}

	/**
	 * Filters rows with `in` condition on column `oz_sessions`.`updated_at`.
	 *
	 * @param array $value the filter value
	 *
	 * @return static
	 */
	public function whereUpdatedAtIsIn(array $value): self
	{
		return $this->filterBy(
			Operator::from('in'),
			\OZONE\Core\Db\OZSession::COL_UPDATED_AT,
			$value
		);
	}

	/**
	 * Filters rows with `not_in` condition on column `oz_sessions`.`updated_at`.
	 *
	 * @param array $value the filter value
	 *
	 * @return static
	 */
	public function whereUpdatedAtIsNotIn(array $value): self
	{
		return $this->filterBy(
			Operator::from('not_in'),
			\OZONE\Core\Db\OZSession::COL_UPDATED_AT,
			$value
		);
	}

	/**
	 * Filters rows with `eq` condition on column `oz_sessions`.`is_valid`.
	 *
	 * @param bool $value the filter value
	 *
	 * @return static
	 */
	public function whereIsValidIs(bool $value): self
	{
		return $this->filterBy(
			Operator::from('eq'),
			\OZONE\Core\Db\OZSession::COL_IS_VALID,
			$value
		);
	}

	/**
	 * Filters rows with `neq` condition on column `oz_sessions`.`is_valid`.
	 *
	 * @param bool $value the filter value
	 *
	 * @return static
	 */
	public function whereIsValidIsNot(bool $value): self
	{
		return $this->filterBy(
			Operator::from('neq'),
			\OZONE\Core\Db\OZSession::COL_IS_VALID,
			$value
		);
	}

	/**
	 * Filters rows with `is_false` condition on column `oz_sessions`.`is_valid`.
	 *
	 * @return static
	 */
	public function whereIsNotValid(): self
	{
		return $this->filterBy(
			Operator::from('is_false'),
			\OZONE\Core\Db\OZSession::COL_IS_VALID
		);
	}

	/**
	 * Filters rows with `is_true` condition on column `oz_sessions`.`is_valid`.
	 *
	 * @return static
	 */
	public function whereIsValid(): self
	{
		return $this->filterBy(
			Operator::from('is_true'),
			\OZONE\Core\Db\OZSession::COL_IS_VALID
		);
	}
}
