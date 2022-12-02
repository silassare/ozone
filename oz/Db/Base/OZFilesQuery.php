<?php
/**
 * Auto generated file
 * 
 * WARNING: please don't edit.
 * 
 * Proudly With: gobl v1.5.0
 * Time: 2022-11-30T17:07:12+00:00
 */
declare(strict_types=1);

namespace OZONE\OZ\Db\Base;

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
	 * @inheritDoc
	 * 
	 * @return static
	 */
	public static function createInstance(): static
	{
		return new \OZONE\OZ\Db\OZFilesQuery;
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
	 * @param string|int $value the filter value
	 * 
	 * @return static
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereIdIs(string|int $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('eq'),
			\OZONE\OZ\Db\OZFile::COL_ID,
			$value
		);
	}

	/**
	 * Filters rows with `neq` condition on column `oz_files`.`id`.
	 * 
	 * @param string|int $value the filter value
	 * 
	 * @return static
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereIdIsNot(string|int $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('neq'),
			\OZONE\OZ\Db\OZFile::COL_ID,
			$value
		);
	}

	/**
	 * Filters rows with `lt` condition on column `oz_files`.`id`.
	 * 
	 * @param string|int $value the filter value
	 * 
	 * @return static
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereIdIsLt(string|int $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('lt'),
			\OZONE\OZ\Db\OZFile::COL_ID,
			$value
		);
	}

	/**
	 * Filters rows with `lte` condition on column `oz_files`.`id`.
	 * 
	 * @param string|int $value the filter value
	 * 
	 * @return static
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereIdIsLte(string|int $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('lte'),
			\OZONE\OZ\Db\OZFile::COL_ID,
			$value
		);
	}

	/**
	 * Filters rows with `gt` condition on column `oz_files`.`id`.
	 * 
	 * @param string|int $value the filter value
	 * 
	 * @return static
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereIdIsGt(string|int $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('gt'),
			\OZONE\OZ\Db\OZFile::COL_ID,
			$value
		);
	}

	/**
	 * Filters rows with `gte` condition on column `oz_files`.`id`.
	 * 
	 * @param string|int $value the filter value
	 * 
	 * @return static
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereIdIsGte(string|int $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('gte'),
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
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereIdIsLike(string $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('like'),
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
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereIdIsNotLike(string $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('not_like'),
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
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereIdIsIn(array $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('in'),
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
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereIdIsNotIn(array $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('not_in'),
			\OZONE\OZ\Db\OZFile::COL_ID,
			$value
		);
	}

	/**
	 * Filters rows with `eq` condition on column `oz_files`.`user_id`.
	 * 
	 * @param string|int $value the filter value
	 * 
	 * @return static
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereUserIdIs(string|int $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('eq'),
			\OZONE\OZ\Db\OZFile::COL_USER_ID,
			$value
		);
	}

	/**
	 * Filters rows with `neq` condition on column `oz_files`.`user_id`.
	 * 
	 * @param string|int $value the filter value
	 * 
	 * @return static
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereUserIdIsNot(string|int $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('neq'),
			\OZONE\OZ\Db\OZFile::COL_USER_ID,
			$value
		);
	}

	/**
	 * Filters rows with `lt` condition on column `oz_files`.`user_id`.
	 * 
	 * @param string|int $value the filter value
	 * 
	 * @return static
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereUserIdIsLt(string|int $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('lt'),
			\OZONE\OZ\Db\OZFile::COL_USER_ID,
			$value
		);
	}

	/**
	 * Filters rows with `lte` condition on column `oz_files`.`user_id`.
	 * 
	 * @param string|int $value the filter value
	 * 
	 * @return static
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereUserIdIsLte(string|int $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('lte'),
			\OZONE\OZ\Db\OZFile::COL_USER_ID,
			$value
		);
	}

	/**
	 * Filters rows with `gt` condition on column `oz_files`.`user_id`.
	 * 
	 * @param string|int $value the filter value
	 * 
	 * @return static
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereUserIdIsGt(string|int $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('gt'),
			\OZONE\OZ\Db\OZFile::COL_USER_ID,
			$value
		);
	}

	/**
	 * Filters rows with `gte` condition on column `oz_files`.`user_id`.
	 * 
	 * @param string|int $value the filter value
	 * 
	 * @return static
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereUserIdIsGte(string|int $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('gte'),
			\OZONE\OZ\Db\OZFile::COL_USER_ID,
			$value
		);
	}

	/**
	 * Filters rows with `like` condition on column `oz_files`.`user_id`.
	 * 
	 * @param string $value the filter value
	 * 
	 * @return static
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereUserIdIsLike(string $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('like'),
			\OZONE\OZ\Db\OZFile::COL_USER_ID,
			$value
		);
	}

	/**
	 * Filters rows with `not_like` condition on column `oz_files`.`user_id`.
	 * 
	 * @param string $value the filter value
	 * 
	 * @return static
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereUserIdIsNotLike(string $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('not_like'),
			\OZONE\OZ\Db\OZFile::COL_USER_ID,
			$value
		);
	}

	/**
	 * Filters rows with `is_null` condition on column `oz_files`.`user_id`.
	 * 
	 * @return static
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereUserIdIsNull(): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('is_null'),
			\OZONE\OZ\Db\OZFile::COL_USER_ID
		);
	}

	/**
	 * Filters rows with `is_not_null` condition on column `oz_files`.`user_id`.
	 * 
	 * @return static
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereUserIdIsNotNull(): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('is_not_null'),
			\OZONE\OZ\Db\OZFile::COL_USER_ID
		);
	}

	/**
	 * Filters rows with `in` condition on column `oz_files`.`user_id`.
	 * 
	 * @param array $value the filter value
	 * 
	 * @return static
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereUserIdIsIn(array $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('in'),
			\OZONE\OZ\Db\OZFile::COL_USER_ID,
			$value
		);
	}

	/**
	 * Filters rows with `not_in` condition on column `oz_files`.`user_id`.
	 * 
	 * @param array $value the filter value
	 * 
	 * @return static
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereUserIdIsNotIn(array $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('not_in'),
			\OZONE\OZ\Db\OZFile::COL_USER_ID,
			$value
		);
	}

	/**
	 * Filters rows with `eq` condition on column `oz_files`.`key`.
	 * 
	 * @param string $value the filter value
	 * 
	 * @return static
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereKeyIs(string $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('eq'),
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
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereKeyIsNot(string $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('neq'),
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
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereKeyIsLt(string $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('lt'),
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
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereKeyIsLte(string $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('lte'),
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
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereKeyIsGt(string $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('gt'),
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
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereKeyIsGte(string $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('gte'),
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
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereKeyIsLike(string $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('like'),
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
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereKeyIsNotLike(string $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('not_like'),
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
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereKeyIsIn(array $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('in'),
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
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereKeyIsNotIn(array $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('not_in'),
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
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereRefIs(string $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('eq'),
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
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereRefIsNot(string $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('neq'),
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
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereRefIsLt(string $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('lt'),
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
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereRefIsLte(string $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('lte'),
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
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereRefIsGt(string $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('gt'),
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
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereRefIsGte(string $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('gte'),
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
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereRefIsLike(string $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('like'),
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
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereRefIsNotLike(string $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('not_like'),
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
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereRefIsIn(array $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('in'),
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
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereRefIsNotIn(array $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('not_in'),
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
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereDriverIs(string $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('eq'),
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
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereDriverIsNot(string $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('neq'),
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
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereDriverIsLt(string $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('lt'),
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
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereDriverIsLte(string $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('lte'),
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
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereDriverIsGt(string $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('gt'),
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
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereDriverIsGte(string $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('gte'),
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
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereDriverIsLike(string $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('like'),
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
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereDriverIsNotLike(string $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('not_like'),
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
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereDriverIsIn(array $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('in'),
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
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereDriverIsNotIn(array $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('not_in'),
			\OZONE\OZ\Db\OZFile::COL_DRIVER,
			$value
		);
	}

	/**
	 * Filters rows with `eq` condition on column `oz_files`.`clone_id`.
	 * 
	 * @param string|int $value the filter value
	 * 
	 * @return static
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereCloneIdIs(string|int $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('eq'),
			\OZONE\OZ\Db\OZFile::COL_CLONE_ID,
			$value
		);
	}

	/**
	 * Filters rows with `neq` condition on column `oz_files`.`clone_id`.
	 * 
	 * @param string|int $value the filter value
	 * 
	 * @return static
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereCloneIdIsNot(string|int $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('neq'),
			\OZONE\OZ\Db\OZFile::COL_CLONE_ID,
			$value
		);
	}

	/**
	 * Filters rows with `lt` condition on column `oz_files`.`clone_id`.
	 * 
	 * @param string|int $value the filter value
	 * 
	 * @return static
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereCloneIdIsLt(string|int $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('lt'),
			\OZONE\OZ\Db\OZFile::COL_CLONE_ID,
			$value
		);
	}

	/**
	 * Filters rows with `lte` condition on column `oz_files`.`clone_id`.
	 * 
	 * @param string|int $value the filter value
	 * 
	 * @return static
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereCloneIdIsLte(string|int $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('lte'),
			\OZONE\OZ\Db\OZFile::COL_CLONE_ID,
			$value
		);
	}

	/**
	 * Filters rows with `gt` condition on column `oz_files`.`clone_id`.
	 * 
	 * @param string|int $value the filter value
	 * 
	 * @return static
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereCloneIdIsGt(string|int $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('gt'),
			\OZONE\OZ\Db\OZFile::COL_CLONE_ID,
			$value
		);
	}

	/**
	 * Filters rows with `gte` condition on column `oz_files`.`clone_id`.
	 * 
	 * @param string|int $value the filter value
	 * 
	 * @return static
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereCloneIdIsGte(string|int $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('gte'),
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
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereCloneIdIsLike(string $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('like'),
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
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereCloneIdIsNotLike(string $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('not_like'),
			\OZONE\OZ\Db\OZFile::COL_CLONE_ID,
			$value
		);
	}

	/**
	 * Filters rows with `is_null` condition on column `oz_files`.`clone_id`.
	 * 
	 * @return static
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereCloneIdIsNull(): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('is_null'),
			\OZONE\OZ\Db\OZFile::COL_CLONE_ID
		);
	}

	/**
	 * Filters rows with `is_not_null` condition on column `oz_files`.`clone_id`.
	 * 
	 * @return static
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereCloneIdIsNotNull(): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('is_not_null'),
			\OZONE\OZ\Db\OZFile::COL_CLONE_ID
		);
	}

	/**
	 * Filters rows with `in` condition on column `oz_files`.`clone_id`.
	 * 
	 * @param array $value the filter value
	 * 
	 * @return static
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereCloneIdIsIn(array $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('in'),
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
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereCloneIdIsNotIn(array $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('not_in'),
			\OZONE\OZ\Db\OZFile::COL_CLONE_ID,
			$value
		);
	}

	/**
	 * Filters rows with `eq` condition on column `oz_files`.`source_id`.
	 * 
	 * @param string|int $value the filter value
	 * 
	 * @return static
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereSourceIdIs(string|int $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('eq'),
			\OZONE\OZ\Db\OZFile::COL_SOURCE_ID,
			$value
		);
	}

	/**
	 * Filters rows with `neq` condition on column `oz_files`.`source_id`.
	 * 
	 * @param string|int $value the filter value
	 * 
	 * @return static
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereSourceIdIsNot(string|int $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('neq'),
			\OZONE\OZ\Db\OZFile::COL_SOURCE_ID,
			$value
		);
	}

	/**
	 * Filters rows with `lt` condition on column `oz_files`.`source_id`.
	 * 
	 * @param string|int $value the filter value
	 * 
	 * @return static
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereSourceIdIsLt(string|int $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('lt'),
			\OZONE\OZ\Db\OZFile::COL_SOURCE_ID,
			$value
		);
	}

	/**
	 * Filters rows with `lte` condition on column `oz_files`.`source_id`.
	 * 
	 * @param string|int $value the filter value
	 * 
	 * @return static
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereSourceIdIsLte(string|int $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('lte'),
			\OZONE\OZ\Db\OZFile::COL_SOURCE_ID,
			$value
		);
	}

	/**
	 * Filters rows with `gt` condition on column `oz_files`.`source_id`.
	 * 
	 * @param string|int $value the filter value
	 * 
	 * @return static
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereSourceIdIsGt(string|int $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('gt'),
			\OZONE\OZ\Db\OZFile::COL_SOURCE_ID,
			$value
		);
	}

	/**
	 * Filters rows with `gte` condition on column `oz_files`.`source_id`.
	 * 
	 * @param string|int $value the filter value
	 * 
	 * @return static
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereSourceIdIsGte(string|int $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('gte'),
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
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereSourceIdIsLike(string $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('like'),
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
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereSourceIdIsNotLike(string $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('not_like'),
			\OZONE\OZ\Db\OZFile::COL_SOURCE_ID,
			$value
		);
	}

	/**
	 * Filters rows with `is_null` condition on column `oz_files`.`source_id`.
	 * 
	 * @return static
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereSourceIdIsNull(): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('is_null'),
			\OZONE\OZ\Db\OZFile::COL_SOURCE_ID
		);
	}

	/**
	 * Filters rows with `is_not_null` condition on column `oz_files`.`source_id`.
	 * 
	 * @return static
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereSourceIdIsNotNull(): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('is_not_null'),
			\OZONE\OZ\Db\OZFile::COL_SOURCE_ID
		);
	}

	/**
	 * Filters rows with `in` condition on column `oz_files`.`source_id`.
	 * 
	 * @param array $value the filter value
	 * 
	 * @return static
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereSourceIdIsIn(array $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('in'),
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
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereSourceIdIsNotIn(array $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('not_in'),
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
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereSizeIs(int $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('eq'),
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
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereSizeIsNot(int $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('neq'),
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
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereSizeIsLt(int $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('lt'),
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
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereSizeIsLte(int $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('lte'),
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
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereSizeIsGt(int $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('gt'),
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
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereSizeIsGte(int $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('gte'),
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
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereSizeIsLike(string $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('like'),
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
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereSizeIsNotLike(string $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('not_like'),
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
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereSizeIsIn(array $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('in'),
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
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereSizeIsNotIn(array $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('not_in'),
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
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereMimeTypeIs(string $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('eq'),
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
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereMimeTypeIsNot(string $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('neq'),
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
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereMimeTypeIsLt(string $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('lt'),
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
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereMimeTypeIsLte(string $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('lte'),
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
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereMimeTypeIsGt(string $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('gt'),
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
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereMimeTypeIsGte(string $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('gte'),
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
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereMimeTypeIsLike(string $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('like'),
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
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereMimeTypeIsNotLike(string $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('not_like'),
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
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereMimeTypeIsIn(array $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('in'),
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
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereMimeTypeIsNotIn(array $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('not_in'),
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
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereExtensionIs(string $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('eq'),
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
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereExtensionIsNot(string $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('neq'),
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
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereExtensionIsLt(string $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('lt'),
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
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereExtensionIsLte(string $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('lte'),
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
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereExtensionIsGt(string $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('gt'),
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
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereExtensionIsGte(string $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('gte'),
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
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereExtensionIsLike(string $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('like'),
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
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereExtensionIsNotLike(string $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('not_like'),
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
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereExtensionIsIn(array $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('in'),
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
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereExtensionIsNotIn(array $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('not_in'),
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
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereNameIs(string $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('eq'),
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
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereNameIsNot(string $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('neq'),
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
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereNameIsLt(string $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('lt'),
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
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereNameIsLte(string $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('lte'),
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
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereNameIsGt(string $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('gt'),
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
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereNameIsGte(string $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('gte'),
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
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereNameIsLike(string $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('like'),
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
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereNameIsNotLike(string $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('not_like'),
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
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereNameIsIn(array $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('in'),
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
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereNameIsNotIn(array $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('not_in'),
			\OZONE\OZ\Db\OZFile::COL_NAME,
			$value
		);
	}

	/**
	 * Filters rows with `eq` condition on column `oz_files`.`label`.
	 * 
	 * @param string $value the filter value
	 * 
	 * @return static
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereLabelIs(string $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('eq'),
			\OZONE\OZ\Db\OZFile::COL_LABEL,
			$value
		);
	}

	/**
	 * Filters rows with `neq` condition on column `oz_files`.`label`.
	 * 
	 * @param string $value the filter value
	 * 
	 * @return static
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereLabelIsNot(string $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('neq'),
			\OZONE\OZ\Db\OZFile::COL_LABEL,
			$value
		);
	}

	/**
	 * Filters rows with `lt` condition on column `oz_files`.`label`.
	 * 
	 * @param string $value the filter value
	 * 
	 * @return static
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereLabelIsLt(string $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('lt'),
			\OZONE\OZ\Db\OZFile::COL_LABEL,
			$value
		);
	}

	/**
	 * Filters rows with `lte` condition on column `oz_files`.`label`.
	 * 
	 * @param string $value the filter value
	 * 
	 * @return static
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereLabelIsLte(string $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('lte'),
			\OZONE\OZ\Db\OZFile::COL_LABEL,
			$value
		);
	}

	/**
	 * Filters rows with `gt` condition on column `oz_files`.`label`.
	 * 
	 * @param string $value the filter value
	 * 
	 * @return static
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereLabelIsGt(string $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('gt'),
			\OZONE\OZ\Db\OZFile::COL_LABEL,
			$value
		);
	}

	/**
	 * Filters rows with `gte` condition on column `oz_files`.`label`.
	 * 
	 * @param string $value the filter value
	 * 
	 * @return static
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereLabelIsGte(string $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('gte'),
			\OZONE\OZ\Db\OZFile::COL_LABEL,
			$value
		);
	}

	/**
	 * Filters rows with `like` condition on column `oz_files`.`label`.
	 * 
	 * @param string $value the filter value
	 * 
	 * @return static
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereLabelIsLike(string $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('like'),
			\OZONE\OZ\Db\OZFile::COL_LABEL,
			$value
		);
	}

	/**
	 * Filters rows with `not_like` condition on column `oz_files`.`label`.
	 * 
	 * @param string $value the filter value
	 * 
	 * @return static
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereLabelIsNotLike(string $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('not_like'),
			\OZONE\OZ\Db\OZFile::COL_LABEL,
			$value
		);
	}

	/**
	 * Filters rows with `in` condition on column `oz_files`.`label`.
	 * 
	 * @param array $value the filter value
	 * 
	 * @return static
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereLabelIsIn(array $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('in'),
			\OZONE\OZ\Db\OZFile::COL_LABEL,
			$value
		);
	}

	/**
	 * Filters rows with `not_in` condition on column `oz_files`.`label`.
	 * 
	 * @param array $value the filter value
	 * 
	 * @return static
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereLabelIsNotIn(array $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('not_in'),
			\OZONE\OZ\Db\OZFile::COL_LABEL,
			$value
		);
	}

	/**
	 * Filters rows with `eq` condition on column `oz_files`.`data`.
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
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereDataIsNot(array $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('neq'),
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
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereDataIsLike(string $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('like'),
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
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereDataIsNotLike(string $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('not_like'),
			\OZONE\OZ\Db\OZFile::COL_DATA,
			$value
		);
	}

	/**
	 * Filters rows with `eq` condition on column `oz_files`.`created_at`.
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
			\OZONE\OZ\Db\OZFile::COL_CREATED_AT,
			$value
		);
	}

	/**
	 * Filters rows with `neq` condition on column `oz_files`.`created_at`.
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
			\OZONE\OZ\Db\OZFile::COL_CREATED_AT,
			$value
		);
	}

	/**
	 * Filters rows with `lt` condition on column `oz_files`.`created_at`.
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
			\OZONE\OZ\Db\OZFile::COL_CREATED_AT,
			$value
		);
	}

	/**
	 * Filters rows with `lte` condition on column `oz_files`.`created_at`.
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
			\OZONE\OZ\Db\OZFile::COL_CREATED_AT,
			$value
		);
	}

	/**
	 * Filters rows with `gt` condition on column `oz_files`.`created_at`.
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
			\OZONE\OZ\Db\OZFile::COL_CREATED_AT,
			$value
		);
	}

	/**
	 * Filters rows with `gte` condition on column `oz_files`.`created_at`.
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
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereCreatedAtIsLike(string $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('like'),
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
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereCreatedAtIsNotLike(string $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('not_like'),
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
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereCreatedAtIsIn(array $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('in'),
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
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereCreatedAtIsNotIn(array $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('not_in'),
			\OZONE\OZ\Db\OZFile::COL_CREATED_AT,
			$value
		);
	}

	/**
	 * Filters rows with `eq` condition on column `oz_files`.`updated_at`.
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
			\OZONE\OZ\Db\OZFile::COL_UPDATED_AT,
			$value
		);
	}

	/**
	 * Filters rows with `neq` condition on column `oz_files`.`updated_at`.
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
			\OZONE\OZ\Db\OZFile::COL_UPDATED_AT,
			$value
		);
	}

	/**
	 * Filters rows with `lt` condition on column `oz_files`.`updated_at`.
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
			\OZONE\OZ\Db\OZFile::COL_UPDATED_AT,
			$value
		);
	}

	/**
	 * Filters rows with `lte` condition on column `oz_files`.`updated_at`.
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
			\OZONE\OZ\Db\OZFile::COL_UPDATED_AT,
			$value
		);
	}

	/**
	 * Filters rows with `gt` condition on column `oz_files`.`updated_at`.
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
			\OZONE\OZ\Db\OZFile::COL_UPDATED_AT,
			$value
		);
	}

	/**
	 * Filters rows with `gte` condition on column `oz_files`.`updated_at`.
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
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereUpdatedAtIsLike(string $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('like'),
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
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereUpdatedAtIsNotLike(string $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('not_like'),
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
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereUpdatedAtIsIn(array $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('in'),
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
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereUpdatedAtIsNotIn(array $value): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('not_in'),
			\OZONE\OZ\Db\OZFile::COL_UPDATED_AT,
			$value
		);
	}

	/**
	 * Filters rows with `eq` condition on column `oz_files`.`valid`.
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
			\OZONE\OZ\Db\OZFile::COL_VALID,
			$value
		);
	}

	/**
	 * Filters rows with `neq` condition on column `oz_files`.`valid`.
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
			\OZONE\OZ\Db\OZFile::COL_VALID,
			$value
		);
	}

	/**
	 * Filters rows with `is_false` condition on column `oz_files`.`valid`.
	 * 
	 * @return static
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereValidIsFalse(): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('is_false'),
			\OZONE\OZ\Db\OZFile::COL_VALID
		);
	}

	/**
	 * Filters rows with `is_true` condition on column `oz_files`.`valid`.
	 * 
	 * @return static
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function whereValidIsTrue(): self
	{
		return $this->filterBy(
			\Gobl\DBAL\Operator::from('is_true'),
			\OZONE\OZ\Db\OZFile::COL_VALID
		);
	}
}
