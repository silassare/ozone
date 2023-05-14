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
 * Class OZFilesQuery.
 *
 * @method \OZONE\OZ\Db\OZFilesResults find(?int $max = null, int $offset = 0, array $order_by = [])
 */
abstract class OZFilesQuery extends \Gobl\ORM\ORMTableQuery
{
	/**
	 * OZFilesQuery constructor.
	 */
	public function __construct()
	{
		parent::__construct(
			\OZONE\OZ\Db\OZFile::TABLE_NAMESPACE,
			\OZONE\OZ\Db\OZFile::TABLE_NAME
		);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @return static
	 */
	public static function createInstance(): static
	{
		return new \OZONE\OZ\Db\OZFilesQuery();
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
	 * Filters rows with `eq` condition on column `oz_files`.`id`.
	 *
	 * @param int|string $value the filter value
	 *
	 * @return static
	 */
	public function whereIdIs(string|int $value): self
	{
		return $this->filterBy(
			Operator::from('eq'),
			\OZONE\OZ\Db\OZFile::COL_ID,
			$value
		);
	}

	/**
	 * Filters rows with `neq` condition on column `oz_files`.`id`.
	 *
	 * @param int|string $value the filter value
	 *
	 * @return static
	 */
	public function whereIdIsNot(string|int $value): self
	{
		return $this->filterBy(
			Operator::from('neq'),
			\OZONE\OZ\Db\OZFile::COL_ID,
			$value
		);
	}

	/**
	 * Filters rows with `lt` condition on column `oz_files`.`id`.
	 *
	 * @param int|string $value the filter value
	 *
	 * @return static
	 */
	public function whereIdIsLt(string|int $value): self
	{
		return $this->filterBy(
			Operator::from('lt'),
			\OZONE\OZ\Db\OZFile::COL_ID,
			$value
		);
	}

	/**
	 * Filters rows with `lte` condition on column `oz_files`.`id`.
	 *
	 * @param int|string $value the filter value
	 *
	 * @return static
	 */
	public function whereIdIsLte(string|int $value): self
	{
		return $this->filterBy(
			Operator::from('lte'),
			\OZONE\OZ\Db\OZFile::COL_ID,
			$value
		);
	}

	/**
	 * Filters rows with `gt` condition on column `oz_files`.`id`.
	 *
	 * @param int|string $value the filter value
	 *
	 * @return static
	 */
	public function whereIdIsGt(string|int $value): self
	{
		return $this->filterBy(
			Operator::from('gt'),
			\OZONE\OZ\Db\OZFile::COL_ID,
			$value
		);
	}

	/**
	 * Filters rows with `gte` condition on column `oz_files`.`id`.
	 *
	 * @param int|string $value the filter value
	 *
	 * @return static
	 */
	public function whereIdIsGte(string|int $value): self
	{
		return $this->filterBy(
			Operator::from('gte'),
			\OZONE\OZ\Db\OZFile::COL_ID,
			$value
		);
	}

	/**
	 * Filters rows with `like` condition on column `oz_files`.`id`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 */
	public function whereIdIsLike(string $value): self
	{
		return $this->filterBy(
			Operator::from('like'),
			\OZONE\OZ\Db\OZFile::COL_ID,
			$value
		);
	}

	/**
	 * Filters rows with `not_like` condition on column `oz_files`.`id`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 */
	public function whereIdIsNotLike(string $value): self
	{
		return $this->filterBy(
			Operator::from('not_like'),
			\OZONE\OZ\Db\OZFile::COL_ID,
			$value
		);
	}

	/**
	 * Filters rows with `in` condition on column `oz_files`.`id`.
	 *
	 * @param array $value the filter value
	 *
	 * @return static
	 */
	public function whereIdIsIn(array $value): self
	{
		return $this->filterBy(
			Operator::from('in'),
			\OZONE\OZ\Db\OZFile::COL_ID,
			$value
		);
	}

	/**
	 * Filters rows with `not_in` condition on column `oz_files`.`id`.
	 *
	 * @param array $value the filter value
	 *
	 * @return static
	 */
	public function whereIdIsNotIn(array $value): self
	{
		return $this->filterBy(
			Operator::from('not_in'),
			\OZONE\OZ\Db\OZFile::COL_ID,
			$value
		);
	}

	/**
	 * Filters rows with `eq` condition on column `oz_files`.`owner_id`.
	 *
	 * @param int|string $value the filter value
	 *
	 * @return static
	 */
	public function whereOwnerIdIs(string|int $value): self
	{
		return $this->filterBy(
			Operator::from('eq'),
			\OZONE\OZ\Db\OZFile::COL_OWNER_ID,
			$value
		);
	}

	/**
	 * Filters rows with `neq` condition on column `oz_files`.`owner_id`.
	 *
	 * @param int|string $value the filter value
	 *
	 * @return static
	 */
	public function whereOwnerIdIsNot(string|int $value): self
	{
		return $this->filterBy(
			Operator::from('neq'),
			\OZONE\OZ\Db\OZFile::COL_OWNER_ID,
			$value
		);
	}

	/**
	 * Filters rows with `lt` condition on column `oz_files`.`owner_id`.
	 *
	 * @param int|string $value the filter value
	 *
	 * @return static
	 */
	public function whereOwnerIdIsLt(string|int $value): self
	{
		return $this->filterBy(
			Operator::from('lt'),
			\OZONE\OZ\Db\OZFile::COL_OWNER_ID,
			$value
		);
	}

	/**
	 * Filters rows with `lte` condition on column `oz_files`.`owner_id`.
	 *
	 * @param int|string $value the filter value
	 *
	 * @return static
	 */
	public function whereOwnerIdIsLte(string|int $value): self
	{
		return $this->filterBy(
			Operator::from('lte'),
			\OZONE\OZ\Db\OZFile::COL_OWNER_ID,
			$value
		);
	}

	/**
	 * Filters rows with `gt` condition on column `oz_files`.`owner_id`.
	 *
	 * @param int|string $value the filter value
	 *
	 * @return static
	 */
	public function whereOwnerIdIsGt(string|int $value): self
	{
		return $this->filterBy(
			Operator::from('gt'),
			\OZONE\OZ\Db\OZFile::COL_OWNER_ID,
			$value
		);
	}

	/**
	 * Filters rows with `gte` condition on column `oz_files`.`owner_id`.
	 *
	 * @param int|string $value the filter value
	 *
	 * @return static
	 */
	public function whereOwnerIdIsGte(string|int $value): self
	{
		return $this->filterBy(
			Operator::from('gte'),
			\OZONE\OZ\Db\OZFile::COL_OWNER_ID,
			$value
		);
	}

	/**
	 * Filters rows with `like` condition on column `oz_files`.`owner_id`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 */
	public function whereOwnerIdIsLike(string $value): self
	{
		return $this->filterBy(
			Operator::from('like'),
			\OZONE\OZ\Db\OZFile::COL_OWNER_ID,
			$value
		);
	}

	/**
	 * Filters rows with `not_like` condition on column `oz_files`.`owner_id`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 */
	public function whereOwnerIdIsNotLike(string $value): self
	{
		return $this->filterBy(
			Operator::from('not_like'),
			\OZONE\OZ\Db\OZFile::COL_OWNER_ID,
			$value
		);
	}

	/**
	 * Filters rows with `is_null` condition on column `oz_files`.`owner_id`.
	 *
	 * @return static
	 */
	public function whereOwnerIdIsNull(): self
	{
		return $this->filterBy(
			Operator::from('is_null'),
			\OZONE\OZ\Db\OZFile::COL_OWNER_ID
		);
	}

	/**
	 * Filters rows with `is_not_null` condition on column `oz_files`.`owner_id`.
	 *
	 * @return static
	 */
	public function whereOwnerIdIsNotNull(): self
	{
		return $this->filterBy(
			Operator::from('is_not_null'),
			\OZONE\OZ\Db\OZFile::COL_OWNER_ID
		);
	}

	/**
	 * Filters rows with `in` condition on column `oz_files`.`owner_id`.
	 *
	 * @param array $value the filter value
	 *
	 * @return static
	 */
	public function whereOwnerIdIsIn(array $value): self
	{
		return $this->filterBy(
			Operator::from('in'),
			\OZONE\OZ\Db\OZFile::COL_OWNER_ID,
			$value
		);
	}

	/**
	 * Filters rows with `not_in` condition on column `oz_files`.`owner_id`.
	 *
	 * @param array $value the filter value
	 *
	 * @return static
	 */
	public function whereOwnerIdIsNotIn(array $value): self
	{
		return $this->filterBy(
			Operator::from('not_in'),
			\OZONE\OZ\Db\OZFile::COL_OWNER_ID,
			$value
		);
	}

	/**
	 * Filters rows with `eq` condition on column `oz_files`.`key`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 */
	public function whereKeyIs(string $value): self
	{
		return $this->filterBy(
			Operator::from('eq'),
			\OZONE\OZ\Db\OZFile::COL_KEY,
			$value
		);
	}

	/**
	 * Filters rows with `neq` condition on column `oz_files`.`key`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 */
	public function whereKeyIsNot(string $value): self
	{
		return $this->filterBy(
			Operator::from('neq'),
			\OZONE\OZ\Db\OZFile::COL_KEY,
			$value
		);
	}

	/**
	 * Filters rows with `lt` condition on column `oz_files`.`key`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 */
	public function whereKeyIsLt(string $value): self
	{
		return $this->filterBy(
			Operator::from('lt'),
			\OZONE\OZ\Db\OZFile::COL_KEY,
			$value
		);
	}

	/**
	 * Filters rows with `lte` condition on column `oz_files`.`key`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 */
	public function whereKeyIsLte(string $value): self
	{
		return $this->filterBy(
			Operator::from('lte'),
			\OZONE\OZ\Db\OZFile::COL_KEY,
			$value
		);
	}

	/**
	 * Filters rows with `gt` condition on column `oz_files`.`key`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 */
	public function whereKeyIsGt(string $value): self
	{
		return $this->filterBy(
			Operator::from('gt'),
			\OZONE\OZ\Db\OZFile::COL_KEY,
			$value
		);
	}

	/**
	 * Filters rows with `gte` condition on column `oz_files`.`key`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 */
	public function whereKeyIsGte(string $value): self
	{
		return $this->filterBy(
			Operator::from('gte'),
			\OZONE\OZ\Db\OZFile::COL_KEY,
			$value
		);
	}

	/**
	 * Filters rows with `like` condition on column `oz_files`.`key`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 */
	public function whereKeyIsLike(string $value): self
	{
		return $this->filterBy(
			Operator::from('like'),
			\OZONE\OZ\Db\OZFile::COL_KEY,
			$value
		);
	}

	/**
	 * Filters rows with `not_like` condition on column `oz_files`.`key`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 */
	public function whereKeyIsNotLike(string $value): self
	{
		return $this->filterBy(
			Operator::from('not_like'),
			\OZONE\OZ\Db\OZFile::COL_KEY,
			$value
		);
	}

	/**
	 * Filters rows with `in` condition on column `oz_files`.`key`.
	 *
	 * @param array $value the filter value
	 *
	 * @return static
	 */
	public function whereKeyIsIn(array $value): self
	{
		return $this->filterBy(
			Operator::from('in'),
			\OZONE\OZ\Db\OZFile::COL_KEY,
			$value
		);
	}

	/**
	 * Filters rows with `not_in` condition on column `oz_files`.`key`.
	 *
	 * @param array $value the filter value
	 *
	 * @return static
	 */
	public function whereKeyIsNotIn(array $value): self
	{
		return $this->filterBy(
			Operator::from('not_in'),
			\OZONE\OZ\Db\OZFile::COL_KEY,
			$value
		);
	}

	/**
	 * Filters rows with `eq` condition on column `oz_files`.`ref`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 */
	public function whereRefIs(string $value): self
	{
		return $this->filterBy(
			Operator::from('eq'),
			\OZONE\OZ\Db\OZFile::COL_REF,
			$value
		);
	}

	/**
	 * Filters rows with `neq` condition on column `oz_files`.`ref`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 */
	public function whereRefIsNot(string $value): self
	{
		return $this->filterBy(
			Operator::from('neq'),
			\OZONE\OZ\Db\OZFile::COL_REF,
			$value
		);
	}

	/**
	 * Filters rows with `lt` condition on column `oz_files`.`ref`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 */
	public function whereRefIsLt(string $value): self
	{
		return $this->filterBy(
			Operator::from('lt'),
			\OZONE\OZ\Db\OZFile::COL_REF,
			$value
		);
	}

	/**
	 * Filters rows with `lte` condition on column `oz_files`.`ref`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 */
	public function whereRefIsLte(string $value): self
	{
		return $this->filterBy(
			Operator::from('lte'),
			\OZONE\OZ\Db\OZFile::COL_REF,
			$value
		);
	}

	/**
	 * Filters rows with `gt` condition on column `oz_files`.`ref`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 */
	public function whereRefIsGt(string $value): self
	{
		return $this->filterBy(
			Operator::from('gt'),
			\OZONE\OZ\Db\OZFile::COL_REF,
			$value
		);
	}

	/**
	 * Filters rows with `gte` condition on column `oz_files`.`ref`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 */
	public function whereRefIsGte(string $value): self
	{
		return $this->filterBy(
			Operator::from('gte'),
			\OZONE\OZ\Db\OZFile::COL_REF,
			$value
		);
	}

	/**
	 * Filters rows with `like` condition on column `oz_files`.`ref`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 */
	public function whereRefIsLike(string $value): self
	{
		return $this->filterBy(
			Operator::from('like'),
			\OZONE\OZ\Db\OZFile::COL_REF,
			$value
		);
	}

	/**
	 * Filters rows with `not_like` condition on column `oz_files`.`ref`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 */
	public function whereRefIsNotLike(string $value): self
	{
		return $this->filterBy(
			Operator::from('not_like'),
			\OZONE\OZ\Db\OZFile::COL_REF,
			$value
		);
	}

	/**
	 * Filters rows with `in` condition on column `oz_files`.`ref`.
	 *
	 * @param array $value the filter value
	 *
	 * @return static
	 */
	public function whereRefIsIn(array $value): self
	{
		return $this->filterBy(
			Operator::from('in'),
			\OZONE\OZ\Db\OZFile::COL_REF,
			$value
		);
	}

	/**
	 * Filters rows with `not_in` condition on column `oz_files`.`ref`.
	 *
	 * @param array $value the filter value
	 *
	 * @return static
	 */
	public function whereRefIsNotIn(array $value): self
	{
		return $this->filterBy(
			Operator::from('not_in'),
			\OZONE\OZ\Db\OZFile::COL_REF,
			$value
		);
	}

	/**
	 * Filters rows with `eq` condition on column `oz_files`.`driver`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 */
	public function whereDriverIs(string $value): self
	{
		return $this->filterBy(
			Operator::from('eq'),
			\OZONE\OZ\Db\OZFile::COL_DRIVER,
			$value
		);
	}

	/**
	 * Filters rows with `neq` condition on column `oz_files`.`driver`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 */
	public function whereDriverIsNot(string $value): self
	{
		return $this->filterBy(
			Operator::from('neq'),
			\OZONE\OZ\Db\OZFile::COL_DRIVER,
			$value
		);
	}

	/**
	 * Filters rows with `lt` condition on column `oz_files`.`driver`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 */
	public function whereDriverIsLt(string $value): self
	{
		return $this->filterBy(
			Operator::from('lt'),
			\OZONE\OZ\Db\OZFile::COL_DRIVER,
			$value
		);
	}

	/**
	 * Filters rows with `lte` condition on column `oz_files`.`driver`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 */
	public function whereDriverIsLte(string $value): self
	{
		return $this->filterBy(
			Operator::from('lte'),
			\OZONE\OZ\Db\OZFile::COL_DRIVER,
			$value
		);
	}

	/**
	 * Filters rows with `gt` condition on column `oz_files`.`driver`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 */
	public function whereDriverIsGt(string $value): self
	{
		return $this->filterBy(
			Operator::from('gt'),
			\OZONE\OZ\Db\OZFile::COL_DRIVER,
			$value
		);
	}

	/**
	 * Filters rows with `gte` condition on column `oz_files`.`driver`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 */
	public function whereDriverIsGte(string $value): self
	{
		return $this->filterBy(
			Operator::from('gte'),
			\OZONE\OZ\Db\OZFile::COL_DRIVER,
			$value
		);
	}

	/**
	 * Filters rows with `like` condition on column `oz_files`.`driver`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 */
	public function whereDriverIsLike(string $value): self
	{
		return $this->filterBy(
			Operator::from('like'),
			\OZONE\OZ\Db\OZFile::COL_DRIVER,
			$value
		);
	}

	/**
	 * Filters rows with `not_like` condition on column `oz_files`.`driver`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 */
	public function whereDriverIsNotLike(string $value): self
	{
		return $this->filterBy(
			Operator::from('not_like'),
			\OZONE\OZ\Db\OZFile::COL_DRIVER,
			$value
		);
	}

	/**
	 * Filters rows with `in` condition on column `oz_files`.`driver`.
	 *
	 * @param array $value the filter value
	 *
	 * @return static
	 */
	public function whereDriverIsIn(array $value): self
	{
		return $this->filterBy(
			Operator::from('in'),
			\OZONE\OZ\Db\OZFile::COL_DRIVER,
			$value
		);
	}

	/**
	 * Filters rows with `not_in` condition on column `oz_files`.`driver`.
	 *
	 * @param array $value the filter value
	 *
	 * @return static
	 */
	public function whereDriverIsNotIn(array $value): self
	{
		return $this->filterBy(
			Operator::from('not_in'),
			\OZONE\OZ\Db\OZFile::COL_DRIVER,
			$value
		);
	}

	/**
	 * Filters rows with `eq` condition on column `oz_files`.`clone_id`.
	 *
	 * @param int|string $value the filter value
	 *
	 * @return static
	 */
	public function whereCloneIdIs(string|int $value): self
	{
		return $this->filterBy(
			Operator::from('eq'),
			\OZONE\OZ\Db\OZFile::COL_CLONE_ID,
			$value
		);
	}

	/**
	 * Filters rows with `neq` condition on column `oz_files`.`clone_id`.
	 *
	 * @param int|string $value the filter value
	 *
	 * @return static
	 */
	public function whereCloneIdIsNot(string|int $value): self
	{
		return $this->filterBy(
			Operator::from('neq'),
			\OZONE\OZ\Db\OZFile::COL_CLONE_ID,
			$value
		);
	}

	/**
	 * Filters rows with `lt` condition on column `oz_files`.`clone_id`.
	 *
	 * @param int|string $value the filter value
	 *
	 * @return static
	 */
	public function whereCloneIdIsLt(string|int $value): self
	{
		return $this->filterBy(
			Operator::from('lt'),
			\OZONE\OZ\Db\OZFile::COL_CLONE_ID,
			$value
		);
	}

	/**
	 * Filters rows with `lte` condition on column `oz_files`.`clone_id`.
	 *
	 * @param int|string $value the filter value
	 *
	 * @return static
	 */
	public function whereCloneIdIsLte(string|int $value): self
	{
		return $this->filterBy(
			Operator::from('lte'),
			\OZONE\OZ\Db\OZFile::COL_CLONE_ID,
			$value
		);
	}

	/**
	 * Filters rows with `gt` condition on column `oz_files`.`clone_id`.
	 *
	 * @param int|string $value the filter value
	 *
	 * @return static
	 */
	public function whereCloneIdIsGt(string|int $value): self
	{
		return $this->filterBy(
			Operator::from('gt'),
			\OZONE\OZ\Db\OZFile::COL_CLONE_ID,
			$value
		);
	}

	/**
	 * Filters rows with `gte` condition on column `oz_files`.`clone_id`.
	 *
	 * @param int|string $value the filter value
	 *
	 * @return static
	 */
	public function whereCloneIdIsGte(string|int $value): self
	{
		return $this->filterBy(
			Operator::from('gte'),
			\OZONE\OZ\Db\OZFile::COL_CLONE_ID,
			$value
		);
	}

	/**
	 * Filters rows with `like` condition on column `oz_files`.`clone_id`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 */
	public function whereCloneIdIsLike(string $value): self
	{
		return $this->filterBy(
			Operator::from('like'),
			\OZONE\OZ\Db\OZFile::COL_CLONE_ID,
			$value
		);
	}

	/**
	 * Filters rows with `not_like` condition on column `oz_files`.`clone_id`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 */
	public function whereCloneIdIsNotLike(string $value): self
	{
		return $this->filterBy(
			Operator::from('not_like'),
			\OZONE\OZ\Db\OZFile::COL_CLONE_ID,
			$value
		);
	}

	/**
	 * Filters rows with `is_null` condition on column `oz_files`.`clone_id`.
	 *
	 * @return static
	 */
	public function whereCloneIdIsNull(): self
	{
		return $this->filterBy(
			Operator::from('is_null'),
			\OZONE\OZ\Db\OZFile::COL_CLONE_ID
		);
	}

	/**
	 * Filters rows with `is_not_null` condition on column `oz_files`.`clone_id`.
	 *
	 * @return static
	 */
	public function whereCloneIdIsNotNull(): self
	{
		return $this->filterBy(
			Operator::from('is_not_null'),
			\OZONE\OZ\Db\OZFile::COL_CLONE_ID
		);
	}

	/**
	 * Filters rows with `in` condition on column `oz_files`.`clone_id`.
	 *
	 * @param array $value the filter value
	 *
	 * @return static
	 */
	public function whereCloneIdIsIn(array $value): self
	{
		return $this->filterBy(
			Operator::from('in'),
			\OZONE\OZ\Db\OZFile::COL_CLONE_ID,
			$value
		);
	}

	/**
	 * Filters rows with `not_in` condition on column `oz_files`.`clone_id`.
	 *
	 * @param array $value the filter value
	 *
	 * @return static
	 */
	public function whereCloneIdIsNotIn(array $value): self
	{
		return $this->filterBy(
			Operator::from('not_in'),
			\OZONE\OZ\Db\OZFile::COL_CLONE_ID,
			$value
		);
	}

	/**
	 * Filters rows with `eq` condition on column `oz_files`.`source_id`.
	 *
	 * @param int|string $value the filter value
	 *
	 * @return static
	 */
	public function whereSourceIdIs(string|int $value): self
	{
		return $this->filterBy(
			Operator::from('eq'),
			\OZONE\OZ\Db\OZFile::COL_SOURCE_ID,
			$value
		);
	}

	/**
	 * Filters rows with `neq` condition on column `oz_files`.`source_id`.
	 *
	 * @param int|string $value the filter value
	 *
	 * @return static
	 */
	public function whereSourceIdIsNot(string|int $value): self
	{
		return $this->filterBy(
			Operator::from('neq'),
			\OZONE\OZ\Db\OZFile::COL_SOURCE_ID,
			$value
		);
	}

	/**
	 * Filters rows with `lt` condition on column `oz_files`.`source_id`.
	 *
	 * @param int|string $value the filter value
	 *
	 * @return static
	 */
	public function whereSourceIdIsLt(string|int $value): self
	{
		return $this->filterBy(
			Operator::from('lt'),
			\OZONE\OZ\Db\OZFile::COL_SOURCE_ID,
			$value
		);
	}

	/**
	 * Filters rows with `lte` condition on column `oz_files`.`source_id`.
	 *
	 * @param int|string $value the filter value
	 *
	 * @return static
	 */
	public function whereSourceIdIsLte(string|int $value): self
	{
		return $this->filterBy(
			Operator::from('lte'),
			\OZONE\OZ\Db\OZFile::COL_SOURCE_ID,
			$value
		);
	}

	/**
	 * Filters rows with `gt` condition on column `oz_files`.`source_id`.
	 *
	 * @param int|string $value the filter value
	 *
	 * @return static
	 */
	public function whereSourceIdIsGt(string|int $value): self
	{
		return $this->filterBy(
			Operator::from('gt'),
			\OZONE\OZ\Db\OZFile::COL_SOURCE_ID,
			$value
		);
	}

	/**
	 * Filters rows with `gte` condition on column `oz_files`.`source_id`.
	 *
	 * @param int|string $value the filter value
	 *
	 * @return static
	 */
	public function whereSourceIdIsGte(string|int $value): self
	{
		return $this->filterBy(
			Operator::from('gte'),
			\OZONE\OZ\Db\OZFile::COL_SOURCE_ID,
			$value
		);
	}

	/**
	 * Filters rows with `like` condition on column `oz_files`.`source_id`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 */
	public function whereSourceIdIsLike(string $value): self
	{
		return $this->filterBy(
			Operator::from('like'),
			\OZONE\OZ\Db\OZFile::COL_SOURCE_ID,
			$value
		);
	}

	/**
	 * Filters rows with `not_like` condition on column `oz_files`.`source_id`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 */
	public function whereSourceIdIsNotLike(string $value): self
	{
		return $this->filterBy(
			Operator::from('not_like'),
			\OZONE\OZ\Db\OZFile::COL_SOURCE_ID,
			$value
		);
	}

	/**
	 * Filters rows with `is_null` condition on column `oz_files`.`source_id`.
	 *
	 * @return static
	 */
	public function whereSourceIdIsNull(): self
	{
		return $this->filterBy(
			Operator::from('is_null'),
			\OZONE\OZ\Db\OZFile::COL_SOURCE_ID
		);
	}

	/**
	 * Filters rows with `is_not_null` condition on column `oz_files`.`source_id`.
	 *
	 * @return static
	 */
	public function whereSourceIdIsNotNull(): self
	{
		return $this->filterBy(
			Operator::from('is_not_null'),
			\OZONE\OZ\Db\OZFile::COL_SOURCE_ID
		);
	}

	/**
	 * Filters rows with `in` condition on column `oz_files`.`source_id`.
	 *
	 * @param array $value the filter value
	 *
	 * @return static
	 */
	public function whereSourceIdIsIn(array $value): self
	{
		return $this->filterBy(
			Operator::from('in'),
			\OZONE\OZ\Db\OZFile::COL_SOURCE_ID,
			$value
		);
	}

	/**
	 * Filters rows with `not_in` condition on column `oz_files`.`source_id`.
	 *
	 * @param array $value the filter value
	 *
	 * @return static
	 */
	public function whereSourceIdIsNotIn(array $value): self
	{
		return $this->filterBy(
			Operator::from('not_in'),
			\OZONE\OZ\Db\OZFile::COL_SOURCE_ID,
			$value
		);
	}

	/**
	 * Filters rows with `eq` condition on column `oz_files`.`size`.
	 *
	 * @param int $value the filter value
	 *
	 * @return static
	 */
	public function whereSizeIs(int $value): self
	{
		return $this->filterBy(
			Operator::from('eq'),
			\OZONE\OZ\Db\OZFile::COL_SIZE,
			$value
		);
	}

	/**
	 * Filters rows with `neq` condition on column `oz_files`.`size`.
	 *
	 * @param int $value the filter value
	 *
	 * @return static
	 */
	public function whereSizeIsNot(int $value): self
	{
		return $this->filterBy(
			Operator::from('neq'),
			\OZONE\OZ\Db\OZFile::COL_SIZE,
			$value
		);
	}

	/**
	 * Filters rows with `lt` condition on column `oz_files`.`size`.
	 *
	 * @param int $value the filter value
	 *
	 * @return static
	 */
	public function whereSizeIsLt(int $value): self
	{
		return $this->filterBy(
			Operator::from('lt'),
			\OZONE\OZ\Db\OZFile::COL_SIZE,
			$value
		);
	}

	/**
	 * Filters rows with `lte` condition on column `oz_files`.`size`.
	 *
	 * @param int $value the filter value
	 *
	 * @return static
	 */
	public function whereSizeIsLte(int $value): self
	{
		return $this->filterBy(
			Operator::from('lte'),
			\OZONE\OZ\Db\OZFile::COL_SIZE,
			$value
		);
	}

	/**
	 * Filters rows with `gt` condition on column `oz_files`.`size`.
	 *
	 * @param int $value the filter value
	 *
	 * @return static
	 */
	public function whereSizeIsGt(int $value): self
	{
		return $this->filterBy(
			Operator::from('gt'),
			\OZONE\OZ\Db\OZFile::COL_SIZE,
			$value
		);
	}

	/**
	 * Filters rows with `gte` condition on column `oz_files`.`size`.
	 *
	 * @param int $value the filter value
	 *
	 * @return static
	 */
	public function whereSizeIsGte(int $value): self
	{
		return $this->filterBy(
			Operator::from('gte'),
			\OZONE\OZ\Db\OZFile::COL_SIZE,
			$value
		);
	}

	/**
	 * Filters rows with `like` condition on column `oz_files`.`size`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 */
	public function whereSizeIsLike(string $value): self
	{
		return $this->filterBy(
			Operator::from('like'),
			\OZONE\OZ\Db\OZFile::COL_SIZE,
			$value
		);
	}

	/**
	 * Filters rows with `not_like` condition on column `oz_files`.`size`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 */
	public function whereSizeIsNotLike(string $value): self
	{
		return $this->filterBy(
			Operator::from('not_like'),
			\OZONE\OZ\Db\OZFile::COL_SIZE,
			$value
		);
	}

	/**
	 * Filters rows with `in` condition on column `oz_files`.`size`.
	 *
	 * @param array $value the filter value
	 *
	 * @return static
	 */
	public function whereSizeIsIn(array $value): self
	{
		return $this->filterBy(
			Operator::from('in'),
			\OZONE\OZ\Db\OZFile::COL_SIZE,
			$value
		);
	}

	/**
	 * Filters rows with `not_in` condition on column `oz_files`.`size`.
	 *
	 * @param array $value the filter value
	 *
	 * @return static
	 */
	public function whereSizeIsNotIn(array $value): self
	{
		return $this->filterBy(
			Operator::from('not_in'),
			\OZONE\OZ\Db\OZFile::COL_SIZE,
			$value
		);
	}

	/**
	 * Filters rows with `eq` condition on column `oz_files`.`mime_type`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 */
	public function whereMimeTypeIs(string $value): self
	{
		return $this->filterBy(
			Operator::from('eq'),
			\OZONE\OZ\Db\OZFile::COL_MIME_TYPE,
			$value
		);
	}

	/**
	 * Filters rows with `neq` condition on column `oz_files`.`mime_type`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 */
	public function whereMimeTypeIsNot(string $value): self
	{
		return $this->filterBy(
			Operator::from('neq'),
			\OZONE\OZ\Db\OZFile::COL_MIME_TYPE,
			$value
		);
	}

	/**
	 * Filters rows with `lt` condition on column `oz_files`.`mime_type`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 */
	public function whereMimeTypeIsLt(string $value): self
	{
		return $this->filterBy(
			Operator::from('lt'),
			\OZONE\OZ\Db\OZFile::COL_MIME_TYPE,
			$value
		);
	}

	/**
	 * Filters rows with `lte` condition on column `oz_files`.`mime_type`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 */
	public function whereMimeTypeIsLte(string $value): self
	{
		return $this->filterBy(
			Operator::from('lte'),
			\OZONE\OZ\Db\OZFile::COL_MIME_TYPE,
			$value
		);
	}

	/**
	 * Filters rows with `gt` condition on column `oz_files`.`mime_type`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 */
	public function whereMimeTypeIsGt(string $value): self
	{
		return $this->filterBy(
			Operator::from('gt'),
			\OZONE\OZ\Db\OZFile::COL_MIME_TYPE,
			$value
		);
	}

	/**
	 * Filters rows with `gte` condition on column `oz_files`.`mime_type`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 */
	public function whereMimeTypeIsGte(string $value): self
	{
		return $this->filterBy(
			Operator::from('gte'),
			\OZONE\OZ\Db\OZFile::COL_MIME_TYPE,
			$value
		);
	}

	/**
	 * Filters rows with `like` condition on column `oz_files`.`mime_type`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 */
	public function whereMimeTypeIsLike(string $value): self
	{
		return $this->filterBy(
			Operator::from('like'),
			\OZONE\OZ\Db\OZFile::COL_MIME_TYPE,
			$value
		);
	}

	/**
	 * Filters rows with `not_like` condition on column `oz_files`.`mime_type`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 */
	public function whereMimeTypeIsNotLike(string $value): self
	{
		return $this->filterBy(
			Operator::from('not_like'),
			\OZONE\OZ\Db\OZFile::COL_MIME_TYPE,
			$value
		);
	}

	/**
	 * Filters rows with `in` condition on column `oz_files`.`mime_type`.
	 *
	 * @param array $value the filter value
	 *
	 * @return static
	 */
	public function whereMimeTypeIsIn(array $value): self
	{
		return $this->filterBy(
			Operator::from('in'),
			\OZONE\OZ\Db\OZFile::COL_MIME_TYPE,
			$value
		);
	}

	/**
	 * Filters rows with `not_in` condition on column `oz_files`.`mime_type`.
	 *
	 * @param array $value the filter value
	 *
	 * @return static
	 */
	public function whereMimeTypeIsNotIn(array $value): self
	{
		return $this->filterBy(
			Operator::from('not_in'),
			\OZONE\OZ\Db\OZFile::COL_MIME_TYPE,
			$value
		);
	}

	/**
	 * Filters rows with `eq` condition on column `oz_files`.`extension`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 */
	public function whereExtensionIs(string $value): self
	{
		return $this->filterBy(
			Operator::from('eq'),
			\OZONE\OZ\Db\OZFile::COL_EXTENSION,
			$value
		);
	}

	/**
	 * Filters rows with `neq` condition on column `oz_files`.`extension`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 */
	public function whereExtensionIsNot(string $value): self
	{
		return $this->filterBy(
			Operator::from('neq'),
			\OZONE\OZ\Db\OZFile::COL_EXTENSION,
			$value
		);
	}

	/**
	 * Filters rows with `lt` condition on column `oz_files`.`extension`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 */
	public function whereExtensionIsLt(string $value): self
	{
		return $this->filterBy(
			Operator::from('lt'),
			\OZONE\OZ\Db\OZFile::COL_EXTENSION,
			$value
		);
	}

	/**
	 * Filters rows with `lte` condition on column `oz_files`.`extension`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 */
	public function whereExtensionIsLte(string $value): self
	{
		return $this->filterBy(
			Operator::from('lte'),
			\OZONE\OZ\Db\OZFile::COL_EXTENSION,
			$value
		);
	}

	/**
	 * Filters rows with `gt` condition on column `oz_files`.`extension`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 */
	public function whereExtensionIsGt(string $value): self
	{
		return $this->filterBy(
			Operator::from('gt'),
			\OZONE\OZ\Db\OZFile::COL_EXTENSION,
			$value
		);
	}

	/**
	 * Filters rows with `gte` condition on column `oz_files`.`extension`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 */
	public function whereExtensionIsGte(string $value): self
	{
		return $this->filterBy(
			Operator::from('gte'),
			\OZONE\OZ\Db\OZFile::COL_EXTENSION,
			$value
		);
	}

	/**
	 * Filters rows with `like` condition on column `oz_files`.`extension`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 */
	public function whereExtensionIsLike(string $value): self
	{
		return $this->filterBy(
			Operator::from('like'),
			\OZONE\OZ\Db\OZFile::COL_EXTENSION,
			$value
		);
	}

	/**
	 * Filters rows with `not_like` condition on column `oz_files`.`extension`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 */
	public function whereExtensionIsNotLike(string $value): self
	{
		return $this->filterBy(
			Operator::from('not_like'),
			\OZONE\OZ\Db\OZFile::COL_EXTENSION,
			$value
		);
	}

	/**
	 * Filters rows with `in` condition on column `oz_files`.`extension`.
	 *
	 * @param array $value the filter value
	 *
	 * @return static
	 */
	public function whereExtensionIsIn(array $value): self
	{
		return $this->filterBy(
			Operator::from('in'),
			\OZONE\OZ\Db\OZFile::COL_EXTENSION,
			$value
		);
	}

	/**
	 * Filters rows with `not_in` condition on column `oz_files`.`extension`.
	 *
	 * @param array $value the filter value
	 *
	 * @return static
	 */
	public function whereExtensionIsNotIn(array $value): self
	{
		return $this->filterBy(
			Operator::from('not_in'),
			\OZONE\OZ\Db\OZFile::COL_EXTENSION,
			$value
		);
	}

	/**
	 * Filters rows with `eq` condition on column `oz_files`.`name`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 */
	public function whereNameIs(string $value): self
	{
		return $this->filterBy(
			Operator::from('eq'),
			\OZONE\OZ\Db\OZFile::COL_NAME,
			$value
		);
	}

	/**
	 * Filters rows with `neq` condition on column `oz_files`.`name`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 */
	public function whereNameIsNot(string $value): self
	{
		return $this->filterBy(
			Operator::from('neq'),
			\OZONE\OZ\Db\OZFile::COL_NAME,
			$value
		);
	}

	/**
	 * Filters rows with `lt` condition on column `oz_files`.`name`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 */
	public function whereNameIsLt(string $value): self
	{
		return $this->filterBy(
			Operator::from('lt'),
			\OZONE\OZ\Db\OZFile::COL_NAME,
			$value
		);
	}

	/**
	 * Filters rows with `lte` condition on column `oz_files`.`name`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 */
	public function whereNameIsLte(string $value): self
	{
		return $this->filterBy(
			Operator::from('lte'),
			\OZONE\OZ\Db\OZFile::COL_NAME,
			$value
		);
	}

	/**
	 * Filters rows with `gt` condition on column `oz_files`.`name`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 */
	public function whereNameIsGt(string $value): self
	{
		return $this->filterBy(
			Operator::from('gt'),
			\OZONE\OZ\Db\OZFile::COL_NAME,
			$value
		);
	}

	/**
	 * Filters rows with `gte` condition on column `oz_files`.`name`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 */
	public function whereNameIsGte(string $value): self
	{
		return $this->filterBy(
			Operator::from('gte'),
			\OZONE\OZ\Db\OZFile::COL_NAME,
			$value
		);
	}

	/**
	 * Filters rows with `like` condition on column `oz_files`.`name`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 */
	public function whereNameIsLike(string $value): self
	{
		return $this->filterBy(
			Operator::from('like'),
			\OZONE\OZ\Db\OZFile::COL_NAME,
			$value
		);
	}

	/**
	 * Filters rows with `not_like` condition on column `oz_files`.`name`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 */
	public function whereNameIsNotLike(string $value): self
	{
		return $this->filterBy(
			Operator::from('not_like'),
			\OZONE\OZ\Db\OZFile::COL_NAME,
			$value
		);
	}

	/**
	 * Filters rows with `in` condition on column `oz_files`.`name`.
	 *
	 * @param array $value the filter value
	 *
	 * @return static
	 */
	public function whereNameIsIn(array $value): self
	{
		return $this->filterBy(
			Operator::from('in'),
			\OZONE\OZ\Db\OZFile::COL_NAME,
			$value
		);
	}

	/**
	 * Filters rows with `not_in` condition on column `oz_files`.`name`.
	 *
	 * @param array $value the filter value
	 *
	 * @return static
	 */
	public function whereNameIsNotIn(array $value): self
	{
		return $this->filterBy(
			Operator::from('not_in'),
			\OZONE\OZ\Db\OZFile::COL_NAME,
			$value
		);
	}

	/**
	 * Filters rows with `eq` condition on column `oz_files`.`for_id`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 */
	public function whereForIdIs(string $value): self
	{
		return $this->filterBy(
			Operator::from('eq'),
			\OZONE\OZ\Db\OZFile::COL_FOR_ID,
			$value
		);
	}

	/**
	 * Filters rows with `neq` condition on column `oz_files`.`for_id`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 */
	public function whereForIdIsNot(string $value): self
	{
		return $this->filterBy(
			Operator::from('neq'),
			\OZONE\OZ\Db\OZFile::COL_FOR_ID,
			$value
		);
	}

	/**
	 * Filters rows with `lt` condition on column `oz_files`.`for_id`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 */
	public function whereForIdIsLt(string $value): self
	{
		return $this->filterBy(
			Operator::from('lt'),
			\OZONE\OZ\Db\OZFile::COL_FOR_ID,
			$value
		);
	}

	/**
	 * Filters rows with `lte` condition on column `oz_files`.`for_id`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 */
	public function whereForIdIsLte(string $value): self
	{
		return $this->filterBy(
			Operator::from('lte'),
			\OZONE\OZ\Db\OZFile::COL_FOR_ID,
			$value
		);
	}

	/**
	 * Filters rows with `gt` condition on column `oz_files`.`for_id`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 */
	public function whereForIdIsGt(string $value): self
	{
		return $this->filterBy(
			Operator::from('gt'),
			\OZONE\OZ\Db\OZFile::COL_FOR_ID,
			$value
		);
	}

	/**
	 * Filters rows with `gte` condition on column `oz_files`.`for_id`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 */
	public function whereForIdIsGte(string $value): self
	{
		return $this->filterBy(
			Operator::from('gte'),
			\OZONE\OZ\Db\OZFile::COL_FOR_ID,
			$value
		);
	}

	/**
	 * Filters rows with `like` condition on column `oz_files`.`for_id`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 */
	public function whereForIdIsLike(string $value): self
	{
		return $this->filterBy(
			Operator::from('like'),
			\OZONE\OZ\Db\OZFile::COL_FOR_ID,
			$value
		);
	}

	/**
	 * Filters rows with `not_like` condition on column `oz_files`.`for_id`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 */
	public function whereForIdIsNotLike(string $value): self
	{
		return $this->filterBy(
			Operator::from('not_like'),
			\OZONE\OZ\Db\OZFile::COL_FOR_ID,
			$value
		);
	}

	/**
	 * Filters rows with `is_null` condition on column `oz_files`.`for_id`.
	 *
	 * @return static
	 */
	public function whereForIdIsNull(): self
	{
		return $this->filterBy(
			Operator::from('is_null'),
			\OZONE\OZ\Db\OZFile::COL_FOR_ID
		);
	}

	/**
	 * Filters rows with `is_not_null` condition on column `oz_files`.`for_id`.
	 *
	 * @return static
	 */
	public function whereForIdIsNotNull(): self
	{
		return $this->filterBy(
			Operator::from('is_not_null'),
			\OZONE\OZ\Db\OZFile::COL_FOR_ID
		);
	}

	/**
	 * Filters rows with `in` condition on column `oz_files`.`for_id`.
	 *
	 * @param array $value the filter value
	 *
	 * @return static
	 */
	public function whereForIdIsIn(array $value): self
	{
		return $this->filterBy(
			Operator::from('in'),
			\OZONE\OZ\Db\OZFile::COL_FOR_ID,
			$value
		);
	}

	/**
	 * Filters rows with `not_in` condition on column `oz_files`.`for_id`.
	 *
	 * @param array $value the filter value
	 *
	 * @return static
	 */
	public function whereForIdIsNotIn(array $value): self
	{
		return $this->filterBy(
			Operator::from('not_in'),
			\OZONE\OZ\Db\OZFile::COL_FOR_ID,
			$value
		);
	}

	/**
	 * Filters rows with `eq` condition on column `oz_files`.`for_type`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 */
	public function whereForTypeIs(string $value): self
	{
		return $this->filterBy(
			Operator::from('eq'),
			\OZONE\OZ\Db\OZFile::COL_FOR_TYPE,
			$value
		);
	}

	/**
	 * Filters rows with `neq` condition on column `oz_files`.`for_type`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 */
	public function whereForTypeIsNot(string $value): self
	{
		return $this->filterBy(
			Operator::from('neq'),
			\OZONE\OZ\Db\OZFile::COL_FOR_TYPE,
			$value
		);
	}

	/**
	 * Filters rows with `lt` condition on column `oz_files`.`for_type`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 */
	public function whereForTypeIsLt(string $value): self
	{
		return $this->filterBy(
			Operator::from('lt'),
			\OZONE\OZ\Db\OZFile::COL_FOR_TYPE,
			$value
		);
	}

	/**
	 * Filters rows with `lte` condition on column `oz_files`.`for_type`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 */
	public function whereForTypeIsLte(string $value): self
	{
		return $this->filterBy(
			Operator::from('lte'),
			\OZONE\OZ\Db\OZFile::COL_FOR_TYPE,
			$value
		);
	}

	/**
	 * Filters rows with `gt` condition on column `oz_files`.`for_type`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 */
	public function whereForTypeIsGt(string $value): self
	{
		return $this->filterBy(
			Operator::from('gt'),
			\OZONE\OZ\Db\OZFile::COL_FOR_TYPE,
			$value
		);
	}

	/**
	 * Filters rows with `gte` condition on column `oz_files`.`for_type`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 */
	public function whereForTypeIsGte(string $value): self
	{
		return $this->filterBy(
			Operator::from('gte'),
			\OZONE\OZ\Db\OZFile::COL_FOR_TYPE,
			$value
		);
	}

	/**
	 * Filters rows with `like` condition on column `oz_files`.`for_type`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 */
	public function whereForTypeIsLike(string $value): self
	{
		return $this->filterBy(
			Operator::from('like'),
			\OZONE\OZ\Db\OZFile::COL_FOR_TYPE,
			$value
		);
	}

	/**
	 * Filters rows with `not_like` condition on column `oz_files`.`for_type`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 */
	public function whereForTypeIsNotLike(string $value): self
	{
		return $this->filterBy(
			Operator::from('not_like'),
			\OZONE\OZ\Db\OZFile::COL_FOR_TYPE,
			$value
		);
	}

	/**
	 * Filters rows with `is_null` condition on column `oz_files`.`for_type`.
	 *
	 * @return static
	 */
	public function whereForTypeIsNull(): self
	{
		return $this->filterBy(
			Operator::from('is_null'),
			\OZONE\OZ\Db\OZFile::COL_FOR_TYPE
		);
	}

	/**
	 * Filters rows with `is_not_null` condition on column `oz_files`.`for_type`.
	 *
	 * @return static
	 */
	public function whereForTypeIsNotNull(): self
	{
		return $this->filterBy(
			Operator::from('is_not_null'),
			\OZONE\OZ\Db\OZFile::COL_FOR_TYPE
		);
	}

	/**
	 * Filters rows with `in` condition on column `oz_files`.`for_type`.
	 *
	 * @param array $value the filter value
	 *
	 * @return static
	 */
	public function whereForTypeIsIn(array $value): self
	{
		return $this->filterBy(
			Operator::from('in'),
			\OZONE\OZ\Db\OZFile::COL_FOR_TYPE,
			$value
		);
	}

	/**
	 * Filters rows with `not_in` condition on column `oz_files`.`for_type`.
	 *
	 * @param array $value the filter value
	 *
	 * @return static
	 */
	public function whereForTypeIsNotIn(array $value): self
	{
		return $this->filterBy(
			Operator::from('not_in'),
			\OZONE\OZ\Db\OZFile::COL_FOR_TYPE,
			$value
		);
	}

	/**
	 * Filters rows with `eq` condition on column `oz_files`.`for_label`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 */
	public function whereForLabelIs(string $value): self
	{
		return $this->filterBy(
			Operator::from('eq'),
			\OZONE\OZ\Db\OZFile::COL_FOR_LABEL,
			$value
		);
	}

	/**
	 * Filters rows with `neq` condition on column `oz_files`.`for_label`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 */
	public function whereForLabelIsNot(string $value): self
	{
		return $this->filterBy(
			Operator::from('neq'),
			\OZONE\OZ\Db\OZFile::COL_FOR_LABEL,
			$value
		);
	}

	/**
	 * Filters rows with `lt` condition on column `oz_files`.`for_label`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 */
	public function whereForLabelIsLt(string $value): self
	{
		return $this->filterBy(
			Operator::from('lt'),
			\OZONE\OZ\Db\OZFile::COL_FOR_LABEL,
			$value
		);
	}

	/**
	 * Filters rows with `lte` condition on column `oz_files`.`for_label`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 */
	public function whereForLabelIsLte(string $value): self
	{
		return $this->filterBy(
			Operator::from('lte'),
			\OZONE\OZ\Db\OZFile::COL_FOR_LABEL,
			$value
		);
	}

	/**
	 * Filters rows with `gt` condition on column `oz_files`.`for_label`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 */
	public function whereForLabelIsGt(string $value): self
	{
		return $this->filterBy(
			Operator::from('gt'),
			\OZONE\OZ\Db\OZFile::COL_FOR_LABEL,
			$value
		);
	}

	/**
	 * Filters rows with `gte` condition on column `oz_files`.`for_label`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 */
	public function whereForLabelIsGte(string $value): self
	{
		return $this->filterBy(
			Operator::from('gte'),
			\OZONE\OZ\Db\OZFile::COL_FOR_LABEL,
			$value
		);
	}

	/**
	 * Filters rows with `like` condition on column `oz_files`.`for_label`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 */
	public function whereForLabelIsLike(string $value): self
	{
		return $this->filterBy(
			Operator::from('like'),
			\OZONE\OZ\Db\OZFile::COL_FOR_LABEL,
			$value
		);
	}

	/**
	 * Filters rows with `not_like` condition on column `oz_files`.`for_label`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 */
	public function whereForLabelIsNotLike(string $value): self
	{
		return $this->filterBy(
			Operator::from('not_like'),
			\OZONE\OZ\Db\OZFile::COL_FOR_LABEL,
			$value
		);
	}

	/**
	 * Filters rows with `in` condition on column `oz_files`.`for_label`.
	 *
	 * @param array $value the filter value
	 *
	 * @return static
	 */
	public function whereForLabelIsIn(array $value): self
	{
		return $this->filterBy(
			Operator::from('in'),
			\OZONE\OZ\Db\OZFile::COL_FOR_LABEL,
			$value
		);
	}

	/**
	 * Filters rows with `not_in` condition on column `oz_files`.`for_label`.
	 *
	 * @param array $value the filter value
	 *
	 * @return static
	 */
	public function whereForLabelIsNotIn(array $value): self
	{
		return $this->filterBy(
			Operator::from('not_in'),
			\OZONE\OZ\Db\OZFile::COL_FOR_LABEL,
			$value
		);
	}

	/**
	 * Filters rows with `eq` condition on column `oz_files`.`data`.
	 *
	 * @param array $value the filter value
	 *
	 * @return static
	 */
	public function whereDataIs(array $value): self
	{
		return $this->filterBy(
			Operator::from('eq'),
			\OZONE\OZ\Db\OZFile::COL_DATA,
			$value
		);
	}

	/**
	 * Filters rows with `neq` condition on column `oz_files`.`data`.
	 *
	 * @param array $value the filter value
	 *
	 * @return static
	 */
	public function whereDataIsNot(array $value): self
	{
		return $this->filterBy(
			Operator::from('neq'),
			\OZONE\OZ\Db\OZFile::COL_DATA,
			$value
		);
	}

	/**
	 * Filters rows with `like` condition on column `oz_files`.`data`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 */
	public function whereDataIsLike(string $value): self
	{
		return $this->filterBy(
			Operator::from('like'),
			\OZONE\OZ\Db\OZFile::COL_DATA,
			$value
		);
	}

	/**
	 * Filters rows with `not_like` condition on column `oz_files`.`data`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 */
	public function whereDataIsNotLike(string $value): self
	{
		return $this->filterBy(
			Operator::from('not_like'),
			\OZONE\OZ\Db\OZFile::COL_DATA,
			$value
		);
	}

	/**
	 * Filters rows with `eq` condition on column `oz_files`.`created_at`.
	 *
	 * @param int|string $value the filter value
	 *
	 * @return static
	 */
	public function whereCreatedAtIs(string|int $value): self
	{
		return $this->filterBy(
			Operator::from('eq'),
			\OZONE\OZ\Db\OZFile::COL_CREATED_AT,
			$value
		);
	}

	/**
	 * Filters rows with `neq` condition on column `oz_files`.`created_at`.
	 *
	 * @param int|string $value the filter value
	 *
	 * @return static
	 */
	public function whereCreatedAtIsNot(string|int $value): self
	{
		return $this->filterBy(
			Operator::from('neq'),
			\OZONE\OZ\Db\OZFile::COL_CREATED_AT,
			$value
		);
	}

	/**
	 * Filters rows with `lt` condition on column `oz_files`.`created_at`.
	 *
	 * @param int|string $value the filter value
	 *
	 * @return static
	 */
	public function whereCreatedAtIsLt(string|int $value): self
	{
		return $this->filterBy(
			Operator::from('lt'),
			\OZONE\OZ\Db\OZFile::COL_CREATED_AT,
			$value
		);
	}

	/**
	 * Filters rows with `lte` condition on column `oz_files`.`created_at`.
	 *
	 * @param int|string $value the filter value
	 *
	 * @return static
	 */
	public function whereCreatedAtIsLte(string|int $value): self
	{
		return $this->filterBy(
			Operator::from('lte'),
			\OZONE\OZ\Db\OZFile::COL_CREATED_AT,
			$value
		);
	}

	/**
	 * Filters rows with `gt` condition on column `oz_files`.`created_at`.
	 *
	 * @param int|string $value the filter value
	 *
	 * @return static
	 */
	public function whereCreatedAtIsGt(string|int $value): self
	{
		return $this->filterBy(
			Operator::from('gt'),
			\OZONE\OZ\Db\OZFile::COL_CREATED_AT,
			$value
		);
	}

	/**
	 * Filters rows with `gte` condition on column `oz_files`.`created_at`.
	 *
	 * @param int|string $value the filter value
	 *
	 * @return static
	 */
	public function whereCreatedAtIsGte(string|int $value): self
	{
		return $this->filterBy(
			Operator::from('gte'),
			\OZONE\OZ\Db\OZFile::COL_CREATED_AT,
			$value
		);
	}

	/**
	 * Filters rows with `like` condition on column `oz_files`.`created_at`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 */
	public function whereCreatedAtIsLike(string $value): self
	{
		return $this->filterBy(
			Operator::from('like'),
			\OZONE\OZ\Db\OZFile::COL_CREATED_AT,
			$value
		);
	}

	/**
	 * Filters rows with `not_like` condition on column `oz_files`.`created_at`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 */
	public function whereCreatedAtIsNotLike(string $value): self
	{
		return $this->filterBy(
			Operator::from('not_like'),
			\OZONE\OZ\Db\OZFile::COL_CREATED_AT,
			$value
		);
	}

	/**
	 * Filters rows with `in` condition on column `oz_files`.`created_at`.
	 *
	 * @param array $value the filter value
	 *
	 * @return static
	 */
	public function whereCreatedAtIsIn(array $value): self
	{
		return $this->filterBy(
			Operator::from('in'),
			\OZONE\OZ\Db\OZFile::COL_CREATED_AT,
			$value
		);
	}

	/**
	 * Filters rows with `not_in` condition on column `oz_files`.`created_at`.
	 *
	 * @param array $value the filter value
	 *
	 * @return static
	 */
	public function whereCreatedAtIsNotIn(array $value): self
	{
		return $this->filterBy(
			Operator::from('not_in'),
			\OZONE\OZ\Db\OZFile::COL_CREATED_AT,
			$value
		);
	}

	/**
	 * Filters rows with `eq` condition on column `oz_files`.`updated_at`.
	 *
	 * @param int|string $value the filter value
	 *
	 * @return static
	 */
	public function whereUpdatedAtIs(string|int $value): self
	{
		return $this->filterBy(
			Operator::from('eq'),
			\OZONE\OZ\Db\OZFile::COL_UPDATED_AT,
			$value
		);
	}

	/**
	 * Filters rows with `neq` condition on column `oz_files`.`updated_at`.
	 *
	 * @param int|string $value the filter value
	 *
	 * @return static
	 */
	public function whereUpdatedAtIsNot(string|int $value): self
	{
		return $this->filterBy(
			Operator::from('neq'),
			\OZONE\OZ\Db\OZFile::COL_UPDATED_AT,
			$value
		);
	}

	/**
	 * Filters rows with `lt` condition on column `oz_files`.`updated_at`.
	 *
	 * @param int|string $value the filter value
	 *
	 * @return static
	 */
	public function whereUpdatedAtIsLt(string|int $value): self
	{
		return $this->filterBy(
			Operator::from('lt'),
			\OZONE\OZ\Db\OZFile::COL_UPDATED_AT,
			$value
		);
	}

	/**
	 * Filters rows with `lte` condition on column `oz_files`.`updated_at`.
	 *
	 * @param int|string $value the filter value
	 *
	 * @return static
	 */
	public function whereUpdatedAtIsLte(string|int $value): self
	{
		return $this->filterBy(
			Operator::from('lte'),
			\OZONE\OZ\Db\OZFile::COL_UPDATED_AT,
			$value
		);
	}

	/**
	 * Filters rows with `gt` condition on column `oz_files`.`updated_at`.
	 *
	 * @param int|string $value the filter value
	 *
	 * @return static
	 */
	public function whereUpdatedAtIsGt(string|int $value): self
	{
		return $this->filterBy(
			Operator::from('gt'),
			\OZONE\OZ\Db\OZFile::COL_UPDATED_AT,
			$value
		);
	}

	/**
	 * Filters rows with `gte` condition on column `oz_files`.`updated_at`.
	 *
	 * @param int|string $value the filter value
	 *
	 * @return static
	 */
	public function whereUpdatedAtIsGte(string|int $value): self
	{
		return $this->filterBy(
			Operator::from('gte'),
			\OZONE\OZ\Db\OZFile::COL_UPDATED_AT,
			$value
		);
	}

	/**
	 * Filters rows with `like` condition on column `oz_files`.`updated_at`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 */
	public function whereUpdatedAtIsLike(string $value): self
	{
		return $this->filterBy(
			Operator::from('like'),
			\OZONE\OZ\Db\OZFile::COL_UPDATED_AT,
			$value
		);
	}

	/**
	 * Filters rows with `not_like` condition on column `oz_files`.`updated_at`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 */
	public function whereUpdatedAtIsNotLike(string $value): self
	{
		return $this->filterBy(
			Operator::from('not_like'),
			\OZONE\OZ\Db\OZFile::COL_UPDATED_AT,
			$value
		);
	}

	/**
	 * Filters rows with `in` condition on column `oz_files`.`updated_at`.
	 *
	 * @param array $value the filter value
	 *
	 * @return static
	 */
	public function whereUpdatedAtIsIn(array $value): self
	{
		return $this->filterBy(
			Operator::from('in'),
			\OZONE\OZ\Db\OZFile::COL_UPDATED_AT,
			$value
		);
	}

	/**
	 * Filters rows with `not_in` condition on column `oz_files`.`updated_at`.
	 *
	 * @param array $value the filter value
	 *
	 * @return static
	 */
	public function whereUpdatedAtIsNotIn(array $value): self
	{
		return $this->filterBy(
			Operator::from('not_in'),
			\OZONE\OZ\Db\OZFile::COL_UPDATED_AT,
			$value
		);
	}

	/**
	 * Filters rows with `eq` condition on column `oz_files`.`is_valid`.
	 *
	 * @param bool $value the filter value
	 *
	 * @return static
	 */
	public function whereIsValidIs(bool $value): self
	{
		return $this->filterBy(
			Operator::from('eq'),
			\OZONE\OZ\Db\OZFile::COL_IS_VALID,
			$value
		);
	}

	/**
	 * Filters rows with `neq` condition on column `oz_files`.`is_valid`.
	 *
	 * @param bool $value the filter value
	 *
	 * @return static
	 */
	public function whereIsValidIsNot(bool $value): self
	{
		return $this->filterBy(
			Operator::from('neq'),
			\OZONE\OZ\Db\OZFile::COL_IS_VALID,
			$value
		);
	}

	/**
	 * Filters rows with `is_false` condition on column `oz_files`.`is_valid`.
	 *
	 * @return static
	 */
	public function whereIsNotValid(): self
	{
		return $this->filterBy(
			Operator::from('is_false'),
			\OZONE\OZ\Db\OZFile::COL_IS_VALID
		);
	}

	/**
	 * Filters rows with `is_true` condition on column `oz_files`.`is_valid`.
	 *
	 * @return static
	 */
	public function whereIsValid(): self
	{
		return $this->filterBy(
			Operator::from('is_true'),
			\OZONE\OZ\Db\OZFile::COL_IS_VALID
		);
	}
}
