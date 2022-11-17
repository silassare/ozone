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
 * Class OZUsersQuery.
 *
 * @method \OZONE\OZ\Db\OZUsersResults find(?int $max = null, int $offset = 0, array $order_by = [])
 */
abstract class OZUsersQuery extends \Gobl\ORM\ORMTableQuery
{
	/**
	 * OZUsersQuery constructor.
	 */
	public function __construct()
	{
		parent::__construct(
			\OZONE\OZ\Db\OZUser::TABLE_NAMESPACE,
			\OZONE\OZ\Db\OZUser::TABLE_NAME
		);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @return static
	 */
	public static function createInstance(): static
	{
		return new \OZONE\OZ\Db\OZUsersQuery();
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
	 * Filters rows with `eq` condition on column `oz_users`.`id`.
	 *
	 * @param int|string $value the filter value
	 *
	 * @return static
	 *
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereIdIs(string|int $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('eq'),
			\OZONE\OZ\Db\OZUser::COL_ID,
			$value
		);
	}

	/**
	 * Filters rows with `neq` condition on column `oz_users`.`id`.
	 *
	 * @param int|string $value the filter value
	 *
	 * @return static
	 *
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereIdIsNot(string|int $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('neq'),
			\OZONE\OZ\Db\OZUser::COL_ID,
			$value
		);
	}

	/**
	 * Filters rows with `lt` condition on column `oz_users`.`id`.
	 *
	 * @param int|string $value the filter value
	 *
	 * @return static
	 *
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereIdIsLt(string|int $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('lt'),
			\OZONE\OZ\Db\OZUser::COL_ID,
			$value
		);
	}

	/**
	 * Filters rows with `lte` condition on column `oz_users`.`id`.
	 *
	 * @param int|string $value the filter value
	 *
	 * @return static
	 *
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereIdIsLte(string|int $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('lte'),
			\OZONE\OZ\Db\OZUser::COL_ID,
			$value
		);
	}

	/**
	 * Filters rows with `gt` condition on column `oz_users`.`id`.
	 *
	 * @param int|string $value the filter value
	 *
	 * @return static
	 *
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereIdIsGt(string|int $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('gt'),
			\OZONE\OZ\Db\OZUser::COL_ID,
			$value
		);
	}

	/**
	 * Filters rows with `gte` condition on column `oz_users`.`id`.
	 *
	 * @param int|string $value the filter value
	 *
	 * @return static
	 *
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereIdIsGte(string|int $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('gte'),
			\OZONE\OZ\Db\OZUser::COL_ID,
			$value
		);
	}

	/**
	 * Filters rows with `like` condition on column `oz_users`.`id`.
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
			\OZONE\OZ\Db\OZUser::COL_ID,
			$value
		);
	}

	/**
	 * Filters rows with `not_like` condition on column `oz_users`.`id`.
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
			\OZONE\OZ\Db\OZUser::COL_ID,
			$value
		);
	}

	/**
	 * Filters rows with `in` condition on column `oz_users`.`id`.
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
			\OZONE\OZ\Db\OZUser::COL_ID,
			$value
		);
	}

	/**
	 * Filters rows with `not_in` condition on column `oz_users`.`id`.
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
			\OZONE\OZ\Db\OZUser::COL_ID,
			$value
		);
	}

	/**
	 * Filters rows with `eq` condition on column `oz_users`.`phone`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 *
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function wherePhoneIs(string $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('eq'),
			\OZONE\OZ\Db\OZUser::COL_PHONE,
			$value
		);
	}

	/**
	 * Filters rows with `neq` condition on column `oz_users`.`phone`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 *
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function wherePhoneIsNot(string $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('neq'),
			\OZONE\OZ\Db\OZUser::COL_PHONE,
			$value
		);
	}

	/**
	 * Filters rows with `lt` condition on column `oz_users`.`phone`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 *
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function wherePhoneIsLt(string $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('lt'),
			\OZONE\OZ\Db\OZUser::COL_PHONE,
			$value
		);
	}

	/**
	 * Filters rows with `lte` condition on column `oz_users`.`phone`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 *
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function wherePhoneIsLte(string $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('lte'),
			\OZONE\OZ\Db\OZUser::COL_PHONE,
			$value
		);
	}

	/**
	 * Filters rows with `gt` condition on column `oz_users`.`phone`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 *
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function wherePhoneIsGt(string $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('gt'),
			\OZONE\OZ\Db\OZUser::COL_PHONE,
			$value
		);
	}

	/**
	 * Filters rows with `gte` condition on column `oz_users`.`phone`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 *
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function wherePhoneIsGte(string $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('gte'),
			\OZONE\OZ\Db\OZUser::COL_PHONE,
			$value
		);
	}

	/**
	 * Filters rows with `like` condition on column `oz_users`.`phone`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 *
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function wherePhoneIsLike(string $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('like'),
			\OZONE\OZ\Db\OZUser::COL_PHONE,
			$value
		);
	}

	/**
	 * Filters rows with `not_like` condition on column `oz_users`.`phone`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 *
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function wherePhoneIsNotLike(string $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('not_like'),
			\OZONE\OZ\Db\OZUser::COL_PHONE,
			$value
		);
	}

	/**
	 * Filters rows with `is_null` condition on column `oz_users`.`phone`.
	 *
	 * @return static
	 *
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function wherePhoneIsNull(): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('is_null'),
			\OZONE\OZ\Db\OZUser::COL_PHONE
		);
	}

	/**
	 * Filters rows with `is_not_null` condition on column `oz_users`.`phone`.
	 *
	 * @return static
	 *
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function wherePhoneIsNotNull(): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('is_not_null'),
			\OZONE\OZ\Db\OZUser::COL_PHONE
		);
	}

	/**
	 * Filters rows with `in` condition on column `oz_users`.`phone`.
	 *
	 * @param array $value the filter value
	 *
	 * @return static
	 *
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function wherePhoneIsIn(array $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('in'),
			\OZONE\OZ\Db\OZUser::COL_PHONE,
			$value
		);
	}

	/**
	 * Filters rows with `not_in` condition on column `oz_users`.`phone`.
	 *
	 * @param array $value the filter value
	 *
	 * @return static
	 *
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function wherePhoneIsNotIn(array $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('not_in'),
			\OZONE\OZ\Db\OZUser::COL_PHONE,
			$value
		);
	}

	/**
	 * Filters rows with `eq` condition on column `oz_users`.`email`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 *
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereEmailIs(string $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('eq'),
			\OZONE\OZ\Db\OZUser::COL_EMAIL,
			$value
		);
	}

	/**
	 * Filters rows with `neq` condition on column `oz_users`.`email`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 *
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereEmailIsNot(string $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('neq'),
			\OZONE\OZ\Db\OZUser::COL_EMAIL,
			$value
		);
	}

	/**
	 * Filters rows with `lt` condition on column `oz_users`.`email`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 *
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereEmailIsLt(string $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('lt'),
			\OZONE\OZ\Db\OZUser::COL_EMAIL,
			$value
		);
	}

	/**
	 * Filters rows with `lte` condition on column `oz_users`.`email`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 *
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereEmailIsLte(string $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('lte'),
			\OZONE\OZ\Db\OZUser::COL_EMAIL,
			$value
		);
	}

	/**
	 * Filters rows with `gt` condition on column `oz_users`.`email`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 *
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereEmailIsGt(string $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('gt'),
			\OZONE\OZ\Db\OZUser::COL_EMAIL,
			$value
		);
	}

	/**
	 * Filters rows with `gte` condition on column `oz_users`.`email`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 *
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereEmailIsGte(string $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('gte'),
			\OZONE\OZ\Db\OZUser::COL_EMAIL,
			$value
		);
	}

	/**
	 * Filters rows with `like` condition on column `oz_users`.`email`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 *
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereEmailIsLike(string $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('like'),
			\OZONE\OZ\Db\OZUser::COL_EMAIL,
			$value
		);
	}

	/**
	 * Filters rows with `not_like` condition on column `oz_users`.`email`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 *
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereEmailIsNotLike(string $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('not_like'),
			\OZONE\OZ\Db\OZUser::COL_EMAIL,
			$value
		);
	}

	/**
	 * Filters rows with `in` condition on column `oz_users`.`email`.
	 *
	 * @param array $value the filter value
	 *
	 * @return static
	 *
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereEmailIsIn(array $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('in'),
			\OZONE\OZ\Db\OZUser::COL_EMAIL,
			$value
		);
	}

	/**
	 * Filters rows with `not_in` condition on column `oz_users`.`email`.
	 *
	 * @param array $value the filter value
	 *
	 * @return static
	 *
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereEmailIsNotIn(array $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('not_in'),
			\OZONE\OZ\Db\OZUser::COL_EMAIL,
			$value
		);
	}

	/**
	 * Filters rows with `eq` condition on column `oz_users`.`pass`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 *
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function wherePassIs(string $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('eq'),
			\OZONE\OZ\Db\OZUser::COL_PASS,
			$value
		);
	}

	/**
	 * Filters rows with `neq` condition on column `oz_users`.`pass`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 *
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function wherePassIsNot(string $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('neq'),
			\OZONE\OZ\Db\OZUser::COL_PASS,
			$value
		);
	}

	/**
	 * Filters rows with `lt` condition on column `oz_users`.`pass`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 *
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function wherePassIsLt(string $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('lt'),
			\OZONE\OZ\Db\OZUser::COL_PASS,
			$value
		);
	}

	/**
	 * Filters rows with `lte` condition on column `oz_users`.`pass`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 *
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function wherePassIsLte(string $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('lte'),
			\OZONE\OZ\Db\OZUser::COL_PASS,
			$value
		);
	}

	/**
	 * Filters rows with `gt` condition on column `oz_users`.`pass`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 *
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function wherePassIsGt(string $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('gt'),
			\OZONE\OZ\Db\OZUser::COL_PASS,
			$value
		);
	}

	/**
	 * Filters rows with `gte` condition on column `oz_users`.`pass`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 *
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function wherePassIsGte(string $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('gte'),
			\OZONE\OZ\Db\OZUser::COL_PASS,
			$value
		);
	}

	/**
	 * Filters rows with `like` condition on column `oz_users`.`pass`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 *
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function wherePassIsLike(string $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('like'),
			\OZONE\OZ\Db\OZUser::COL_PASS,
			$value
		);
	}

	/**
	 * Filters rows with `not_like` condition on column `oz_users`.`pass`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 *
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function wherePassIsNotLike(string $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('not_like'),
			\OZONE\OZ\Db\OZUser::COL_PASS,
			$value
		);
	}

	/**
	 * Filters rows with `in` condition on column `oz_users`.`pass`.
	 *
	 * @param array $value the filter value
	 *
	 * @return static
	 *
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function wherePassIsIn(array $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('in'),
			\OZONE\OZ\Db\OZUser::COL_PASS,
			$value
		);
	}

	/**
	 * Filters rows with `not_in` condition on column `oz_users`.`pass`.
	 *
	 * @param array $value the filter value
	 *
	 * @return static
	 *
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function wherePassIsNotIn(array $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('not_in'),
			\OZONE\OZ\Db\OZUser::COL_PASS,
			$value
		);
	}

	/**
	 * Filters rows with `eq` condition on column `oz_users`.`name`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 *
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereNameIs(string $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('eq'),
			\OZONE\OZ\Db\OZUser::COL_NAME,
			$value
		);
	}

	/**
	 * Filters rows with `neq` condition on column `oz_users`.`name`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 *
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereNameIsNot(string $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('neq'),
			\OZONE\OZ\Db\OZUser::COL_NAME,
			$value
		);
	}

	/**
	 * Filters rows with `lt` condition on column `oz_users`.`name`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 *
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereNameIsLt(string $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('lt'),
			\OZONE\OZ\Db\OZUser::COL_NAME,
			$value
		);
	}

	/**
	 * Filters rows with `lte` condition on column `oz_users`.`name`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 *
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereNameIsLte(string $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('lte'),
			\OZONE\OZ\Db\OZUser::COL_NAME,
			$value
		);
	}

	/**
	 * Filters rows with `gt` condition on column `oz_users`.`name`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 *
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereNameIsGt(string $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('gt'),
			\OZONE\OZ\Db\OZUser::COL_NAME,
			$value
		);
	}

	/**
	 * Filters rows with `gte` condition on column `oz_users`.`name`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 *
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereNameIsGte(string $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('gte'),
			\OZONE\OZ\Db\OZUser::COL_NAME,
			$value
		);
	}

	/**
	 * Filters rows with `like` condition on column `oz_users`.`name`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 *
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereNameIsLike(string $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('like'),
			\OZONE\OZ\Db\OZUser::COL_NAME,
			$value
		);
	}

	/**
	 * Filters rows with `not_like` condition on column `oz_users`.`name`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 *
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereNameIsNotLike(string $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('not_like'),
			\OZONE\OZ\Db\OZUser::COL_NAME,
			$value
		);
	}

	/**
	 * Filters rows with `in` condition on column `oz_users`.`name`.
	 *
	 * @param array $value the filter value
	 *
	 * @return static
	 *
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereNameIsIn(array $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('in'),
			\OZONE\OZ\Db\OZUser::COL_NAME,
			$value
		);
	}

	/**
	 * Filters rows with `not_in` condition on column `oz_users`.`name`.
	 *
	 * @param array $value the filter value
	 *
	 * @return static
	 *
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereNameIsNotIn(array $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('not_in'),
			\OZONE\OZ\Db\OZUser::COL_NAME,
			$value
		);
	}

	/**
	 * Filters rows with `eq` condition on column `oz_users`.`gender`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 *
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereGenderIs(string $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('eq'),
			\OZONE\OZ\Db\OZUser::COL_GENDER,
			$value
		);
	}

	/**
	 * Filters rows with `neq` condition on column `oz_users`.`gender`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 *
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereGenderIsNot(string $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('neq'),
			\OZONE\OZ\Db\OZUser::COL_GENDER,
			$value
		);
	}

	/**
	 * Filters rows with `lt` condition on column `oz_users`.`gender`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 *
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereGenderIsLt(string $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('lt'),
			\OZONE\OZ\Db\OZUser::COL_GENDER,
			$value
		);
	}

	/**
	 * Filters rows with `lte` condition on column `oz_users`.`gender`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 *
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereGenderIsLte(string $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('lte'),
			\OZONE\OZ\Db\OZUser::COL_GENDER,
			$value
		);
	}

	/**
	 * Filters rows with `gt` condition on column `oz_users`.`gender`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 *
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereGenderIsGt(string $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('gt'),
			\OZONE\OZ\Db\OZUser::COL_GENDER,
			$value
		);
	}

	/**
	 * Filters rows with `gte` condition on column `oz_users`.`gender`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 *
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereGenderIsGte(string $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('gte'),
			\OZONE\OZ\Db\OZUser::COL_GENDER,
			$value
		);
	}

	/**
	 * Filters rows with `like` condition on column `oz_users`.`gender`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 *
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereGenderIsLike(string $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('like'),
			\OZONE\OZ\Db\OZUser::COL_GENDER,
			$value
		);
	}

	/**
	 * Filters rows with `not_like` condition on column `oz_users`.`gender`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 *
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereGenderIsNotLike(string $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('not_like'),
			\OZONE\OZ\Db\OZUser::COL_GENDER,
			$value
		);
	}

	/**
	 * Filters rows with `in` condition on column `oz_users`.`gender`.
	 *
	 * @param array $value the filter value
	 *
	 * @return static
	 *
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereGenderIsIn(array $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('in'),
			\OZONE\OZ\Db\OZUser::COL_GENDER,
			$value
		);
	}

	/**
	 * Filters rows with `not_in` condition on column `oz_users`.`gender`.
	 *
	 * @param array $value the filter value
	 *
	 * @return static
	 *
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereGenderIsNotIn(array $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('not_in'),
			\OZONE\OZ\Db\OZUser::COL_GENDER,
			$value
		);
	}

	/**
	 * Filters rows with `eq` condition on column `oz_users`.`birth_date`.
	 *
	 * @param int|string $value the filter value
	 *
	 * @return static
	 *
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereBirthDateIs(string|int $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('eq'),
			\OZONE\OZ\Db\OZUser::COL_BIRTH_DATE,
			$value
		);
	}

	/**
	 * Filters rows with `neq` condition on column `oz_users`.`birth_date`.
	 *
	 * @param int|string $value the filter value
	 *
	 * @return static
	 *
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereBirthDateIsNot(string|int $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('neq'),
			\OZONE\OZ\Db\OZUser::COL_BIRTH_DATE,
			$value
		);
	}

	/**
	 * Filters rows with `lt` condition on column `oz_users`.`birth_date`.
	 *
	 * @param int|string $value the filter value
	 *
	 * @return static
	 *
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereBirthDateIsLt(string|int $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('lt'),
			\OZONE\OZ\Db\OZUser::COL_BIRTH_DATE,
			$value
		);
	}

	/**
	 * Filters rows with `lte` condition on column `oz_users`.`birth_date`.
	 *
	 * @param int|string $value the filter value
	 *
	 * @return static
	 *
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereBirthDateIsLte(string|int $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('lte'),
			\OZONE\OZ\Db\OZUser::COL_BIRTH_DATE,
			$value
		);
	}

	/**
	 * Filters rows with `gt` condition on column `oz_users`.`birth_date`.
	 *
	 * @param int|string $value the filter value
	 *
	 * @return static
	 *
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereBirthDateIsGt(string|int $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('gt'),
			\OZONE\OZ\Db\OZUser::COL_BIRTH_DATE,
			$value
		);
	}

	/**
	 * Filters rows with `gte` condition on column `oz_users`.`birth_date`.
	 *
	 * @param int|string $value the filter value
	 *
	 * @return static
	 *
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereBirthDateIsGte(string|int $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('gte'),
			\OZONE\OZ\Db\OZUser::COL_BIRTH_DATE,
			$value
		);
	}

	/**
	 * Filters rows with `like` condition on column `oz_users`.`birth_date`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 *
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereBirthDateIsLike(string $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('like'),
			\OZONE\OZ\Db\OZUser::COL_BIRTH_DATE,
			$value
		);
	}

	/**
	 * Filters rows with `not_like` condition on column `oz_users`.`birth_date`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 *
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereBirthDateIsNotLike(string $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('not_like'),
			\OZONE\OZ\Db\OZUser::COL_BIRTH_DATE,
			$value
		);
	}

	/**
	 * Filters rows with `in` condition on column `oz_users`.`birth_date`.
	 *
	 * @param array $value the filter value
	 *
	 * @return static
	 *
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereBirthDateIsIn(array $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('in'),
			\OZONE\OZ\Db\OZUser::COL_BIRTH_DATE,
			$value
		);
	}

	/**
	 * Filters rows with `not_in` condition on column `oz_users`.`birth_date`.
	 *
	 * @param array $value the filter value
	 *
	 * @return static
	 *
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereBirthDateIsNotIn(array $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('not_in'),
			\OZONE\OZ\Db\OZUser::COL_BIRTH_DATE,
			$value
		);
	}

	/**
	 * Filters rows with `eq` condition on column `oz_users`.`pic`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 *
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function wherePicIs(string $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('eq'),
			\OZONE\OZ\Db\OZUser::COL_PIC,
			$value
		);
	}

	/**
	 * Filters rows with `neq` condition on column `oz_users`.`pic`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 *
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function wherePicIsNot(string $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('neq'),
			\OZONE\OZ\Db\OZUser::COL_PIC,
			$value
		);
	}

	/**
	 * Filters rows with `lt` condition on column `oz_users`.`pic`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 *
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function wherePicIsLt(string $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('lt'),
			\OZONE\OZ\Db\OZUser::COL_PIC,
			$value
		);
	}

	/**
	 * Filters rows with `lte` condition on column `oz_users`.`pic`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 *
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function wherePicIsLte(string $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('lte'),
			\OZONE\OZ\Db\OZUser::COL_PIC,
			$value
		);
	}

	/**
	 * Filters rows with `gt` condition on column `oz_users`.`pic`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 *
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function wherePicIsGt(string $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('gt'),
			\OZONE\OZ\Db\OZUser::COL_PIC,
			$value
		);
	}

	/**
	 * Filters rows with `gte` condition on column `oz_users`.`pic`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 *
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function wherePicIsGte(string $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('gte'),
			\OZONE\OZ\Db\OZUser::COL_PIC,
			$value
		);
	}

	/**
	 * Filters rows with `like` condition on column `oz_users`.`pic`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 *
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function wherePicIsLike(string $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('like'),
			\OZONE\OZ\Db\OZUser::COL_PIC,
			$value
		);
	}

	/**
	 * Filters rows with `not_like` condition on column `oz_users`.`pic`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 *
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function wherePicIsNotLike(string $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('not_like'),
			\OZONE\OZ\Db\OZUser::COL_PIC,
			$value
		);
	}

	/**
	 * Filters rows with `is_null` condition on column `oz_users`.`pic`.
	 *
	 * @return static
	 *
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function wherePicIsNull(): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('is_null'),
			\OZONE\OZ\Db\OZUser::COL_PIC
		);
	}

	/**
	 * Filters rows with `is_not_null` condition on column `oz_users`.`pic`.
	 *
	 * @return static
	 *
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function wherePicIsNotNull(): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('is_not_null'),
			\OZONE\OZ\Db\OZUser::COL_PIC
		);
	}

	/**
	 * Filters rows with `in` condition on column `oz_users`.`pic`.
	 *
	 * @param array $value the filter value
	 *
	 * @return static
	 *
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function wherePicIsIn(array $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('in'),
			\OZONE\OZ\Db\OZUser::COL_PIC,
			$value
		);
	}

	/**
	 * Filters rows with `not_in` condition on column `oz_users`.`pic`.
	 *
	 * @param array $value the filter value
	 *
	 * @return static
	 *
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function wherePicIsNotIn(array $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('not_in'),
			\OZONE\OZ\Db\OZUser::COL_PIC,
			$value
		);
	}

	/**
	 * Filters rows with `eq` condition on column `oz_users`.`cc2`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 *
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereCc2Is(string $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('eq'),
			\OZONE\OZ\Db\OZUser::COL_CC2,
			$value
		);
	}

	/**
	 * Filters rows with `neq` condition on column `oz_users`.`cc2`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 *
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereCc2IsNot(string $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('neq'),
			\OZONE\OZ\Db\OZUser::COL_CC2,
			$value
		);
	}

	/**
	 * Filters rows with `lt` condition on column `oz_users`.`cc2`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 *
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereCc2IsLt(string $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('lt'),
			\OZONE\OZ\Db\OZUser::COL_CC2,
			$value
		);
	}

	/**
	 * Filters rows with `lte` condition on column `oz_users`.`cc2`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 *
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereCc2IsLte(string $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('lte'),
			\OZONE\OZ\Db\OZUser::COL_CC2,
			$value
		);
	}

	/**
	 * Filters rows with `gt` condition on column `oz_users`.`cc2`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 *
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereCc2IsGt(string $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('gt'),
			\OZONE\OZ\Db\OZUser::COL_CC2,
			$value
		);
	}

	/**
	 * Filters rows with `gte` condition on column `oz_users`.`cc2`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 *
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereCc2IsGte(string $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('gte'),
			\OZONE\OZ\Db\OZUser::COL_CC2,
			$value
		);
	}

	/**
	 * Filters rows with `like` condition on column `oz_users`.`cc2`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 *
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereCc2IsLike(string $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('like'),
			\OZONE\OZ\Db\OZUser::COL_CC2,
			$value
		);
	}

	/**
	 * Filters rows with `not_like` condition on column `oz_users`.`cc2`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 *
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereCc2IsNotLike(string $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('not_like'),
			\OZONE\OZ\Db\OZUser::COL_CC2,
			$value
		);
	}

	/**
	 * Filters rows with `in` condition on column `oz_users`.`cc2`.
	 *
	 * @param array $value the filter value
	 *
	 * @return static
	 *
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereCc2IsIn(array $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('in'),
			\OZONE\OZ\Db\OZUser::COL_CC2,
			$value
		);
	}

	/**
	 * Filters rows with `not_in` condition on column `oz_users`.`cc2`.
	 *
	 * @param array $value the filter value
	 *
	 * @return static
	 *
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereCc2IsNotIn(array $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('not_in'),
			\OZONE\OZ\Db\OZUser::COL_CC2,
			$value
		);
	}

	/**
	 * Filters rows with `eq` condition on column `oz_users`.`data`.
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
			\OZONE\OZ\Db\OZUser::COL_DATA,
			$value
		);
	}

	/**
	 * Filters rows with `neq` condition on column `oz_users`.`data`.
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
			\OZONE\OZ\Db\OZUser::COL_DATA,
			$value
		);
	}

	/**
	 * Filters rows with `like` condition on column `oz_users`.`data`.
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
			\OZONE\OZ\Db\OZUser::COL_DATA,
			$value
		);
	}

	/**
	 * Filters rows with `not_like` condition on column `oz_users`.`data`.
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
			\OZONE\OZ\Db\OZUser::COL_DATA,
			$value
		);
	}

	/**
	 * Filters rows with `eq` condition on column `oz_users`.`created_at`.
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
			\OZONE\OZ\Db\OZUser::COL_CREATED_AT,
			$value
		);
	}

	/**
	 * Filters rows with `neq` condition on column `oz_users`.`created_at`.
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
			\OZONE\OZ\Db\OZUser::COL_CREATED_AT,
			$value
		);
	}

	/**
	 * Filters rows with `lt` condition on column `oz_users`.`created_at`.
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
			\OZONE\OZ\Db\OZUser::COL_CREATED_AT,
			$value
		);
	}

	/**
	 * Filters rows with `lte` condition on column `oz_users`.`created_at`.
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
			\OZONE\OZ\Db\OZUser::COL_CREATED_AT,
			$value
		);
	}

	/**
	 * Filters rows with `gt` condition on column `oz_users`.`created_at`.
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
			\OZONE\OZ\Db\OZUser::COL_CREATED_AT,
			$value
		);
	}

	/**
	 * Filters rows with `gte` condition on column `oz_users`.`created_at`.
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
			\OZONE\OZ\Db\OZUser::COL_CREATED_AT,
			$value
		);
	}

	/**
	 * Filters rows with `like` condition on column `oz_users`.`created_at`.
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
			\OZONE\OZ\Db\OZUser::COL_CREATED_AT,
			$value
		);
	}

	/**
	 * Filters rows with `not_like` condition on column `oz_users`.`created_at`.
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
			\OZONE\OZ\Db\OZUser::COL_CREATED_AT,
			$value
		);
	}

	/**
	 * Filters rows with `in` condition on column `oz_users`.`created_at`.
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
			\OZONE\OZ\Db\OZUser::COL_CREATED_AT,
			$value
		);
	}

	/**
	 * Filters rows with `not_in` condition on column `oz_users`.`created_at`.
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
			\OZONE\OZ\Db\OZUser::COL_CREATED_AT,
			$value
		);
	}

	/**
	 * Filters rows with `eq` condition on column `oz_users`.`updated_at`.
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
			\OZONE\OZ\Db\OZUser::COL_UPDATED_AT,
			$value
		);
	}

	/**
	 * Filters rows with `neq` condition on column `oz_users`.`updated_at`.
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
			\OZONE\OZ\Db\OZUser::COL_UPDATED_AT,
			$value
		);
	}

	/**
	 * Filters rows with `lt` condition on column `oz_users`.`updated_at`.
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
			\OZONE\OZ\Db\OZUser::COL_UPDATED_AT,
			$value
		);
	}

	/**
	 * Filters rows with `lte` condition on column `oz_users`.`updated_at`.
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
			\OZONE\OZ\Db\OZUser::COL_UPDATED_AT,
			$value
		);
	}

	/**
	 * Filters rows with `gt` condition on column `oz_users`.`updated_at`.
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
			\OZONE\OZ\Db\OZUser::COL_UPDATED_AT,
			$value
		);
	}

	/**
	 * Filters rows with `gte` condition on column `oz_users`.`updated_at`.
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
			\OZONE\OZ\Db\OZUser::COL_UPDATED_AT,
			$value
		);
	}

	/**
	 * Filters rows with `like` condition on column `oz_users`.`updated_at`.
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
			\OZONE\OZ\Db\OZUser::COL_UPDATED_AT,
			$value
		);
	}

	/**
	 * Filters rows with `not_like` condition on column `oz_users`.`updated_at`.
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
			\OZONE\OZ\Db\OZUser::COL_UPDATED_AT,
			$value
		);
	}

	/**
	 * Filters rows with `in` condition on column `oz_users`.`updated_at`.
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
			\OZONE\OZ\Db\OZUser::COL_UPDATED_AT,
			$value
		);
	}

	/**
	 * Filters rows with `not_in` condition on column `oz_users`.`updated_at`.
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
			\OZONE\OZ\Db\OZUser::COL_UPDATED_AT,
			$value
		);
	}

	/**
	 * Filters rows with `eq` condition on column `oz_users`.`valid`.
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
			\OZONE\OZ\Db\OZUser::COL_VALID,
			$value
		);
	}

	/**
	 * Filters rows with `neq` condition on column `oz_users`.`valid`.
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
			\OZONE\OZ\Db\OZUser::COL_VALID,
			$value
		);
	}

	/**
	 * Filters rows with `is_false` condition on column `oz_users`.`valid`.
	 *
	 * @return static
	 *
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereValidIsFalse(): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('is_false'),
			\OZONE\OZ\Db\OZUser::COL_VALID
		);
	}

	/**
	 * Filters rows with `is_true` condition on column `oz_users`.`valid`.
	 *
	 * @return static
	 *
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereValidIsTrue(): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('is_true'),
			\OZONE\OZ\Db\OZUser::COL_VALID
		);
	}
}
