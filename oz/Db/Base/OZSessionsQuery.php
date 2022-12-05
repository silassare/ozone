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

/**
 * Class OZSessionsQuery.
 *
 * @method \OZONE\OZ\Db\OZSessionsResults find(?int $max = null, int $offset = 0, array $order_by = [])
 */
abstract class OZSessionsQuery extends \Gobl\ORM\ORMTableQuery
{
	/**
	 * OZSessionsQuery constructor.
	 */
	public function __construct()
	{
		parent::__construct(
			\OZONE\OZ\Db\OZSession::TABLE_NAMESPACE,
			\OZONE\OZ\Db\OZSession::TABLE_NAME
		);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @return static
	 */
	public static function createInstance(): static
	{
		return new \OZONE\OZ\Db\OZSessionsQuery();
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
	 *
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereIdIs(string $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('eq'),
			\OZONE\OZ\Db\OZSession::COL_ID,
			$value
		);
	}

	/**
	 * Filters rows with `neq` condition on column `oz_sessions`.`id`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 *
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereIdIsNot(string $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('neq'),
			\OZONE\OZ\Db\OZSession::COL_ID,
			$value
		);
	}

	/**
	 * Filters rows with `lt` condition on column `oz_sessions`.`id`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 *
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereIdIsLt(string $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('lt'),
			\OZONE\OZ\Db\OZSession::COL_ID,
			$value
		);
	}

	/**
	 * Filters rows with `lte` condition on column `oz_sessions`.`id`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 *
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereIdIsLte(string $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('lte'),
			\OZONE\OZ\Db\OZSession::COL_ID,
			$value
		);
	}

	/**
	 * Filters rows with `gt` condition on column `oz_sessions`.`id`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 *
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereIdIsGt(string $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('gt'),
			\OZONE\OZ\Db\OZSession::COL_ID,
			$value
		);
	}

	/**
	 * Filters rows with `gte` condition on column `oz_sessions`.`id`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 *
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereIdIsGte(string $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('gte'),
			\OZONE\OZ\Db\OZSession::COL_ID,
			$value
		);
	}

	/**
	 * Filters rows with `like` condition on column `oz_sessions`.`id`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 *
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereIdIsLike(string $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('like'),
			\OZONE\OZ\Db\OZSession::COL_ID,
			$value
		);
	}

	/**
	 * Filters rows with `not_like` condition on column `oz_sessions`.`id`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 *
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereIdIsNotLike(string $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('not_like'),
			\OZONE\OZ\Db\OZSession::COL_ID,
			$value
		);
	}

	/**
	 * Filters rows with `in` condition on column `oz_sessions`.`id`.
	 *
	 * @param array $value the filter value
	 *
	 * @return static
	 *
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereIdIsIn(array $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('in'),
			\OZONE\OZ\Db\OZSession::COL_ID,
			$value
		);
	}

	/**
	 * Filters rows with `not_in` condition on column `oz_sessions`.`id`.
	 *
	 * @param array $value the filter value
	 *
	 * @return static
	 *
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereIdIsNotIn(array $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('not_in'),
			\OZONE\OZ\Db\OZSession::COL_ID,
			$value
		);
	}

	/**
	 * Filters rows with `eq` condition on column `oz_sessions`.`client_id`.
	 *
	 * @param int|string $value the filter value
	 *
	 * @return static
	 *
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereClientIdIs(string|int $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('eq'),
			\OZONE\OZ\Db\OZSession::COL_CLIENT_ID,
			$value
		);
	}

	/**
	 * Filters rows with `neq` condition on column `oz_sessions`.`client_id`.
	 *
	 * @param int|string $value the filter value
	 *
	 * @return static
	 *
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereClientIdIsNot(string|int $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('neq'),
			\OZONE\OZ\Db\OZSession::COL_CLIENT_ID,
			$value
		);
	}

	/**
	 * Filters rows with `lt` condition on column `oz_sessions`.`client_id`.
	 *
	 * @param int|string $value the filter value
	 *
	 * @return static
	 *
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereClientIdIsLt(string|int $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('lt'),
			\OZONE\OZ\Db\OZSession::COL_CLIENT_ID,
			$value
		);
	}

	/**
	 * Filters rows with `lte` condition on column `oz_sessions`.`client_id`.
	 *
	 * @param int|string $value the filter value
	 *
	 * @return static
	 *
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereClientIdIsLte(string|int $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('lte'),
			\OZONE\OZ\Db\OZSession::COL_CLIENT_ID,
			$value
		);
	}

	/**
	 * Filters rows with `gt` condition on column `oz_sessions`.`client_id`.
	 *
	 * @param int|string $value the filter value
	 *
	 * @return static
	 *
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereClientIdIsGt(string|int $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('gt'),
			\OZONE\OZ\Db\OZSession::COL_CLIENT_ID,
			$value
		);
	}

	/**
	 * Filters rows with `gte` condition on column `oz_sessions`.`client_id`.
	 *
	 * @param int|string $value the filter value
	 *
	 * @return static
	 *
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereClientIdIsGte(string|int $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('gte'),
			\OZONE\OZ\Db\OZSession::COL_CLIENT_ID,
			$value
		);
	}

	/**
	 * Filters rows with `like` condition on column `oz_sessions`.`client_id`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 *
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereClientIdIsLike(string $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('like'),
			\OZONE\OZ\Db\OZSession::COL_CLIENT_ID,
			$value
		);
	}

	/**
	 * Filters rows with `not_like` condition on column `oz_sessions`.`client_id`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 *
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereClientIdIsNotLike(string $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('not_like'),
			\OZONE\OZ\Db\OZSession::COL_CLIENT_ID,
			$value
		);
	}

	/**
	 * Filters rows with `in` condition on column `oz_sessions`.`client_id`.
	 *
	 * @param array $value the filter value
	 *
	 * @return static
	 *
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereClientIdIsIn(array $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('in'),
			\OZONE\OZ\Db\OZSession::COL_CLIENT_ID,
			$value
		);
	}

	/**
	 * Filters rows with `not_in` condition on column `oz_sessions`.`client_id`.
	 *
	 * @param array $value the filter value
	 *
	 * @return static
	 *
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereClientIdIsNotIn(array $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('not_in'),
			\OZONE\OZ\Db\OZSession::COL_CLIENT_ID,
			$value
		);
	}

	/**
	 * Filters rows with `eq` condition on column `oz_sessions`.`user_id`.
	 *
	 * @param int|string $value the filter value
	 *
	 * @return static
	 *
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereUserIdIs(string|int $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('eq'),
			\OZONE\OZ\Db\OZSession::COL_USER_ID,
			$value
		);
	}

	/**
	 * Filters rows with `neq` condition on column `oz_sessions`.`user_id`.
	 *
	 * @param int|string $value the filter value
	 *
	 * @return static
	 *
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereUserIdIsNot(string|int $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('neq'),
			\OZONE\OZ\Db\OZSession::COL_USER_ID,
			$value
		);
	}

	/**
	 * Filters rows with `lt` condition on column `oz_sessions`.`user_id`.
	 *
	 * @param int|string $value the filter value
	 *
	 * @return static
	 *
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereUserIdIsLt(string|int $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('lt'),
			\OZONE\OZ\Db\OZSession::COL_USER_ID,
			$value
		);
	}

	/**
	 * Filters rows with `lte` condition on column `oz_sessions`.`user_id`.
	 *
	 * @param int|string $value the filter value
	 *
	 * @return static
	 *
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereUserIdIsLte(string|int $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('lte'),
			\OZONE\OZ\Db\OZSession::COL_USER_ID,
			$value
		);
	}

	/**
	 * Filters rows with `gt` condition on column `oz_sessions`.`user_id`.
	 *
	 * @param int|string $value the filter value
	 *
	 * @return static
	 *
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereUserIdIsGt(string|int $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('gt'),
			\OZONE\OZ\Db\OZSession::COL_USER_ID,
			$value
		);
	}

	/**
	 * Filters rows with `gte` condition on column `oz_sessions`.`user_id`.
	 *
	 * @param int|string $value the filter value
	 *
	 * @return static
	 *
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereUserIdIsGte(string|int $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('gte'),
			\OZONE\OZ\Db\OZSession::COL_USER_ID,
			$value
		);
	}

	/**
	 * Filters rows with `like` condition on column `oz_sessions`.`user_id`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 *
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereUserIdIsLike(string $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('like'),
			\OZONE\OZ\Db\OZSession::COL_USER_ID,
			$value
		);
	}

	/**
	 * Filters rows with `not_like` condition on column `oz_sessions`.`user_id`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 *
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereUserIdIsNotLike(string $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('not_like'),
			\OZONE\OZ\Db\OZSession::COL_USER_ID,
			$value
		);
	}

	/**
	 * Filters rows with `is_null` condition on column `oz_sessions`.`user_id`.
	 *
	 * @return static
	 *
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereUserIdIsNull(): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('is_null'),
			\OZONE\OZ\Db\OZSession::COL_USER_ID
		);
	}

	/**
	 * Filters rows with `is_not_null` condition on column `oz_sessions`.`user_id`.
	 *
	 * @return static
	 *
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereUserIdIsNotNull(): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('is_not_null'),
			\OZONE\OZ\Db\OZSession::COL_USER_ID
		);
	}

	/**
	 * Filters rows with `in` condition on column `oz_sessions`.`user_id`.
	 *
	 * @param array $value the filter value
	 *
	 * @return static
	 *
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereUserIdIsIn(array $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('in'),
			\OZONE\OZ\Db\OZSession::COL_USER_ID,
			$value
		);
	}

	/**
	 * Filters rows with `not_in` condition on column `oz_sessions`.`user_id`.
	 *
	 * @param array $value the filter value
	 *
	 * @return static
	 *
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereUserIdIsNotIn(array $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('not_in'),
			\OZONE\OZ\Db\OZSession::COL_USER_ID,
			$value
		);
	}

	/**
	 * Filters rows with `eq` condition on column `oz_sessions`.`token`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 *
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereTokenIs(string $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('eq'),
			\OZONE\OZ\Db\OZSession::COL_TOKEN,
			$value
		);
	}

	/**
	 * Filters rows with `neq` condition on column `oz_sessions`.`token`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 *
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereTokenIsNot(string $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('neq'),
			\OZONE\OZ\Db\OZSession::COL_TOKEN,
			$value
		);
	}

	/**
	 * Filters rows with `lt` condition on column `oz_sessions`.`token`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 *
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereTokenIsLt(string $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('lt'),
			\OZONE\OZ\Db\OZSession::COL_TOKEN,
			$value
		);
	}

	/**
	 * Filters rows with `lte` condition on column `oz_sessions`.`token`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 *
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereTokenIsLte(string $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('lte'),
			\OZONE\OZ\Db\OZSession::COL_TOKEN,
			$value
		);
	}

	/**
	 * Filters rows with `gt` condition on column `oz_sessions`.`token`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 *
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereTokenIsGt(string $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('gt'),
			\OZONE\OZ\Db\OZSession::COL_TOKEN,
			$value
		);
	}

	/**
	 * Filters rows with `gte` condition on column `oz_sessions`.`token`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 *
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereTokenIsGte(string $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('gte'),
			\OZONE\OZ\Db\OZSession::COL_TOKEN,
			$value
		);
	}

	/**
	 * Filters rows with `like` condition on column `oz_sessions`.`token`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 *
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereTokenIsLike(string $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('like'),
			\OZONE\OZ\Db\OZSession::COL_TOKEN,
			$value
		);
	}

	/**
	 * Filters rows with `not_like` condition on column `oz_sessions`.`token`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 *
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereTokenIsNotLike(string $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('not_like'),
			\OZONE\OZ\Db\OZSession::COL_TOKEN,
			$value
		);
	}

	/**
	 * Filters rows with `in` condition on column `oz_sessions`.`token`.
	 *
	 * @param array $value the filter value
	 *
	 * @return static
	 *
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereTokenIsIn(array $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('in'),
			\OZONE\OZ\Db\OZSession::COL_TOKEN,
			$value
		);
	}

	/**
	 * Filters rows with `not_in` condition on column `oz_sessions`.`token`.
	 *
	 * @param array $value the filter value
	 *
	 * @return static
	 *
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereTokenIsNotIn(array $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('not_in'),
			\OZONE\OZ\Db\OZSession::COL_TOKEN,
			$value
		);
	}

	/**
	 * Filters rows with `eq` condition on column `oz_sessions`.`expire`.
	 *
	 * @param int|string $value the filter value
	 *
	 * @return static
	 *
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereExpireIs(string|int $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('eq'),
			\OZONE\OZ\Db\OZSession::COL_EXPIRE,
			$value
		);
	}

	/**
	 * Filters rows with `neq` condition on column `oz_sessions`.`expire`.
	 *
	 * @param int|string $value the filter value
	 *
	 * @return static
	 *
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereExpireIsNot(string|int $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('neq'),
			\OZONE\OZ\Db\OZSession::COL_EXPIRE,
			$value
		);
	}

	/**
	 * Filters rows with `lt` condition on column `oz_sessions`.`expire`.
	 *
	 * @param int|string $value the filter value
	 *
	 * @return static
	 *
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereExpireIsLt(string|int $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('lt'),
			\OZONE\OZ\Db\OZSession::COL_EXPIRE,
			$value
		);
	}

	/**
	 * Filters rows with `lte` condition on column `oz_sessions`.`expire`.
	 *
	 * @param int|string $value the filter value
	 *
	 * @return static
	 *
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereExpireIsLte(string|int $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('lte'),
			\OZONE\OZ\Db\OZSession::COL_EXPIRE,
			$value
		);
	}

	/**
	 * Filters rows with `gt` condition on column `oz_sessions`.`expire`.
	 *
	 * @param int|string $value the filter value
	 *
	 * @return static
	 *
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereExpireIsGt(string|int $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('gt'),
			\OZONE\OZ\Db\OZSession::COL_EXPIRE,
			$value
		);
	}

	/**
	 * Filters rows with `gte` condition on column `oz_sessions`.`expire`.
	 *
	 * @param int|string $value the filter value
	 *
	 * @return static
	 *
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereExpireIsGte(string|int $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('gte'),
			\OZONE\OZ\Db\OZSession::COL_EXPIRE,
			$value
		);
	}

	/**
	 * Filters rows with `like` condition on column `oz_sessions`.`expire`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 *
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereExpireIsLike(string $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('like'),
			\OZONE\OZ\Db\OZSession::COL_EXPIRE,
			$value
		);
	}

	/**
	 * Filters rows with `not_like` condition on column `oz_sessions`.`expire`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 *
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereExpireIsNotLike(string $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('not_like'),
			\OZONE\OZ\Db\OZSession::COL_EXPIRE,
			$value
		);
	}

	/**
	 * Filters rows with `in` condition on column `oz_sessions`.`expire`.
	 *
	 * @param array $value the filter value
	 *
	 * @return static
	 *
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereExpireIsIn(array $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('in'),
			\OZONE\OZ\Db\OZSession::COL_EXPIRE,
			$value
		);
	}

	/**
	 * Filters rows with `not_in` condition on column `oz_sessions`.`expire`.
	 *
	 * @param array $value the filter value
	 *
	 * @return static
	 *
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereExpireIsNotIn(array $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('not_in'),
			\OZONE\OZ\Db\OZSession::COL_EXPIRE,
			$value
		);
	}

	/**
	 * Filters rows with `eq` condition on column `oz_sessions`.`verified`.
	 *
	 * @param bool $value the filter value
	 *
	 * @return static
	 *
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereVerifiedIs(bool $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('eq'),
			\OZONE\OZ\Db\OZSession::COL_VERIFIED,
			$value
		);
	}

	/**
	 * Filters rows with `neq` condition on column `oz_sessions`.`verified`.
	 *
	 * @param bool $value the filter value
	 *
	 * @return static
	 *
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereVerifiedIsNot(bool $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('neq'),
			\OZONE\OZ\Db\OZSession::COL_VERIFIED,
			$value
		);
	}

	/**
	 * Filters rows with `is_false` condition on column `oz_sessions`.`verified`.
	 *
	 * @return static
	 *
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereVerifiedIsFalse(): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('is_false'),
			\OZONE\OZ\Db\OZSession::COL_VERIFIED
		);
	}

	/**
	 * Filters rows with `is_true` condition on column `oz_sessions`.`verified`.
	 *
	 * @return static
	 *
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereVerifiedIsTrue(): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('is_true'),
			\OZONE\OZ\Db\OZSession::COL_VERIFIED
		);
	}

	/**
	 * Filters rows with `eq` condition on column `oz_sessions`.`last_seen`.
	 *
	 * @param int|string $value the filter value
	 *
	 * @return static
	 *
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereLastSeenIs(string|int $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('eq'),
			\OZONE\OZ\Db\OZSession::COL_LAST_SEEN,
			$value
		);
	}

	/**
	 * Filters rows with `neq` condition on column `oz_sessions`.`last_seen`.
	 *
	 * @param int|string $value the filter value
	 *
	 * @return static
	 *
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereLastSeenIsNot(string|int $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('neq'),
			\OZONE\OZ\Db\OZSession::COL_LAST_SEEN,
			$value
		);
	}

	/**
	 * Filters rows with `lt` condition on column `oz_sessions`.`last_seen`.
	 *
	 * @param int|string $value the filter value
	 *
	 * @return static
	 *
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereLastSeenIsLt(string|int $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('lt'),
			\OZONE\OZ\Db\OZSession::COL_LAST_SEEN,
			$value
		);
	}

	/**
	 * Filters rows with `lte` condition on column `oz_sessions`.`last_seen`.
	 *
	 * @param int|string $value the filter value
	 *
	 * @return static
	 *
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereLastSeenIsLte(string|int $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('lte'),
			\OZONE\OZ\Db\OZSession::COL_LAST_SEEN,
			$value
		);
	}

	/**
	 * Filters rows with `gt` condition on column `oz_sessions`.`last_seen`.
	 *
	 * @param int|string $value the filter value
	 *
	 * @return static
	 *
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereLastSeenIsGt(string|int $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('gt'),
			\OZONE\OZ\Db\OZSession::COL_LAST_SEEN,
			$value
		);
	}

	/**
	 * Filters rows with `gte` condition on column `oz_sessions`.`last_seen`.
	 *
	 * @param int|string $value the filter value
	 *
	 * @return static
	 *
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereLastSeenIsGte(string|int $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('gte'),
			\OZONE\OZ\Db\OZSession::COL_LAST_SEEN,
			$value
		);
	}

	/**
	 * Filters rows with `like` condition on column `oz_sessions`.`last_seen`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 *
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereLastSeenIsLike(string $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('like'),
			\OZONE\OZ\Db\OZSession::COL_LAST_SEEN,
			$value
		);
	}

	/**
	 * Filters rows with `not_like` condition on column `oz_sessions`.`last_seen`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 *
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereLastSeenIsNotLike(string $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('not_like'),
			\OZONE\OZ\Db\OZSession::COL_LAST_SEEN,
			$value
		);
	}

	/**
	 * Filters rows with `in` condition on column `oz_sessions`.`last_seen`.
	 *
	 * @param array $value the filter value
	 *
	 * @return static
	 *
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereLastSeenIsIn(array $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('in'),
			\OZONE\OZ\Db\OZSession::COL_LAST_SEEN,
			$value
		);
	}

	/**
	 * Filters rows with `not_in` condition on column `oz_sessions`.`last_seen`.
	 *
	 * @param array $value the filter value
	 *
	 * @return static
	 *
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereLastSeenIsNotIn(array $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('not_in'),
			\OZONE\OZ\Db\OZSession::COL_LAST_SEEN,
			$value
		);
	}

	/**
	 * Filters rows with `eq` condition on column `oz_sessions`.`data`.
	 *
	 * @param array $value the filter value
	 *
	 * @return static
	 *
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereDataIs(array $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('eq'),
			\OZONE\OZ\Db\OZSession::COL_DATA,
			$value
		);
	}

	/**
	 * Filters rows with `neq` condition on column `oz_sessions`.`data`.
	 *
	 * @param array $value the filter value
	 *
	 * @return static
	 *
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereDataIsNot(array $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('neq'),
			\OZONE\OZ\Db\OZSession::COL_DATA,
			$value
		);
	}

	/**
	 * Filters rows with `like` condition on column `oz_sessions`.`data`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 *
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereDataIsLike(string $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('like'),
			\OZONE\OZ\Db\OZSession::COL_DATA,
			$value
		);
	}

	/**
	 * Filters rows with `not_like` condition on column `oz_sessions`.`data`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 *
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereDataIsNotLike(string $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('not_like'),
			\OZONE\OZ\Db\OZSession::COL_DATA,
			$value
		);
	}

	/**
	 * Filters rows with `eq` condition on column `oz_sessions`.`created_at`.
	 *
	 * @param int|string $value the filter value
	 *
	 * @return static
	 *
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereCreatedAtIs(string|int $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('eq'),
			\OZONE\OZ\Db\OZSession::COL_CREATED_AT,
			$value
		);
	}

	/**
	 * Filters rows with `neq` condition on column `oz_sessions`.`created_at`.
	 *
	 * @param int|string $value the filter value
	 *
	 * @return static
	 *
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereCreatedAtIsNot(string|int $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('neq'),
			\OZONE\OZ\Db\OZSession::COL_CREATED_AT,
			$value
		);
	}

	/**
	 * Filters rows with `lt` condition on column `oz_sessions`.`created_at`.
	 *
	 * @param int|string $value the filter value
	 *
	 * @return static
	 *
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereCreatedAtIsLt(string|int $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('lt'),
			\OZONE\OZ\Db\OZSession::COL_CREATED_AT,
			$value
		);
	}

	/**
	 * Filters rows with `lte` condition on column `oz_sessions`.`created_at`.
	 *
	 * @param int|string $value the filter value
	 *
	 * @return static
	 *
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereCreatedAtIsLte(string|int $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('lte'),
			\OZONE\OZ\Db\OZSession::COL_CREATED_AT,
			$value
		);
	}

	/**
	 * Filters rows with `gt` condition on column `oz_sessions`.`created_at`.
	 *
	 * @param int|string $value the filter value
	 *
	 * @return static
	 *
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereCreatedAtIsGt(string|int $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('gt'),
			\OZONE\OZ\Db\OZSession::COL_CREATED_AT,
			$value
		);
	}

	/**
	 * Filters rows with `gte` condition on column `oz_sessions`.`created_at`.
	 *
	 * @param int|string $value the filter value
	 *
	 * @return static
	 *
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereCreatedAtIsGte(string|int $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('gte'),
			\OZONE\OZ\Db\OZSession::COL_CREATED_AT,
			$value
		);
	}

	/**
	 * Filters rows with `like` condition on column `oz_sessions`.`created_at`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 *
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereCreatedAtIsLike(string $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('like'),
			\OZONE\OZ\Db\OZSession::COL_CREATED_AT,
			$value
		);
	}

	/**
	 * Filters rows with `not_like` condition on column `oz_sessions`.`created_at`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 *
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereCreatedAtIsNotLike(string $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('not_like'),
			\OZONE\OZ\Db\OZSession::COL_CREATED_AT,
			$value
		);
	}

	/**
	 * Filters rows with `in` condition on column `oz_sessions`.`created_at`.
	 *
	 * @param array $value the filter value
	 *
	 * @return static
	 *
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereCreatedAtIsIn(array $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('in'),
			\OZONE\OZ\Db\OZSession::COL_CREATED_AT,
			$value
		);
	}

	/**
	 * Filters rows with `not_in` condition on column `oz_sessions`.`created_at`.
	 *
	 * @param array $value the filter value
	 *
	 * @return static
	 *
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereCreatedAtIsNotIn(array $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('not_in'),
			\OZONE\OZ\Db\OZSession::COL_CREATED_AT,
			$value
		);
	}

	/**
	 * Filters rows with `eq` condition on column `oz_sessions`.`updated_at`.
	 *
	 * @param int|string $value the filter value
	 *
	 * @return static
	 *
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereUpdatedAtIs(string|int $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('eq'),
			\OZONE\OZ\Db\OZSession::COL_UPDATED_AT,
			$value
		);
	}

	/**
	 * Filters rows with `neq` condition on column `oz_sessions`.`updated_at`.
	 *
	 * @param int|string $value the filter value
	 *
	 * @return static
	 *
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereUpdatedAtIsNot(string|int $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('neq'),
			\OZONE\OZ\Db\OZSession::COL_UPDATED_AT,
			$value
		);
	}

	/**
	 * Filters rows with `lt` condition on column `oz_sessions`.`updated_at`.
	 *
	 * @param int|string $value the filter value
	 *
	 * @return static
	 *
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereUpdatedAtIsLt(string|int $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('lt'),
			\OZONE\OZ\Db\OZSession::COL_UPDATED_AT,
			$value
		);
	}

	/**
	 * Filters rows with `lte` condition on column `oz_sessions`.`updated_at`.
	 *
	 * @param int|string $value the filter value
	 *
	 * @return static
	 *
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereUpdatedAtIsLte(string|int $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('lte'),
			\OZONE\OZ\Db\OZSession::COL_UPDATED_AT,
			$value
		);
	}

	/**
	 * Filters rows with `gt` condition on column `oz_sessions`.`updated_at`.
	 *
	 * @param int|string $value the filter value
	 *
	 * @return static
	 *
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereUpdatedAtIsGt(string|int $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('gt'),
			\OZONE\OZ\Db\OZSession::COL_UPDATED_AT,
			$value
		);
	}

	/**
	 * Filters rows with `gte` condition on column `oz_sessions`.`updated_at`.
	 *
	 * @param int|string $value the filter value
	 *
	 * @return static
	 *
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereUpdatedAtIsGte(string|int $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('gte'),
			\OZONE\OZ\Db\OZSession::COL_UPDATED_AT,
			$value
		);
	}

	/**
	 * Filters rows with `like` condition on column `oz_sessions`.`updated_at`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 *
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereUpdatedAtIsLike(string $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('like'),
			\OZONE\OZ\Db\OZSession::COL_UPDATED_AT,
			$value
		);
	}

	/**
	 * Filters rows with `not_like` condition on column `oz_sessions`.`updated_at`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 *
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereUpdatedAtIsNotLike(string $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('not_like'),
			\OZONE\OZ\Db\OZSession::COL_UPDATED_AT,
			$value
		);
	}

	/**
	 * Filters rows with `in` condition on column `oz_sessions`.`updated_at`.
	 *
	 * @param array $value the filter value
	 *
	 * @return static
	 *
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereUpdatedAtIsIn(array $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('in'),
			\OZONE\OZ\Db\OZSession::COL_UPDATED_AT,
			$value
		);
	}

	/**
	 * Filters rows with `not_in` condition on column `oz_sessions`.`updated_at`.
	 *
	 * @param array $value the filter value
	 *
	 * @return static
	 *
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereUpdatedAtIsNotIn(array $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('not_in'),
			\OZONE\OZ\Db\OZSession::COL_UPDATED_AT,
			$value
		);
	}

	/**
	 * Filters rows with `eq` condition on column `oz_sessions`.`valid`.
	 *
	 * @param bool $value the filter value
	 *
	 * @return static
	 *
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereValidIs(bool $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('eq'),
			\OZONE\OZ\Db\OZSession::COL_VALID,
			$value
		);
	}

	/**
	 * Filters rows with `neq` condition on column `oz_sessions`.`valid`.
	 *
	 * @param bool $value the filter value
	 *
	 * @return static
	 *
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereValidIsNot(bool $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('neq'),
			\OZONE\OZ\Db\OZSession::COL_VALID,
			$value
		);
	}

	/**
	 * Filters rows with `is_false` condition on column `oz_sessions`.`valid`.
	 *
	 * @return static
	 *
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereValidIsFalse(): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('is_false'),
			\OZONE\OZ\Db\OZSession::COL_VALID
		);
	}

	/**
	 * Filters rows with `is_true` condition on column `oz_sessions`.`valid`.
	 *
	 * @return static
	 *
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereValidIsTrue(): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('is_true'),
			\OZONE\OZ\Db\OZSession::COL_VALID
		);
	}
}
