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
 * Class OZClientsQuery.
 *
 * @method \OZONE\OZ\Db\OZClientsResults find(?int $max = null, int $offset = 0, array $order_by = [])
 */
abstract class OZClientsQuery extends \Gobl\ORM\ORMTableQuery
{
	/**
	 * OZClientsQuery constructor.
	 */
	public function __construct()
	{
		parent::__construct(
			\OZONE\OZ\Db\OZClient::TABLE_NAMESPACE,
			\OZONE\OZ\Db\OZClient::TABLE_NAME
		);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @return static
	 */
	public static function createInstance(): static
	{
		return new \OZONE\OZ\Db\OZClientsQuery();
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
	 * Filters rows with `eq` condition on column `oz_clients`.`id`.
	 *
	 * @param int|string $value the filter value
	 *
	 * @return static
	 */
	public function whereIdIs(string|int $value): self
	{
		return $this->filterBy(
			Operator::from('eq'),
			\OZONE\OZ\Db\OZClient::COL_ID,
			$value
		);
	}

	/**
	 * Filters rows with `neq` condition on column `oz_clients`.`id`.
	 *
	 * @param int|string $value the filter value
	 *
	 * @return static
	 */
	public function whereIdIsNot(string|int $value): self
	{
		return $this->filterBy(
			Operator::from('neq'),
			\OZONE\OZ\Db\OZClient::COL_ID,
			$value
		);
	}

	/**
	 * Filters rows with `lt` condition on column `oz_clients`.`id`.
	 *
	 * @param int|string $value the filter value
	 *
	 * @return static
	 */
	public function whereIdIsLt(string|int $value): self
	{
		return $this->filterBy(
			Operator::from('lt'),
			\OZONE\OZ\Db\OZClient::COL_ID,
			$value
		);
	}

	/**
	 * Filters rows with `lte` condition on column `oz_clients`.`id`.
	 *
	 * @param int|string $value the filter value
	 *
	 * @return static
	 */
	public function whereIdIsLte(string|int $value): self
	{
		return $this->filterBy(
			Operator::from('lte'),
			\OZONE\OZ\Db\OZClient::COL_ID,
			$value
		);
	}

	/**
	 * Filters rows with `gt` condition on column `oz_clients`.`id`.
	 *
	 * @param int|string $value the filter value
	 *
	 * @return static
	 */
	public function whereIdIsGt(string|int $value): self
	{
		return $this->filterBy(
			Operator::from('gt'),
			\OZONE\OZ\Db\OZClient::COL_ID,
			$value
		);
	}

	/**
	 * Filters rows with `gte` condition on column `oz_clients`.`id`.
	 *
	 * @param int|string $value the filter value
	 *
	 * @return static
	 */
	public function whereIdIsGte(string|int $value): self
	{
		return $this->filterBy(
			Operator::from('gte'),
			\OZONE\OZ\Db\OZClient::COL_ID,
			$value
		);
	}

	/**
	 * Filters rows with `like` condition on column `oz_clients`.`id`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 */
	public function whereIdIsLike(string $value): self
	{
		return $this->filterBy(
			Operator::from('like'),
			\OZONE\OZ\Db\OZClient::COL_ID,
			$value
		);
	}

	/**
	 * Filters rows with `not_like` condition on column `oz_clients`.`id`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 */
	public function whereIdIsNotLike(string $value): self
	{
		return $this->filterBy(
			Operator::from('not_like'),
			\OZONE\OZ\Db\OZClient::COL_ID,
			$value
		);
	}

	/**
	 * Filters rows with `in` condition on column `oz_clients`.`id`.
	 *
	 * @param array $value the filter value
	 *
	 * @return static
	 */
	public function whereIdIsIn(array $value): self
	{
		return $this->filterBy(
			Operator::from('in'),
			\OZONE\OZ\Db\OZClient::COL_ID,
			$value
		);
	}

	/**
	 * Filters rows with `not_in` condition on column `oz_clients`.`id`.
	 *
	 * @param array $value the filter value
	 *
	 * @return static
	 */
	public function whereIdIsNotIn(array $value): self
	{
		return $this->filterBy(
			Operator::from('not_in'),
			\OZONE\OZ\Db\OZClient::COL_ID,
			$value
		);
	}

	/**
	 * Filters rows with `eq` condition on column `oz_clients`.`api_key`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 */
	public function whereApiKeyIs(string $value): self
	{
		return $this->filterBy(
			Operator::from('eq'),
			\OZONE\OZ\Db\OZClient::COL_API_KEY,
			$value
		);
	}

	/**
	 * Filters rows with `neq` condition on column `oz_clients`.`api_key`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 */
	public function whereApiKeyIsNot(string $value): self
	{
		return $this->filterBy(
			Operator::from('neq'),
			\OZONE\OZ\Db\OZClient::COL_API_KEY,
			$value
		);
	}

	/**
	 * Filters rows with `lt` condition on column `oz_clients`.`api_key`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 */
	public function whereApiKeyIsLt(string $value): self
	{
		return $this->filterBy(
			Operator::from('lt'),
			\OZONE\OZ\Db\OZClient::COL_API_KEY,
			$value
		);
	}

	/**
	 * Filters rows with `lte` condition on column `oz_clients`.`api_key`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 */
	public function whereApiKeyIsLte(string $value): self
	{
		return $this->filterBy(
			Operator::from('lte'),
			\OZONE\OZ\Db\OZClient::COL_API_KEY,
			$value
		);
	}

	/**
	 * Filters rows with `gt` condition on column `oz_clients`.`api_key`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 */
	public function whereApiKeyIsGt(string $value): self
	{
		return $this->filterBy(
			Operator::from('gt'),
			\OZONE\OZ\Db\OZClient::COL_API_KEY,
			$value
		);
	}

	/**
	 * Filters rows with `gte` condition on column `oz_clients`.`api_key`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 */
	public function whereApiKeyIsGte(string $value): self
	{
		return $this->filterBy(
			Operator::from('gte'),
			\OZONE\OZ\Db\OZClient::COL_API_KEY,
			$value
		);
	}

	/**
	 * Filters rows with `like` condition on column `oz_clients`.`api_key`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 */
	public function whereApiKeyIsLike(string $value): self
	{
		return $this->filterBy(
			Operator::from('like'),
			\OZONE\OZ\Db\OZClient::COL_API_KEY,
			$value
		);
	}

	/**
	 * Filters rows with `not_like` condition on column `oz_clients`.`api_key`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 */
	public function whereApiKeyIsNotLike(string $value): self
	{
		return $this->filterBy(
			Operator::from('not_like'),
			\OZONE\OZ\Db\OZClient::COL_API_KEY,
			$value
		);
	}

	/**
	 * Filters rows with `in` condition on column `oz_clients`.`api_key`.
	 *
	 * @param array $value the filter value
	 *
	 * @return static
	 */
	public function whereApiKeyIsIn(array $value): self
	{
		return $this->filterBy(
			Operator::from('in'),
			\OZONE\OZ\Db\OZClient::COL_API_KEY,
			$value
		);
	}

	/**
	 * Filters rows with `not_in` condition on column `oz_clients`.`api_key`.
	 *
	 * @param array $value the filter value
	 *
	 * @return static
	 */
	public function whereApiKeyIsNotIn(array $value): self
	{
		return $this->filterBy(
			Operator::from('not_in'),
			\OZONE\OZ\Db\OZClient::COL_API_KEY,
			$value
		);
	}

	/**
	 * Filters rows with `eq` condition on column `oz_clients`.`added_by`.
	 *
	 * @param int|string $value the filter value
	 *
	 * @return static
	 */
	public function whereAddedByIs(string|int $value): self
	{
		return $this->filterBy(
			Operator::from('eq'),
			\OZONE\OZ\Db\OZClient::COL_ADDED_BY,
			$value
		);
	}

	/**
	 * Filters rows with `neq` condition on column `oz_clients`.`added_by`.
	 *
	 * @param int|string $value the filter value
	 *
	 * @return static
	 */
	public function whereAddedByIsNot(string|int $value): self
	{
		return $this->filterBy(
			Operator::from('neq'),
			\OZONE\OZ\Db\OZClient::COL_ADDED_BY,
			$value
		);
	}

	/**
	 * Filters rows with `lt` condition on column `oz_clients`.`added_by`.
	 *
	 * @param int|string $value the filter value
	 *
	 * @return static
	 */
	public function whereAddedByIsLt(string|int $value): self
	{
		return $this->filterBy(
			Operator::from('lt'),
			\OZONE\OZ\Db\OZClient::COL_ADDED_BY,
			$value
		);
	}

	/**
	 * Filters rows with `lte` condition on column `oz_clients`.`added_by`.
	 *
	 * @param int|string $value the filter value
	 *
	 * @return static
	 */
	public function whereAddedByIsLte(string|int $value): self
	{
		return $this->filterBy(
			Operator::from('lte'),
			\OZONE\OZ\Db\OZClient::COL_ADDED_BY,
			$value
		);
	}

	/**
	 * Filters rows with `gt` condition on column `oz_clients`.`added_by`.
	 *
	 * @param int|string $value the filter value
	 *
	 * @return static
	 */
	public function whereAddedByIsGt(string|int $value): self
	{
		return $this->filterBy(
			Operator::from('gt'),
			\OZONE\OZ\Db\OZClient::COL_ADDED_BY,
			$value
		);
	}

	/**
	 * Filters rows with `gte` condition on column `oz_clients`.`added_by`.
	 *
	 * @param int|string $value the filter value
	 *
	 * @return static
	 */
	public function whereAddedByIsGte(string|int $value): self
	{
		return $this->filterBy(
			Operator::from('gte'),
			\OZONE\OZ\Db\OZClient::COL_ADDED_BY,
			$value
		);
	}

	/**
	 * Filters rows with `like` condition on column `oz_clients`.`added_by`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 */
	public function whereAddedByIsLike(string $value): self
	{
		return $this->filterBy(
			Operator::from('like'),
			\OZONE\OZ\Db\OZClient::COL_ADDED_BY,
			$value
		);
	}

	/**
	 * Filters rows with `not_like` condition on column `oz_clients`.`added_by`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 */
	public function whereAddedByIsNotLike(string $value): self
	{
		return $this->filterBy(
			Operator::from('not_like'),
			\OZONE\OZ\Db\OZClient::COL_ADDED_BY,
			$value
		);
	}

	/**
	 * Filters rows with `in` condition on column `oz_clients`.`added_by`.
	 *
	 * @param array $value the filter value
	 *
	 * @return static
	 */
	public function whereAddedByIsIn(array $value): self
	{
		return $this->filterBy(
			Operator::from('in'),
			\OZONE\OZ\Db\OZClient::COL_ADDED_BY,
			$value
		);
	}

	/**
	 * Filters rows with `not_in` condition on column `oz_clients`.`added_by`.
	 *
	 * @param array $value the filter value
	 *
	 * @return static
	 */
	public function whereAddedByIsNotIn(array $value): self
	{
		return $this->filterBy(
			Operator::from('not_in'),
			\OZONE\OZ\Db\OZClient::COL_ADDED_BY,
			$value
		);
	}

	/**
	 * Filters rows with `eq` condition on column `oz_clients`.`user_id`.
	 *
	 * @param int|string $value the filter value
	 *
	 * @return static
	 */
	public function whereUserIdIs(string|int $value): self
	{
		return $this->filterBy(
			Operator::from('eq'),
			\OZONE\OZ\Db\OZClient::COL_USER_ID,
			$value
		);
	}

	/**
	 * Filters rows with `neq` condition on column `oz_clients`.`user_id`.
	 *
	 * @param int|string $value the filter value
	 *
	 * @return static
	 */
	public function whereUserIdIsNot(string|int $value): self
	{
		return $this->filterBy(
			Operator::from('neq'),
			\OZONE\OZ\Db\OZClient::COL_USER_ID,
			$value
		);
	}

	/**
	 * Filters rows with `lt` condition on column `oz_clients`.`user_id`.
	 *
	 * @param int|string $value the filter value
	 *
	 * @return static
	 */
	public function whereUserIdIsLt(string|int $value): self
	{
		return $this->filterBy(
			Operator::from('lt'),
			\OZONE\OZ\Db\OZClient::COL_USER_ID,
			$value
		);
	}

	/**
	 * Filters rows with `lte` condition on column `oz_clients`.`user_id`.
	 *
	 * @param int|string $value the filter value
	 *
	 * @return static
	 */
	public function whereUserIdIsLte(string|int $value): self
	{
		return $this->filterBy(
			Operator::from('lte'),
			\OZONE\OZ\Db\OZClient::COL_USER_ID,
			$value
		);
	}

	/**
	 * Filters rows with `gt` condition on column `oz_clients`.`user_id`.
	 *
	 * @param int|string $value the filter value
	 *
	 * @return static
	 */
	public function whereUserIdIsGt(string|int $value): self
	{
		return $this->filterBy(
			Operator::from('gt'),
			\OZONE\OZ\Db\OZClient::COL_USER_ID,
			$value
		);
	}

	/**
	 * Filters rows with `gte` condition on column `oz_clients`.`user_id`.
	 *
	 * @param int|string $value the filter value
	 *
	 * @return static
	 */
	public function whereUserIdIsGte(string|int $value): self
	{
		return $this->filterBy(
			Operator::from('gte'),
			\OZONE\OZ\Db\OZClient::COL_USER_ID,
			$value
		);
	}

	/**
	 * Filters rows with `like` condition on column `oz_clients`.`user_id`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 */
	public function whereUserIdIsLike(string $value): self
	{
		return $this->filterBy(
			Operator::from('like'),
			\OZONE\OZ\Db\OZClient::COL_USER_ID,
			$value
		);
	}

	/**
	 * Filters rows with `not_like` condition on column `oz_clients`.`user_id`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 */
	public function whereUserIdIsNotLike(string $value): self
	{
		return $this->filterBy(
			Operator::from('not_like'),
			\OZONE\OZ\Db\OZClient::COL_USER_ID,
			$value
		);
	}

	/**
	 * Filters rows with `is_null` condition on column `oz_clients`.`user_id`.
	 *
	 * @return static
	 */
	public function whereUserIdIsNull(): self
	{
		return $this->filterBy(
			Operator::from('is_null'),
			\OZONE\OZ\Db\OZClient::COL_USER_ID
		);
	}

	/**
	 * Filters rows with `is_not_null` condition on column `oz_clients`.`user_id`.
	 *
	 * @return static
	 */
	public function whereUserIdIsNotNull(): self
	{
		return $this->filterBy(
			Operator::from('is_not_null'),
			\OZONE\OZ\Db\OZClient::COL_USER_ID
		);
	}

	/**
	 * Filters rows with `in` condition on column `oz_clients`.`user_id`.
	 *
	 * @param array $value the filter value
	 *
	 * @return static
	 */
	public function whereUserIdIsIn(array $value): self
	{
		return $this->filterBy(
			Operator::from('in'),
			\OZONE\OZ\Db\OZClient::COL_USER_ID,
			$value
		);
	}

	/**
	 * Filters rows with `not_in` condition on column `oz_clients`.`user_id`.
	 *
	 * @param array $value the filter value
	 *
	 * @return static
	 */
	public function whereUserIdIsNotIn(array $value): self
	{
		return $this->filterBy(
			Operator::from('not_in'),
			\OZONE\OZ\Db\OZClient::COL_USER_ID,
			$value
		);
	}

	/**
	 * Filters rows with `eq` condition on column `oz_clients`.`url`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 */
	public function whereUrlIs(string $value): self
	{
		return $this->filterBy(
			Operator::from('eq'),
			\OZONE\OZ\Db\OZClient::COL_URL,
			$value
		);
	}

	/**
	 * Filters rows with `neq` condition on column `oz_clients`.`url`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 */
	public function whereUrlIsNot(string $value): self
	{
		return $this->filterBy(
			Operator::from('neq'),
			\OZONE\OZ\Db\OZClient::COL_URL,
			$value
		);
	}

	/**
	 * Filters rows with `lt` condition on column `oz_clients`.`url`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 */
	public function whereUrlIsLt(string $value): self
	{
		return $this->filterBy(
			Operator::from('lt'),
			\OZONE\OZ\Db\OZClient::COL_URL,
			$value
		);
	}

	/**
	 * Filters rows with `lte` condition on column `oz_clients`.`url`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 */
	public function whereUrlIsLte(string $value): self
	{
		return $this->filterBy(
			Operator::from('lte'),
			\OZONE\OZ\Db\OZClient::COL_URL,
			$value
		);
	}

	/**
	 * Filters rows with `gt` condition on column `oz_clients`.`url`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 */
	public function whereUrlIsGt(string $value): self
	{
		return $this->filterBy(
			Operator::from('gt'),
			\OZONE\OZ\Db\OZClient::COL_URL,
			$value
		);
	}

	/**
	 * Filters rows with `gte` condition on column `oz_clients`.`url`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 */
	public function whereUrlIsGte(string $value): self
	{
		return $this->filterBy(
			Operator::from('gte'),
			\OZONE\OZ\Db\OZClient::COL_URL,
			$value
		);
	}

	/**
	 * Filters rows with `like` condition on column `oz_clients`.`url`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 */
	public function whereUrlIsLike(string $value): self
	{
		return $this->filterBy(
			Operator::from('like'),
			\OZONE\OZ\Db\OZClient::COL_URL,
			$value
		);
	}

	/**
	 * Filters rows with `not_like` condition on column `oz_clients`.`url`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 */
	public function whereUrlIsNotLike(string $value): self
	{
		return $this->filterBy(
			Operator::from('not_like'),
			\OZONE\OZ\Db\OZClient::COL_URL,
			$value
		);
	}

	/**
	 * Filters rows with `in` condition on column `oz_clients`.`url`.
	 *
	 * @param array $value the filter value
	 *
	 * @return static
	 */
	public function whereUrlIsIn(array $value): self
	{
		return $this->filterBy(
			Operator::from('in'),
			\OZONE\OZ\Db\OZClient::COL_URL,
			$value
		);
	}

	/**
	 * Filters rows with `not_in` condition on column `oz_clients`.`url`.
	 *
	 * @param array $value the filter value
	 *
	 * @return static
	 */
	public function whereUrlIsNotIn(array $value): self
	{
		return $this->filterBy(
			Operator::from('not_in'),
			\OZONE\OZ\Db\OZClient::COL_URL,
			$value
		);
	}

	/**
	 * Filters rows with `eq` condition on column `oz_clients`.`session_life_time`.
	 *
	 * @param int|string $value the filter value
	 *
	 * @return static
	 */
	public function whereSessionLifeTimeIs(string|int $value): self
	{
		return $this->filterBy(
			Operator::from('eq'),
			\OZONE\OZ\Db\OZClient::COL_SESSION_LIFE_TIME,
			$value
		);
	}

	/**
	 * Filters rows with `neq` condition on column `oz_clients`.`session_life_time`.
	 *
	 * @param int|string $value the filter value
	 *
	 * @return static
	 */
	public function whereSessionLifeTimeIsNot(string|int $value): self
	{
		return $this->filterBy(
			Operator::from('neq'),
			\OZONE\OZ\Db\OZClient::COL_SESSION_LIFE_TIME,
			$value
		);
	}

	/**
	 * Filters rows with `lt` condition on column `oz_clients`.`session_life_time`.
	 *
	 * @param int|string $value the filter value
	 *
	 * @return static
	 */
	public function whereSessionLifeTimeIsLt(string|int $value): self
	{
		return $this->filterBy(
			Operator::from('lt'),
			\OZONE\OZ\Db\OZClient::COL_SESSION_LIFE_TIME,
			$value
		);
	}

	/**
	 * Filters rows with `lte` condition on column `oz_clients`.`session_life_time`.
	 *
	 * @param int|string $value the filter value
	 *
	 * @return static
	 */
	public function whereSessionLifeTimeIsLte(string|int $value): self
	{
		return $this->filterBy(
			Operator::from('lte'),
			\OZONE\OZ\Db\OZClient::COL_SESSION_LIFE_TIME,
			$value
		);
	}

	/**
	 * Filters rows with `gt` condition on column `oz_clients`.`session_life_time`.
	 *
	 * @param int|string $value the filter value
	 *
	 * @return static
	 */
	public function whereSessionLifeTimeIsGt(string|int $value): self
	{
		return $this->filterBy(
			Operator::from('gt'),
			\OZONE\OZ\Db\OZClient::COL_SESSION_LIFE_TIME,
			$value
		);
	}

	/**
	 * Filters rows with `gte` condition on column `oz_clients`.`session_life_time`.
	 *
	 * @param int|string $value the filter value
	 *
	 * @return static
	 */
	public function whereSessionLifeTimeIsGte(string|int $value): self
	{
		return $this->filterBy(
			Operator::from('gte'),
			\OZONE\OZ\Db\OZClient::COL_SESSION_LIFE_TIME,
			$value
		);
	}

	/**
	 * Filters rows with `like` condition on column `oz_clients`.`session_life_time`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 */
	public function whereSessionLifeTimeIsLike(string $value): self
	{
		return $this->filterBy(
			Operator::from('like'),
			\OZONE\OZ\Db\OZClient::COL_SESSION_LIFE_TIME,
			$value
		);
	}

	/**
	 * Filters rows with `not_like` condition on column `oz_clients`.`session_life_time`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 */
	public function whereSessionLifeTimeIsNotLike(string $value): self
	{
		return $this->filterBy(
			Operator::from('not_like'),
			\OZONE\OZ\Db\OZClient::COL_SESSION_LIFE_TIME,
			$value
		);
	}

	/**
	 * Filters rows with `in` condition on column `oz_clients`.`session_life_time`.
	 *
	 * @param array $value the filter value
	 *
	 * @return static
	 */
	public function whereSessionLifeTimeIsIn(array $value): self
	{
		return $this->filterBy(
			Operator::from('in'),
			\OZONE\OZ\Db\OZClient::COL_SESSION_LIFE_TIME,
			$value
		);
	}

	/**
	 * Filters rows with `not_in` condition on column `oz_clients`.`session_life_time`.
	 *
	 * @param array $value the filter value
	 *
	 * @return static
	 */
	public function whereSessionLifeTimeIsNotIn(array $value): self
	{
		return $this->filterBy(
			Operator::from('not_in'),
			\OZONE\OZ\Db\OZClient::COL_SESSION_LIFE_TIME,
			$value
		);
	}

	/**
	 * Filters rows with `eq` condition on column `oz_clients`.`about`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 */
	public function whereAboutIs(string $value): self
	{
		return $this->filterBy(
			Operator::from('eq'),
			\OZONE\OZ\Db\OZClient::COL_ABOUT,
			$value
		);
	}

	/**
	 * Filters rows with `neq` condition on column `oz_clients`.`about`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 */
	public function whereAboutIsNot(string $value): self
	{
		return $this->filterBy(
			Operator::from('neq'),
			\OZONE\OZ\Db\OZClient::COL_ABOUT,
			$value
		);
	}

	/**
	 * Filters rows with `lt` condition on column `oz_clients`.`about`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 */
	public function whereAboutIsLt(string $value): self
	{
		return $this->filterBy(
			Operator::from('lt'),
			\OZONE\OZ\Db\OZClient::COL_ABOUT,
			$value
		);
	}

	/**
	 * Filters rows with `lte` condition on column `oz_clients`.`about`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 */
	public function whereAboutIsLte(string $value): self
	{
		return $this->filterBy(
			Operator::from('lte'),
			\OZONE\OZ\Db\OZClient::COL_ABOUT,
			$value
		);
	}

	/**
	 * Filters rows with `gt` condition on column `oz_clients`.`about`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 */
	public function whereAboutIsGt(string $value): self
	{
		return $this->filterBy(
			Operator::from('gt'),
			\OZONE\OZ\Db\OZClient::COL_ABOUT,
			$value
		);
	}

	/**
	 * Filters rows with `gte` condition on column `oz_clients`.`about`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 */
	public function whereAboutIsGte(string $value): self
	{
		return $this->filterBy(
			Operator::from('gte'),
			\OZONE\OZ\Db\OZClient::COL_ABOUT,
			$value
		);
	}

	/**
	 * Filters rows with `like` condition on column `oz_clients`.`about`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 */
	public function whereAboutIsLike(string $value): self
	{
		return $this->filterBy(
			Operator::from('like'),
			\OZONE\OZ\Db\OZClient::COL_ABOUT,
			$value
		);
	}

	/**
	 * Filters rows with `not_like` condition on column `oz_clients`.`about`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 */
	public function whereAboutIsNotLike(string $value): self
	{
		return $this->filterBy(
			Operator::from('not_like'),
			\OZONE\OZ\Db\OZClient::COL_ABOUT,
			$value
		);
	}

	/**
	 * Filters rows with `in` condition on column `oz_clients`.`about`.
	 *
	 * @param array $value the filter value
	 *
	 * @return static
	 */
	public function whereAboutIsIn(array $value): self
	{
		return $this->filterBy(
			Operator::from('in'),
			\OZONE\OZ\Db\OZClient::COL_ABOUT,
			$value
		);
	}

	/**
	 * Filters rows with `not_in` condition on column `oz_clients`.`about`.
	 *
	 * @param array $value the filter value
	 *
	 * @return static
	 */
	public function whereAboutIsNotIn(array $value): self
	{
		return $this->filterBy(
			Operator::from('not_in'),
			\OZONE\OZ\Db\OZClient::COL_ABOUT,
			$value
		);
	}

	/**
	 * Filters rows with `eq` condition on column `oz_clients`.`data`.
	 *
	 * @param array $value the filter value
	 *
	 * @return static
	 */
	public function whereDataIs(array $value): self
	{
		return $this->filterBy(
			Operator::from('eq'),
			\OZONE\OZ\Db\OZClient::COL_DATA,
			$value
		);
	}

	/**
	 * Filters rows with `neq` condition on column `oz_clients`.`data`.
	 *
	 * @param array $value the filter value
	 *
	 * @return static
	 */
	public function whereDataIsNot(array $value): self
	{
		return $this->filterBy(
			Operator::from('neq'),
			\OZONE\OZ\Db\OZClient::COL_DATA,
			$value
		);
	}

	/**
	 * Filters rows with `like` condition on column `oz_clients`.`data`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 */
	public function whereDataIsLike(string $value): self
	{
		return $this->filterBy(
			Operator::from('like'),
			\OZONE\OZ\Db\OZClient::COL_DATA,
			$value
		);
	}

	/**
	 * Filters rows with `not_like` condition on column `oz_clients`.`data`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 */
	public function whereDataIsNotLike(string $value): self
	{
		return $this->filterBy(
			Operator::from('not_like'),
			\OZONE\OZ\Db\OZClient::COL_DATA,
			$value
		);
	}

	/**
	 * Filters rows with `eq` condition on column `oz_clients`.`created_at`.
	 *
	 * @param int|string $value the filter value
	 *
	 * @return static
	 */
	public function whereCreatedAtIs(string|int $value): self
	{
		return $this->filterBy(
			Operator::from('eq'),
			\OZONE\OZ\Db\OZClient::COL_CREATED_AT,
			$value
		);
	}

	/**
	 * Filters rows with `neq` condition on column `oz_clients`.`created_at`.
	 *
	 * @param int|string $value the filter value
	 *
	 * @return static
	 */
	public function whereCreatedAtIsNot(string|int $value): self
	{
		return $this->filterBy(
			Operator::from('neq'),
			\OZONE\OZ\Db\OZClient::COL_CREATED_AT,
			$value
		);
	}

	/**
	 * Filters rows with `lt` condition on column `oz_clients`.`created_at`.
	 *
	 * @param int|string $value the filter value
	 *
	 * @return static
	 */
	public function whereCreatedAtIsLt(string|int $value): self
	{
		return $this->filterBy(
			Operator::from('lt'),
			\OZONE\OZ\Db\OZClient::COL_CREATED_AT,
			$value
		);
	}

	/**
	 * Filters rows with `lte` condition on column `oz_clients`.`created_at`.
	 *
	 * @param int|string $value the filter value
	 *
	 * @return static
	 */
	public function whereCreatedAtIsLte(string|int $value): self
	{
		return $this->filterBy(
			Operator::from('lte'),
			\OZONE\OZ\Db\OZClient::COL_CREATED_AT,
			$value
		);
	}

	/**
	 * Filters rows with `gt` condition on column `oz_clients`.`created_at`.
	 *
	 * @param int|string $value the filter value
	 *
	 * @return static
	 */
	public function whereCreatedAtIsGt(string|int $value): self
	{
		return $this->filterBy(
			Operator::from('gt'),
			\OZONE\OZ\Db\OZClient::COL_CREATED_AT,
			$value
		);
	}

	/**
	 * Filters rows with `gte` condition on column `oz_clients`.`created_at`.
	 *
	 * @param int|string $value the filter value
	 *
	 * @return static
	 */
	public function whereCreatedAtIsGte(string|int $value): self
	{
		return $this->filterBy(
			Operator::from('gte'),
			\OZONE\OZ\Db\OZClient::COL_CREATED_AT,
			$value
		);
	}

	/**
	 * Filters rows with `like` condition on column `oz_clients`.`created_at`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 */
	public function whereCreatedAtIsLike(string $value): self
	{
		return $this->filterBy(
			Operator::from('like'),
			\OZONE\OZ\Db\OZClient::COL_CREATED_AT,
			$value
		);
	}

	/**
	 * Filters rows with `not_like` condition on column `oz_clients`.`created_at`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 */
	public function whereCreatedAtIsNotLike(string $value): self
	{
		return $this->filterBy(
			Operator::from('not_like'),
			\OZONE\OZ\Db\OZClient::COL_CREATED_AT,
			$value
		);
	}

	/**
	 * Filters rows with `in` condition on column `oz_clients`.`created_at`.
	 *
	 * @param array $value the filter value
	 *
	 * @return static
	 */
	public function whereCreatedAtIsIn(array $value): self
	{
		return $this->filterBy(
			Operator::from('in'),
			\OZONE\OZ\Db\OZClient::COL_CREATED_AT,
			$value
		);
	}

	/**
	 * Filters rows with `not_in` condition on column `oz_clients`.`created_at`.
	 *
	 * @param array $value the filter value
	 *
	 * @return static
	 */
	public function whereCreatedAtIsNotIn(array $value): self
	{
		return $this->filterBy(
			Operator::from('not_in'),
			\OZONE\OZ\Db\OZClient::COL_CREATED_AT,
			$value
		);
	}

	/**
	 * Filters rows with `eq` condition on column `oz_clients`.`updated_at`.
	 *
	 * @param int|string $value the filter value
	 *
	 * @return static
	 */
	public function whereUpdatedAtIs(string|int $value): self
	{
		return $this->filterBy(
			Operator::from('eq'),
			\OZONE\OZ\Db\OZClient::COL_UPDATED_AT,
			$value
		);
	}

	/**
	 * Filters rows with `neq` condition on column `oz_clients`.`updated_at`.
	 *
	 * @param int|string $value the filter value
	 *
	 * @return static
	 */
	public function whereUpdatedAtIsNot(string|int $value): self
	{
		return $this->filterBy(
			Operator::from('neq'),
			\OZONE\OZ\Db\OZClient::COL_UPDATED_AT,
			$value
		);
	}

	/**
	 * Filters rows with `lt` condition on column `oz_clients`.`updated_at`.
	 *
	 * @param int|string $value the filter value
	 *
	 * @return static
	 */
	public function whereUpdatedAtIsLt(string|int $value): self
	{
		return $this->filterBy(
			Operator::from('lt'),
			\OZONE\OZ\Db\OZClient::COL_UPDATED_AT,
			$value
		);
	}

	/**
	 * Filters rows with `lte` condition on column `oz_clients`.`updated_at`.
	 *
	 * @param int|string $value the filter value
	 *
	 * @return static
	 */
	public function whereUpdatedAtIsLte(string|int $value): self
	{
		return $this->filterBy(
			Operator::from('lte'),
			\OZONE\OZ\Db\OZClient::COL_UPDATED_AT,
			$value
		);
	}

	/**
	 * Filters rows with `gt` condition on column `oz_clients`.`updated_at`.
	 *
	 * @param int|string $value the filter value
	 *
	 * @return static
	 */
	public function whereUpdatedAtIsGt(string|int $value): self
	{
		return $this->filterBy(
			Operator::from('gt'),
			\OZONE\OZ\Db\OZClient::COL_UPDATED_AT,
			$value
		);
	}

	/**
	 * Filters rows with `gte` condition on column `oz_clients`.`updated_at`.
	 *
	 * @param int|string $value the filter value
	 *
	 * @return static
	 */
	public function whereUpdatedAtIsGte(string|int $value): self
	{
		return $this->filterBy(
			Operator::from('gte'),
			\OZONE\OZ\Db\OZClient::COL_UPDATED_AT,
			$value
		);
	}

	/**
	 * Filters rows with `like` condition on column `oz_clients`.`updated_at`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 */
	public function whereUpdatedAtIsLike(string $value): self
	{
		return $this->filterBy(
			Operator::from('like'),
			\OZONE\OZ\Db\OZClient::COL_UPDATED_AT,
			$value
		);
	}

	/**
	 * Filters rows with `not_like` condition on column `oz_clients`.`updated_at`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 */
	public function whereUpdatedAtIsNotLike(string $value): self
	{
		return $this->filterBy(
			Operator::from('not_like'),
			\OZONE\OZ\Db\OZClient::COL_UPDATED_AT,
			$value
		);
	}

	/**
	 * Filters rows with `in` condition on column `oz_clients`.`updated_at`.
	 *
	 * @param array $value the filter value
	 *
	 * @return static
	 */
	public function whereUpdatedAtIsIn(array $value): self
	{
		return $this->filterBy(
			Operator::from('in'),
			\OZONE\OZ\Db\OZClient::COL_UPDATED_AT,
			$value
		);
	}

	/**
	 * Filters rows with `not_in` condition on column `oz_clients`.`updated_at`.
	 *
	 * @param array $value the filter value
	 *
	 * @return static
	 */
	public function whereUpdatedAtIsNotIn(array $value): self
	{
		return $this->filterBy(
			Operator::from('not_in'),
			\OZONE\OZ\Db\OZClient::COL_UPDATED_AT,
			$value
		);
	}

	/**
	 * Filters rows with `eq` condition on column `oz_clients`.`is_valid`.
	 *
	 * @param bool $value the filter value
	 *
	 * @return static
	 */
	public function whereIsValidIs(bool $value): self
	{
		return $this->filterBy(
			Operator::from('eq'),
			\OZONE\OZ\Db\OZClient::COL_IS_VALID,
			$value
		);
	}

	/**
	 * Filters rows with `neq` condition on column `oz_clients`.`is_valid`.
	 *
	 * @param bool $value the filter value
	 *
	 * @return static
	 */
	public function whereIsValidIsNot(bool $value): self
	{
		return $this->filterBy(
			Operator::from('neq'),
			\OZONE\OZ\Db\OZClient::COL_IS_VALID,
			$value
		);
	}

	/**
	 * Filters rows with `is_false` condition on column `oz_clients`.`is_valid`.
	 *
	 * @return static
	 */
	public function whereIsNotValid(): self
	{
		return $this->filterBy(
			Operator::from('is_false'),
			\OZONE\OZ\Db\OZClient::COL_IS_VALID
		);
	}

	/**
	 * Filters rows with `is_true` condition on column `oz_clients`.`is_valid`.
	 *
	 * @return static
	 */
	public function whereIsValid(): self
	{
		return $this->filterBy(
			Operator::from('is_true'),
			\OZONE\OZ\Db\OZClient::COL_IS_VALID
		);
	}
}
