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
 * Class OZAuthsQuery.
 *
 * @method \OZONE\Core\Db\OZAuthsResults find(?int $max = null, int $offset = 0, array $order_by = [])
 */
abstract class OZAuthsQuery extends \Gobl\ORM\ORMTableQuery
{
	/**
	 * OZAuthsQuery constructor.
	 */
	public function __construct()
	{
		parent::__construct(
			\OZONE\Core\Db\OZAuth::TABLE_NAMESPACE,
			\OZONE\Core\Db\OZAuth::TABLE_NAME
		);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @return static
	 */
	public static function createInstance(): static
	{
		return new \OZONE\Core\Db\OZAuthsQuery();
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
	 * Filters rows with `eq` condition on column `oz_auths`.`ref`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 */
	public function whereRefIs(string $value): self
	{
		return $this->filterBy(
			Operator::from('eq'),
			\OZONE\Core\Db\OZAuth::COL_REF,
			$value
		);
	}

	/**
	 * Filters rows with `neq` condition on column `oz_auths`.`ref`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 */
	public function whereRefIsNot(string $value): self
	{
		return $this->filterBy(
			Operator::from('neq'),
			\OZONE\Core\Db\OZAuth::COL_REF,
			$value
		);
	}

	/**
	 * Filters rows with `lt` condition on column `oz_auths`.`ref`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 */
	public function whereRefIsLt(string $value): self
	{
		return $this->filterBy(
			Operator::from('lt'),
			\OZONE\Core\Db\OZAuth::COL_REF,
			$value
		);
	}

	/**
	 * Filters rows with `lte` condition on column `oz_auths`.`ref`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 */
	public function whereRefIsLte(string $value): self
	{
		return $this->filterBy(
			Operator::from('lte'),
			\OZONE\Core\Db\OZAuth::COL_REF,
			$value
		);
	}

	/**
	 * Filters rows with `gt` condition on column `oz_auths`.`ref`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 */
	public function whereRefIsGt(string $value): self
	{
		return $this->filterBy(
			Operator::from('gt'),
			\OZONE\Core\Db\OZAuth::COL_REF,
			$value
		);
	}

	/**
	 * Filters rows with `gte` condition on column `oz_auths`.`ref`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 */
	public function whereRefIsGte(string $value): self
	{
		return $this->filterBy(
			Operator::from('gte'),
			\OZONE\Core\Db\OZAuth::COL_REF,
			$value
		);
	}

	/**
	 * Filters rows with `like` condition on column `oz_auths`.`ref`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 */
	public function whereRefIsLike(string $value): self
	{
		return $this->filterBy(
			Operator::from('like'),
			\OZONE\Core\Db\OZAuth::COL_REF,
			$value
		);
	}

	/**
	 * Filters rows with `not_like` condition on column `oz_auths`.`ref`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 */
	public function whereRefIsNotLike(string $value): self
	{
		return $this->filterBy(
			Operator::from('not_like'),
			\OZONE\Core\Db\OZAuth::COL_REF,
			$value
		);
	}

	/**
	 * Filters rows with `in` condition on column `oz_auths`.`ref`.
	 *
	 * @param array $value the filter value
	 *
	 * @return static
	 */
	public function whereRefIsIn(array $value): self
	{
		return $this->filterBy(
			Operator::from('in'),
			\OZONE\Core\Db\OZAuth::COL_REF,
			$value
		);
	}

	/**
	 * Filters rows with `not_in` condition on column `oz_auths`.`ref`.
	 *
	 * @param array $value the filter value
	 *
	 * @return static
	 */
	public function whereRefIsNotIn(array $value): self
	{
		return $this->filterBy(
			Operator::from('not_in'),
			\OZONE\Core\Db\OZAuth::COL_REF,
			$value
		);
	}

	/**
	 * Filters rows with `eq` condition on column `oz_auths`.`label`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 */
	public function whereLabelIs(string $value): self
	{
		return $this->filterBy(
			Operator::from('eq'),
			\OZONE\Core\Db\OZAuth::COL_LABEL,
			$value
		);
	}

	/**
	 * Filters rows with `neq` condition on column `oz_auths`.`label`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 */
	public function whereLabelIsNot(string $value): self
	{
		return $this->filterBy(
			Operator::from('neq'),
			\OZONE\Core\Db\OZAuth::COL_LABEL,
			$value
		);
	}

	/**
	 * Filters rows with `lt` condition on column `oz_auths`.`label`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 */
	public function whereLabelIsLt(string $value): self
	{
		return $this->filterBy(
			Operator::from('lt'),
			\OZONE\Core\Db\OZAuth::COL_LABEL,
			$value
		);
	}

	/**
	 * Filters rows with `lte` condition on column `oz_auths`.`label`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 */
	public function whereLabelIsLte(string $value): self
	{
		return $this->filterBy(
			Operator::from('lte'),
			\OZONE\Core\Db\OZAuth::COL_LABEL,
			$value
		);
	}

	/**
	 * Filters rows with `gt` condition on column `oz_auths`.`label`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 */
	public function whereLabelIsGt(string $value): self
	{
		return $this->filterBy(
			Operator::from('gt'),
			\OZONE\Core\Db\OZAuth::COL_LABEL,
			$value
		);
	}

	/**
	 * Filters rows with `gte` condition on column `oz_auths`.`label`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 */
	public function whereLabelIsGte(string $value): self
	{
		return $this->filterBy(
			Operator::from('gte'),
			\OZONE\Core\Db\OZAuth::COL_LABEL,
			$value
		);
	}

	/**
	 * Filters rows with `like` condition on column `oz_auths`.`label`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 */
	public function whereLabelIsLike(string $value): self
	{
		return $this->filterBy(
			Operator::from('like'),
			\OZONE\Core\Db\OZAuth::COL_LABEL,
			$value
		);
	}

	/**
	 * Filters rows with `not_like` condition on column `oz_auths`.`label`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 */
	public function whereLabelIsNotLike(string $value): self
	{
		return $this->filterBy(
			Operator::from('not_like'),
			\OZONE\Core\Db\OZAuth::COL_LABEL,
			$value
		);
	}

	/**
	 * Filters rows with `in` condition on column `oz_auths`.`label`.
	 *
	 * @param array $value the filter value
	 *
	 * @return static
	 */
	public function whereLabelIsIn(array $value): self
	{
		return $this->filterBy(
			Operator::from('in'),
			\OZONE\Core\Db\OZAuth::COL_LABEL,
			$value
		);
	}

	/**
	 * Filters rows with `not_in` condition on column `oz_auths`.`label`.
	 *
	 * @param array $value the filter value
	 *
	 * @return static
	 */
	public function whereLabelIsNotIn(array $value): self
	{
		return $this->filterBy(
			Operator::from('not_in'),
			\OZONE\Core\Db\OZAuth::COL_LABEL,
			$value
		);
	}

	/**
	 * Filters rows with `eq` condition on column `oz_auths`.`refresh_key`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 */
	public function whereRefreshKeyIs(string $value): self
	{
		return $this->filterBy(
			Operator::from('eq'),
			\OZONE\Core\Db\OZAuth::COL_REFRESH_KEY,
			$value
		);
	}

	/**
	 * Filters rows with `neq` condition on column `oz_auths`.`refresh_key`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 */
	public function whereRefreshKeyIsNot(string $value): self
	{
		return $this->filterBy(
			Operator::from('neq'),
			\OZONE\Core\Db\OZAuth::COL_REFRESH_KEY,
			$value
		);
	}

	/**
	 * Filters rows with `lt` condition on column `oz_auths`.`refresh_key`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 */
	public function whereRefreshKeyIsLt(string $value): self
	{
		return $this->filterBy(
			Operator::from('lt'),
			\OZONE\Core\Db\OZAuth::COL_REFRESH_KEY,
			$value
		);
	}

	/**
	 * Filters rows with `lte` condition on column `oz_auths`.`refresh_key`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 */
	public function whereRefreshKeyIsLte(string $value): self
	{
		return $this->filterBy(
			Operator::from('lte'),
			\OZONE\Core\Db\OZAuth::COL_REFRESH_KEY,
			$value
		);
	}

	/**
	 * Filters rows with `gt` condition on column `oz_auths`.`refresh_key`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 */
	public function whereRefreshKeyIsGt(string $value): self
	{
		return $this->filterBy(
			Operator::from('gt'),
			\OZONE\Core\Db\OZAuth::COL_REFRESH_KEY,
			$value
		);
	}

	/**
	 * Filters rows with `gte` condition on column `oz_auths`.`refresh_key`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 */
	public function whereRefreshKeyIsGte(string $value): self
	{
		return $this->filterBy(
			Operator::from('gte'),
			\OZONE\Core\Db\OZAuth::COL_REFRESH_KEY,
			$value
		);
	}

	/**
	 * Filters rows with `like` condition on column `oz_auths`.`refresh_key`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 */
	public function whereRefreshKeyIsLike(string $value): self
	{
		return $this->filterBy(
			Operator::from('like'),
			\OZONE\Core\Db\OZAuth::COL_REFRESH_KEY,
			$value
		);
	}

	/**
	 * Filters rows with `not_like` condition on column `oz_auths`.`refresh_key`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 */
	public function whereRefreshKeyIsNotLike(string $value): self
	{
		return $this->filterBy(
			Operator::from('not_like'),
			\OZONE\Core\Db\OZAuth::COL_REFRESH_KEY,
			$value
		);
	}

	/**
	 * Filters rows with `in` condition on column `oz_auths`.`refresh_key`.
	 *
	 * @param array $value the filter value
	 *
	 * @return static
	 */
	public function whereRefreshKeyIsIn(array $value): self
	{
		return $this->filterBy(
			Operator::from('in'),
			\OZONE\Core\Db\OZAuth::COL_REFRESH_KEY,
			$value
		);
	}

	/**
	 * Filters rows with `not_in` condition on column `oz_auths`.`refresh_key`.
	 *
	 * @param array $value the filter value
	 *
	 * @return static
	 */
	public function whereRefreshKeyIsNotIn(array $value): self
	{
		return $this->filterBy(
			Operator::from('not_in'),
			\OZONE\Core\Db\OZAuth::COL_REFRESH_KEY,
			$value
		);
	}

	/**
	 * Filters rows with `eq` condition on column `oz_auths`.`provider`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 */
	public function whereProviderIs(string $value): self
	{
		return $this->filterBy(
			Operator::from('eq'),
			\OZONE\Core\Db\OZAuth::COL_PROVIDER,
			$value
		);
	}

	/**
	 * Filters rows with `neq` condition on column `oz_auths`.`provider`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 */
	public function whereProviderIsNot(string $value): self
	{
		return $this->filterBy(
			Operator::from('neq'),
			\OZONE\Core\Db\OZAuth::COL_PROVIDER,
			$value
		);
	}

	/**
	 * Filters rows with `lt` condition on column `oz_auths`.`provider`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 */
	public function whereProviderIsLt(string $value): self
	{
		return $this->filterBy(
			Operator::from('lt'),
			\OZONE\Core\Db\OZAuth::COL_PROVIDER,
			$value
		);
	}

	/**
	 * Filters rows with `lte` condition on column `oz_auths`.`provider`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 */
	public function whereProviderIsLte(string $value): self
	{
		return $this->filterBy(
			Operator::from('lte'),
			\OZONE\Core\Db\OZAuth::COL_PROVIDER,
			$value
		);
	}

	/**
	 * Filters rows with `gt` condition on column `oz_auths`.`provider`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 */
	public function whereProviderIsGt(string $value): self
	{
		return $this->filterBy(
			Operator::from('gt'),
			\OZONE\Core\Db\OZAuth::COL_PROVIDER,
			$value
		);
	}

	/**
	 * Filters rows with `gte` condition on column `oz_auths`.`provider`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 */
	public function whereProviderIsGte(string $value): self
	{
		return $this->filterBy(
			Operator::from('gte'),
			\OZONE\Core\Db\OZAuth::COL_PROVIDER,
			$value
		);
	}

	/**
	 * Filters rows with `like` condition on column `oz_auths`.`provider`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 */
	public function whereProviderIsLike(string $value): self
	{
		return $this->filterBy(
			Operator::from('like'),
			\OZONE\Core\Db\OZAuth::COL_PROVIDER,
			$value
		);
	}

	/**
	 * Filters rows with `not_like` condition on column `oz_auths`.`provider`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 */
	public function whereProviderIsNotLike(string $value): self
	{
		return $this->filterBy(
			Operator::from('not_like'),
			\OZONE\Core\Db\OZAuth::COL_PROVIDER,
			$value
		);
	}

	/**
	 * Filters rows with `in` condition on column `oz_auths`.`provider`.
	 *
	 * @param array $value the filter value
	 *
	 * @return static
	 */
	public function whereProviderIsIn(array $value): self
	{
		return $this->filterBy(
			Operator::from('in'),
			\OZONE\Core\Db\OZAuth::COL_PROVIDER,
			$value
		);
	}

	/**
	 * Filters rows with `not_in` condition on column `oz_auths`.`provider`.
	 *
	 * @param array $value the filter value
	 *
	 * @return static
	 */
	public function whereProviderIsNotIn(array $value): self
	{
		return $this->filterBy(
			Operator::from('not_in'),
			\OZONE\Core\Db\OZAuth::COL_PROVIDER,
			$value
		);
	}

	/**
	 * Filters rows with `eq` condition on column `oz_auths`.`payload`.
	 *
	 * @param array $value the filter value
	 *
	 * @return static
	 */
	public function wherePayloadIs(array $value): self
	{
		return $this->filterBy(
			Operator::from('eq'),
			\OZONE\Core\Db\OZAuth::COL_PAYLOAD,
			$value
		);
	}

	/**
	 * Filters rows with `neq` condition on column `oz_auths`.`payload`.
	 *
	 * @param array $value the filter value
	 *
	 * @return static
	 */
	public function wherePayloadIsNot(array $value): self
	{
		return $this->filterBy(
			Operator::from('neq'),
			\OZONE\Core\Db\OZAuth::COL_PAYLOAD,
			$value
		);
	}

	/**
	 * Filters rows with `like` condition on column `oz_auths`.`payload`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 */
	public function wherePayloadIsLike(string $value): self
	{
		return $this->filterBy(
			Operator::from('like'),
			\OZONE\Core\Db\OZAuth::COL_PAYLOAD,
			$value
		);
	}

	/**
	 * Filters rows with `not_like` condition on column `oz_auths`.`payload`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 */
	public function wherePayloadIsNotLike(string $value): self
	{
		return $this->filterBy(
			Operator::from('not_like'),
			\OZONE\Core\Db\OZAuth::COL_PAYLOAD,
			$value
		);
	}

	/**
	 * Filters rows with `eq` condition on column `oz_auths`.`code_hash`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 */
	public function whereCodeHashIs(string $value): self
	{
		return $this->filterBy(
			Operator::from('eq'),
			\OZONE\Core\Db\OZAuth::COL_CODE_HASH,
			$value
		);
	}

	/**
	 * Filters rows with `neq` condition on column `oz_auths`.`code_hash`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 */
	public function whereCodeHashIsNot(string $value): self
	{
		return $this->filterBy(
			Operator::from('neq'),
			\OZONE\Core\Db\OZAuth::COL_CODE_HASH,
			$value
		);
	}

	/**
	 * Filters rows with `lt` condition on column `oz_auths`.`code_hash`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 */
	public function whereCodeHashIsLt(string $value): self
	{
		return $this->filterBy(
			Operator::from('lt'),
			\OZONE\Core\Db\OZAuth::COL_CODE_HASH,
			$value
		);
	}

	/**
	 * Filters rows with `lte` condition on column `oz_auths`.`code_hash`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 */
	public function whereCodeHashIsLte(string $value): self
	{
		return $this->filterBy(
			Operator::from('lte'),
			\OZONE\Core\Db\OZAuth::COL_CODE_HASH,
			$value
		);
	}

	/**
	 * Filters rows with `gt` condition on column `oz_auths`.`code_hash`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 */
	public function whereCodeHashIsGt(string $value): self
	{
		return $this->filterBy(
			Operator::from('gt'),
			\OZONE\Core\Db\OZAuth::COL_CODE_HASH,
			$value
		);
	}

	/**
	 * Filters rows with `gte` condition on column `oz_auths`.`code_hash`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 */
	public function whereCodeHashIsGte(string $value): self
	{
		return $this->filterBy(
			Operator::from('gte'),
			\OZONE\Core\Db\OZAuth::COL_CODE_HASH,
			$value
		);
	}

	/**
	 * Filters rows with `like` condition on column `oz_auths`.`code_hash`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 */
	public function whereCodeHashIsLike(string $value): self
	{
		return $this->filterBy(
			Operator::from('like'),
			\OZONE\Core\Db\OZAuth::COL_CODE_HASH,
			$value
		);
	}

	/**
	 * Filters rows with `not_like` condition on column `oz_auths`.`code_hash`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 */
	public function whereCodeHashIsNotLike(string $value): self
	{
		return $this->filterBy(
			Operator::from('not_like'),
			\OZONE\Core\Db\OZAuth::COL_CODE_HASH,
			$value
		);
	}

	/**
	 * Filters rows with `in` condition on column `oz_auths`.`code_hash`.
	 *
	 * @param array $value the filter value
	 *
	 * @return static
	 */
	public function whereCodeHashIsIn(array $value): self
	{
		return $this->filterBy(
			Operator::from('in'),
			\OZONE\Core\Db\OZAuth::COL_CODE_HASH,
			$value
		);
	}

	/**
	 * Filters rows with `not_in` condition on column `oz_auths`.`code_hash`.
	 *
	 * @param array $value the filter value
	 *
	 * @return static
	 */
	public function whereCodeHashIsNotIn(array $value): self
	{
		return $this->filterBy(
			Operator::from('not_in'),
			\OZONE\Core\Db\OZAuth::COL_CODE_HASH,
			$value
		);
	}

	/**
	 * Filters rows with `eq` condition on column `oz_auths`.`token_hash`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 */
	public function whereTokenHashIs(string $value): self
	{
		return $this->filterBy(
			Operator::from('eq'),
			\OZONE\Core\Db\OZAuth::COL_TOKEN_HASH,
			$value
		);
	}

	/**
	 * Filters rows with `neq` condition on column `oz_auths`.`token_hash`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 */
	public function whereTokenHashIsNot(string $value): self
	{
		return $this->filterBy(
			Operator::from('neq'),
			\OZONE\Core\Db\OZAuth::COL_TOKEN_HASH,
			$value
		);
	}

	/**
	 * Filters rows with `lt` condition on column `oz_auths`.`token_hash`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 */
	public function whereTokenHashIsLt(string $value): self
	{
		return $this->filterBy(
			Operator::from('lt'),
			\OZONE\Core\Db\OZAuth::COL_TOKEN_HASH,
			$value
		);
	}

	/**
	 * Filters rows with `lte` condition on column `oz_auths`.`token_hash`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 */
	public function whereTokenHashIsLte(string $value): self
	{
		return $this->filterBy(
			Operator::from('lte'),
			\OZONE\Core\Db\OZAuth::COL_TOKEN_HASH,
			$value
		);
	}

	/**
	 * Filters rows with `gt` condition on column `oz_auths`.`token_hash`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 */
	public function whereTokenHashIsGt(string $value): self
	{
		return $this->filterBy(
			Operator::from('gt'),
			\OZONE\Core\Db\OZAuth::COL_TOKEN_HASH,
			$value
		);
	}

	/**
	 * Filters rows with `gte` condition on column `oz_auths`.`token_hash`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 */
	public function whereTokenHashIsGte(string $value): self
	{
		return $this->filterBy(
			Operator::from('gte'),
			\OZONE\Core\Db\OZAuth::COL_TOKEN_HASH,
			$value
		);
	}

	/**
	 * Filters rows with `like` condition on column `oz_auths`.`token_hash`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 */
	public function whereTokenHashIsLike(string $value): self
	{
		return $this->filterBy(
			Operator::from('like'),
			\OZONE\Core\Db\OZAuth::COL_TOKEN_HASH,
			$value
		);
	}

	/**
	 * Filters rows with `not_like` condition on column `oz_auths`.`token_hash`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 */
	public function whereTokenHashIsNotLike(string $value): self
	{
		return $this->filterBy(
			Operator::from('not_like'),
			\OZONE\Core\Db\OZAuth::COL_TOKEN_HASH,
			$value
		);
	}

	/**
	 * Filters rows with `in` condition on column `oz_auths`.`token_hash`.
	 *
	 * @param array $value the filter value
	 *
	 * @return static
	 */
	public function whereTokenHashIsIn(array $value): self
	{
		return $this->filterBy(
			Operator::from('in'),
			\OZONE\Core\Db\OZAuth::COL_TOKEN_HASH,
			$value
		);
	}

	/**
	 * Filters rows with `not_in` condition on column `oz_auths`.`token_hash`.
	 *
	 * @param array $value the filter value
	 *
	 * @return static
	 */
	public function whereTokenHashIsNotIn(array $value): self
	{
		return $this->filterBy(
			Operator::from('not_in'),
			\OZONE\Core\Db\OZAuth::COL_TOKEN_HASH,
			$value
		);
	}

	/**
	 * Filters rows with `eq` condition on column `oz_auths`.`state`.
	 *
	 * @param \OZONE\Core\Auth\AuthState|string $value the filter value
	 *
	 * @return static
	 */
	public function whereStateIs(\OZONE\Core\Auth\AuthState|string $value): self
	{
		return $this->filterBy(
			Operator::from('eq'),
			\OZONE\Core\Db\OZAuth::COL_STATE,
			$value
		);
	}

	/**
	 * Filters rows with `neq` condition on column `oz_auths`.`state`.
	 *
	 * @param \OZONE\Core\Auth\AuthState|string $value the filter value
	 *
	 * @return static
	 */
	public function whereStateIsNot(\OZONE\Core\Auth\AuthState|string $value): self
	{
		return $this->filterBy(
			Operator::from('neq'),
			\OZONE\Core\Db\OZAuth::COL_STATE,
			$value
		);
	}

	/**
	 * Filters rows with `lt` condition on column `oz_auths`.`state`.
	 *
	 * @param \OZONE\Core\Auth\AuthState|string $value the filter value
	 *
	 * @return static
	 */
	public function whereStateIsLt(\OZONE\Core\Auth\AuthState|string $value): self
	{
		return $this->filterBy(
			Operator::from('lt'),
			\OZONE\Core\Db\OZAuth::COL_STATE,
			$value
		);
	}

	/**
	 * Filters rows with `lte` condition on column `oz_auths`.`state`.
	 *
	 * @param \OZONE\Core\Auth\AuthState|string $value the filter value
	 *
	 * @return static
	 */
	public function whereStateIsLte(\OZONE\Core\Auth\AuthState|string $value): self
	{
		return $this->filterBy(
			Operator::from('lte'),
			\OZONE\Core\Db\OZAuth::COL_STATE,
			$value
		);
	}

	/**
	 * Filters rows with `gt` condition on column `oz_auths`.`state`.
	 *
	 * @param \OZONE\Core\Auth\AuthState|string $value the filter value
	 *
	 * @return static
	 */
	public function whereStateIsGt(\OZONE\Core\Auth\AuthState|string $value): self
	{
		return $this->filterBy(
			Operator::from('gt'),
			\OZONE\Core\Db\OZAuth::COL_STATE,
			$value
		);
	}

	/**
	 * Filters rows with `gte` condition on column `oz_auths`.`state`.
	 *
	 * @param \OZONE\Core\Auth\AuthState|string $value the filter value
	 *
	 * @return static
	 */
	public function whereStateIsGte(\OZONE\Core\Auth\AuthState|string $value): self
	{
		return $this->filterBy(
			Operator::from('gte'),
			\OZONE\Core\Db\OZAuth::COL_STATE,
			$value
		);
	}

	/**
	 * Filters rows with `like` condition on column `oz_auths`.`state`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 */
	public function whereStateIsLike(string $value): self
	{
		return $this->filterBy(
			Operator::from('like'),
			\OZONE\Core\Db\OZAuth::COL_STATE,
			$value
		);
	}

	/**
	 * Filters rows with `not_like` condition on column `oz_auths`.`state`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 */
	public function whereStateIsNotLike(string $value): self
	{
		return $this->filterBy(
			Operator::from('not_like'),
			\OZONE\Core\Db\OZAuth::COL_STATE,
			$value
		);
	}

	/**
	 * Filters rows with `in` condition on column `oz_auths`.`state`.
	 *
	 * @param array $value the filter value
	 *
	 * @return static
	 */
	public function whereStateIsIn(array $value): self
	{
		return $this->filterBy(
			Operator::from('in'),
			\OZONE\Core\Db\OZAuth::COL_STATE,
			$value
		);
	}

	/**
	 * Filters rows with `not_in` condition on column `oz_auths`.`state`.
	 *
	 * @param array $value the filter value
	 *
	 * @return static
	 */
	public function whereStateIsNotIn(array $value): self
	{
		return $this->filterBy(
			Operator::from('not_in'),
			\OZONE\Core\Db\OZAuth::COL_STATE,
			$value
		);
	}

	/**
	 * Filters rows with `eq` condition on column `oz_auths`.`try_max`.
	 *
	 * @param int $value the filter value
	 *
	 * @return static
	 */
	public function whereTryMaxIs(int $value): self
	{
		return $this->filterBy(
			Operator::from('eq'),
			\OZONE\Core\Db\OZAuth::COL_TRY_MAX,
			$value
		);
	}

	/**
	 * Filters rows with `neq` condition on column `oz_auths`.`try_max`.
	 *
	 * @param int $value the filter value
	 *
	 * @return static
	 */
	public function whereTryMaxIsNot(int $value): self
	{
		return $this->filterBy(
			Operator::from('neq'),
			\OZONE\Core\Db\OZAuth::COL_TRY_MAX,
			$value
		);
	}

	/**
	 * Filters rows with `lt` condition on column `oz_auths`.`try_max`.
	 *
	 * @param int $value the filter value
	 *
	 * @return static
	 */
	public function whereTryMaxIsLt(int $value): self
	{
		return $this->filterBy(
			Operator::from('lt'),
			\OZONE\Core\Db\OZAuth::COL_TRY_MAX,
			$value
		);
	}

	/**
	 * Filters rows with `lte` condition on column `oz_auths`.`try_max`.
	 *
	 * @param int $value the filter value
	 *
	 * @return static
	 */
	public function whereTryMaxIsLte(int $value): self
	{
		return $this->filterBy(
			Operator::from('lte'),
			\OZONE\Core\Db\OZAuth::COL_TRY_MAX,
			$value
		);
	}

	/**
	 * Filters rows with `gt` condition on column `oz_auths`.`try_max`.
	 *
	 * @param int $value the filter value
	 *
	 * @return static
	 */
	public function whereTryMaxIsGt(int $value): self
	{
		return $this->filterBy(
			Operator::from('gt'),
			\OZONE\Core\Db\OZAuth::COL_TRY_MAX,
			$value
		);
	}

	/**
	 * Filters rows with `gte` condition on column `oz_auths`.`try_max`.
	 *
	 * @param int $value the filter value
	 *
	 * @return static
	 */
	public function whereTryMaxIsGte(int $value): self
	{
		return $this->filterBy(
			Operator::from('gte'),
			\OZONE\Core\Db\OZAuth::COL_TRY_MAX,
			$value
		);
	}

	/**
	 * Filters rows with `like` condition on column `oz_auths`.`try_max`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 */
	public function whereTryMaxIsLike(string $value): self
	{
		return $this->filterBy(
			Operator::from('like'),
			\OZONE\Core\Db\OZAuth::COL_TRY_MAX,
			$value
		);
	}

	/**
	 * Filters rows with `not_like` condition on column `oz_auths`.`try_max`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 */
	public function whereTryMaxIsNotLike(string $value): self
	{
		return $this->filterBy(
			Operator::from('not_like'),
			\OZONE\Core\Db\OZAuth::COL_TRY_MAX,
			$value
		);
	}

	/**
	 * Filters rows with `in` condition on column `oz_auths`.`try_max`.
	 *
	 * @param array $value the filter value
	 *
	 * @return static
	 */
	public function whereTryMaxIsIn(array $value): self
	{
		return $this->filterBy(
			Operator::from('in'),
			\OZONE\Core\Db\OZAuth::COL_TRY_MAX,
			$value
		);
	}

	/**
	 * Filters rows with `not_in` condition on column `oz_auths`.`try_max`.
	 *
	 * @param array $value the filter value
	 *
	 * @return static
	 */
	public function whereTryMaxIsNotIn(array $value): self
	{
		return $this->filterBy(
			Operator::from('not_in'),
			\OZONE\Core\Db\OZAuth::COL_TRY_MAX,
			$value
		);
	}

	/**
	 * Filters rows with `eq` condition on column `oz_auths`.`try_count`.
	 *
	 * @param int $value the filter value
	 *
	 * @return static
	 */
	public function whereTryCountIs(int $value): self
	{
		return $this->filterBy(
			Operator::from('eq'),
			\OZONE\Core\Db\OZAuth::COL_TRY_COUNT,
			$value
		);
	}

	/**
	 * Filters rows with `neq` condition on column `oz_auths`.`try_count`.
	 *
	 * @param int $value the filter value
	 *
	 * @return static
	 */
	public function whereTryCountIsNot(int $value): self
	{
		return $this->filterBy(
			Operator::from('neq'),
			\OZONE\Core\Db\OZAuth::COL_TRY_COUNT,
			$value
		);
	}

	/**
	 * Filters rows with `lt` condition on column `oz_auths`.`try_count`.
	 *
	 * @param int $value the filter value
	 *
	 * @return static
	 */
	public function whereTryCountIsLt(int $value): self
	{
		return $this->filterBy(
			Operator::from('lt'),
			\OZONE\Core\Db\OZAuth::COL_TRY_COUNT,
			$value
		);
	}

	/**
	 * Filters rows with `lte` condition on column `oz_auths`.`try_count`.
	 *
	 * @param int $value the filter value
	 *
	 * @return static
	 */
	public function whereTryCountIsLte(int $value): self
	{
		return $this->filterBy(
			Operator::from('lte'),
			\OZONE\Core\Db\OZAuth::COL_TRY_COUNT,
			$value
		);
	}

	/**
	 * Filters rows with `gt` condition on column `oz_auths`.`try_count`.
	 *
	 * @param int $value the filter value
	 *
	 * @return static
	 */
	public function whereTryCountIsGt(int $value): self
	{
		return $this->filterBy(
			Operator::from('gt'),
			\OZONE\Core\Db\OZAuth::COL_TRY_COUNT,
			$value
		);
	}

	/**
	 * Filters rows with `gte` condition on column `oz_auths`.`try_count`.
	 *
	 * @param int $value the filter value
	 *
	 * @return static
	 */
	public function whereTryCountIsGte(int $value): self
	{
		return $this->filterBy(
			Operator::from('gte'),
			\OZONE\Core\Db\OZAuth::COL_TRY_COUNT,
			$value
		);
	}

	/**
	 * Filters rows with `like` condition on column `oz_auths`.`try_count`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 */
	public function whereTryCountIsLike(string $value): self
	{
		return $this->filterBy(
			Operator::from('like'),
			\OZONE\Core\Db\OZAuth::COL_TRY_COUNT,
			$value
		);
	}

	/**
	 * Filters rows with `not_like` condition on column `oz_auths`.`try_count`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 */
	public function whereTryCountIsNotLike(string $value): self
	{
		return $this->filterBy(
			Operator::from('not_like'),
			\OZONE\Core\Db\OZAuth::COL_TRY_COUNT,
			$value
		);
	}

	/**
	 * Filters rows with `in` condition on column `oz_auths`.`try_count`.
	 *
	 * @param array $value the filter value
	 *
	 * @return static
	 */
	public function whereTryCountIsIn(array $value): self
	{
		return $this->filterBy(
			Operator::from('in'),
			\OZONE\Core\Db\OZAuth::COL_TRY_COUNT,
			$value
		);
	}

	/**
	 * Filters rows with `not_in` condition on column `oz_auths`.`try_count`.
	 *
	 * @param array $value the filter value
	 *
	 * @return static
	 */
	public function whereTryCountIsNotIn(array $value): self
	{
		return $this->filterBy(
			Operator::from('not_in'),
			\OZONE\Core\Db\OZAuth::COL_TRY_COUNT,
			$value
		);
	}

	/**
	 * Filters rows with `eq` condition on column `oz_auths`.`lifetime`.
	 *
	 * @param int $value the filter value
	 *
	 * @return static
	 */
	public function whereLifetimeIs(int $value): self
	{
		return $this->filterBy(
			Operator::from('eq'),
			\OZONE\Core\Db\OZAuth::COL_LIFETIME,
			$value
		);
	}

	/**
	 * Filters rows with `neq` condition on column `oz_auths`.`lifetime`.
	 *
	 * @param int $value the filter value
	 *
	 * @return static
	 */
	public function whereLifetimeIsNot(int $value): self
	{
		return $this->filterBy(
			Operator::from('neq'),
			\OZONE\Core\Db\OZAuth::COL_LIFETIME,
			$value
		);
	}

	/**
	 * Filters rows with `lt` condition on column `oz_auths`.`lifetime`.
	 *
	 * @param int $value the filter value
	 *
	 * @return static
	 */
	public function whereLifetimeIsLt(int $value): self
	{
		return $this->filterBy(
			Operator::from('lt'),
			\OZONE\Core\Db\OZAuth::COL_LIFETIME,
			$value
		);
	}

	/**
	 * Filters rows with `lte` condition on column `oz_auths`.`lifetime`.
	 *
	 * @param int $value the filter value
	 *
	 * @return static
	 */
	public function whereLifetimeIsLte(int $value): self
	{
		return $this->filterBy(
			Operator::from('lte'),
			\OZONE\Core\Db\OZAuth::COL_LIFETIME,
			$value
		);
	}

	/**
	 * Filters rows with `gt` condition on column `oz_auths`.`lifetime`.
	 *
	 * @param int $value the filter value
	 *
	 * @return static
	 */
	public function whereLifetimeIsGt(int $value): self
	{
		return $this->filterBy(
			Operator::from('gt'),
			\OZONE\Core\Db\OZAuth::COL_LIFETIME,
			$value
		);
	}

	/**
	 * Filters rows with `gte` condition on column `oz_auths`.`lifetime`.
	 *
	 * @param int $value the filter value
	 *
	 * @return static
	 */
	public function whereLifetimeIsGte(int $value): self
	{
		return $this->filterBy(
			Operator::from('gte'),
			\OZONE\Core\Db\OZAuth::COL_LIFETIME,
			$value
		);
	}

	/**
	 * Filters rows with `like` condition on column `oz_auths`.`lifetime`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 */
	public function whereLifetimeIsLike(string $value): self
	{
		return $this->filterBy(
			Operator::from('like'),
			\OZONE\Core\Db\OZAuth::COL_LIFETIME,
			$value
		);
	}

	/**
	 * Filters rows with `not_like` condition on column `oz_auths`.`lifetime`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 */
	public function whereLifetimeIsNotLike(string $value): self
	{
		return $this->filterBy(
			Operator::from('not_like'),
			\OZONE\Core\Db\OZAuth::COL_LIFETIME,
			$value
		);
	}

	/**
	 * Filters rows with `in` condition on column `oz_auths`.`lifetime`.
	 *
	 * @param array $value the filter value
	 *
	 * @return static
	 */
	public function whereLifetimeIsIn(array $value): self
	{
		return $this->filterBy(
			Operator::from('in'),
			\OZONE\Core\Db\OZAuth::COL_LIFETIME,
			$value
		);
	}

	/**
	 * Filters rows with `not_in` condition on column `oz_auths`.`lifetime`.
	 *
	 * @param array $value the filter value
	 *
	 * @return static
	 */
	public function whereLifetimeIsNotIn(array $value): self
	{
		return $this->filterBy(
			Operator::from('not_in'),
			\OZONE\Core\Db\OZAuth::COL_LIFETIME,
			$value
		);
	}

	/**
	 * Filters rows with `eq` condition on column `oz_auths`.`expire`.
	 *
	 * @param int|string $value the filter value
	 *
	 * @return static
	 */
	public function whereExpireIs(string|int $value): self
	{
		return $this->filterBy(
			Operator::from('eq'),
			\OZONE\Core\Db\OZAuth::COL_EXPIRE,
			$value
		);
	}

	/**
	 * Filters rows with `neq` condition on column `oz_auths`.`expire`.
	 *
	 * @param int|string $value the filter value
	 *
	 * @return static
	 */
	public function whereExpireIsNot(string|int $value): self
	{
		return $this->filterBy(
			Operator::from('neq'),
			\OZONE\Core\Db\OZAuth::COL_EXPIRE,
			$value
		);
	}

	/**
	 * Filters rows with `lt` condition on column `oz_auths`.`expire`.
	 *
	 * @param int|string $value the filter value
	 *
	 * @return static
	 */
	public function whereExpireIsLt(string|int $value): self
	{
		return $this->filterBy(
			Operator::from('lt'),
			\OZONE\Core\Db\OZAuth::COL_EXPIRE,
			$value
		);
	}

	/**
	 * Filters rows with `lte` condition on column `oz_auths`.`expire`.
	 *
	 * @param int|string $value the filter value
	 *
	 * @return static
	 */
	public function whereExpireIsLte(string|int $value): self
	{
		return $this->filterBy(
			Operator::from('lte'),
			\OZONE\Core\Db\OZAuth::COL_EXPIRE,
			$value
		);
	}

	/**
	 * Filters rows with `gt` condition on column `oz_auths`.`expire`.
	 *
	 * @param int|string $value the filter value
	 *
	 * @return static
	 */
	public function whereExpireIsGt(string|int $value): self
	{
		return $this->filterBy(
			Operator::from('gt'),
			\OZONE\Core\Db\OZAuth::COL_EXPIRE,
			$value
		);
	}

	/**
	 * Filters rows with `gte` condition on column `oz_auths`.`expire`.
	 *
	 * @param int|string $value the filter value
	 *
	 * @return static
	 */
	public function whereExpireIsGte(string|int $value): self
	{
		return $this->filterBy(
			Operator::from('gte'),
			\OZONE\Core\Db\OZAuth::COL_EXPIRE,
			$value
		);
	}

	/**
	 * Filters rows with `like` condition on column `oz_auths`.`expire`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 */
	public function whereExpireIsLike(string $value): self
	{
		return $this->filterBy(
			Operator::from('like'),
			\OZONE\Core\Db\OZAuth::COL_EXPIRE,
			$value
		);
	}

	/**
	 * Filters rows with `not_like` condition on column `oz_auths`.`expire`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 */
	public function whereExpireIsNotLike(string $value): self
	{
		return $this->filterBy(
			Operator::from('not_like'),
			\OZONE\Core\Db\OZAuth::COL_EXPIRE,
			$value
		);
	}

	/**
	 * Filters rows with `in` condition on column `oz_auths`.`expire`.
	 *
	 * @param array $value the filter value
	 *
	 * @return static
	 */
	public function whereExpireIsIn(array $value): self
	{
		return $this->filterBy(
			Operator::from('in'),
			\OZONE\Core\Db\OZAuth::COL_EXPIRE,
			$value
		);
	}

	/**
	 * Filters rows with `not_in` condition on column `oz_auths`.`expire`.
	 *
	 * @param array $value the filter value
	 *
	 * @return static
	 */
	public function whereExpireIsNotIn(array $value): self
	{
		return $this->filterBy(
			Operator::from('not_in'),
			\OZONE\Core\Db\OZAuth::COL_EXPIRE,
			$value
		);
	}

	/**
	 * Filters rows with `eq` condition on column `oz_auths`.`options`.
	 *
	 * @param array $value the filter value
	 *
	 * @return static
	 */
	public function whereOptionsIs(array $value): self
	{
		return $this->filterBy(
			Operator::from('eq'),
			\OZONE\Core\Db\OZAuth::COL_OPTIONS,
			$value
		);
	}

	/**
	 * Filters rows with `neq` condition on column `oz_auths`.`options`.
	 *
	 * @param array $value the filter value
	 *
	 * @return static
	 */
	public function whereOptionsIsNot(array $value): self
	{
		return $this->filterBy(
			Operator::from('neq'),
			\OZONE\Core\Db\OZAuth::COL_OPTIONS,
			$value
		);
	}

	/**
	 * Filters rows with `like` condition on column `oz_auths`.`options`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 */
	public function whereOptionsIsLike(string $value): self
	{
		return $this->filterBy(
			Operator::from('like'),
			\OZONE\Core\Db\OZAuth::COL_OPTIONS,
			$value
		);
	}

	/**
	 * Filters rows with `not_like` condition on column `oz_auths`.`options`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 */
	public function whereOptionsIsNotLike(string $value): self
	{
		return $this->filterBy(
			Operator::from('not_like'),
			\OZONE\Core\Db\OZAuth::COL_OPTIONS,
			$value
		);
	}

	/**
	 * Filters rows with `eq` condition on column `oz_auths`.`created_at`.
	 *
	 * @param int|string $value the filter value
	 *
	 * @return static
	 */
	public function whereCreatedAtIs(string|int $value): self
	{
		return $this->filterBy(
			Operator::from('eq'),
			\OZONE\Core\Db\OZAuth::COL_CREATED_AT,
			$value
		);
	}

	/**
	 * Filters rows with `neq` condition on column `oz_auths`.`created_at`.
	 *
	 * @param int|string $value the filter value
	 *
	 * @return static
	 */
	public function whereCreatedAtIsNot(string|int $value): self
	{
		return $this->filterBy(
			Operator::from('neq'),
			\OZONE\Core\Db\OZAuth::COL_CREATED_AT,
			$value
		);
	}

	/**
	 * Filters rows with `lt` condition on column `oz_auths`.`created_at`.
	 *
	 * @param int|string $value the filter value
	 *
	 * @return static
	 */
	public function whereCreatedAtIsLt(string|int $value): self
	{
		return $this->filterBy(
			Operator::from('lt'),
			\OZONE\Core\Db\OZAuth::COL_CREATED_AT,
			$value
		);
	}

	/**
	 * Filters rows with `lte` condition on column `oz_auths`.`created_at`.
	 *
	 * @param int|string $value the filter value
	 *
	 * @return static
	 */
	public function whereCreatedAtIsLte(string|int $value): self
	{
		return $this->filterBy(
			Operator::from('lte'),
			\OZONE\Core\Db\OZAuth::COL_CREATED_AT,
			$value
		);
	}

	/**
	 * Filters rows with `gt` condition on column `oz_auths`.`created_at`.
	 *
	 * @param int|string $value the filter value
	 *
	 * @return static
	 */
	public function whereCreatedAtIsGt(string|int $value): self
	{
		return $this->filterBy(
			Operator::from('gt'),
			\OZONE\Core\Db\OZAuth::COL_CREATED_AT,
			$value
		);
	}

	/**
	 * Filters rows with `gte` condition on column `oz_auths`.`created_at`.
	 *
	 * @param int|string $value the filter value
	 *
	 * @return static
	 */
	public function whereCreatedAtIsGte(string|int $value): self
	{
		return $this->filterBy(
			Operator::from('gte'),
			\OZONE\Core\Db\OZAuth::COL_CREATED_AT,
			$value
		);
	}

	/**
	 * Filters rows with `like` condition on column `oz_auths`.`created_at`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 */
	public function whereCreatedAtIsLike(string $value): self
	{
		return $this->filterBy(
			Operator::from('like'),
			\OZONE\Core\Db\OZAuth::COL_CREATED_AT,
			$value
		);
	}

	/**
	 * Filters rows with `not_like` condition on column `oz_auths`.`created_at`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 */
	public function whereCreatedAtIsNotLike(string $value): self
	{
		return $this->filterBy(
			Operator::from('not_like'),
			\OZONE\Core\Db\OZAuth::COL_CREATED_AT,
			$value
		);
	}

	/**
	 * Filters rows with `in` condition on column `oz_auths`.`created_at`.
	 *
	 * @param array $value the filter value
	 *
	 * @return static
	 */
	public function whereCreatedAtIsIn(array $value): self
	{
		return $this->filterBy(
			Operator::from('in'),
			\OZONE\Core\Db\OZAuth::COL_CREATED_AT,
			$value
		);
	}

	/**
	 * Filters rows with `not_in` condition on column `oz_auths`.`created_at`.
	 *
	 * @param array $value the filter value
	 *
	 * @return static
	 */
	public function whereCreatedAtIsNotIn(array $value): self
	{
		return $this->filterBy(
			Operator::from('not_in'),
			\OZONE\Core\Db\OZAuth::COL_CREATED_AT,
			$value
		);
	}

	/**
	 * Filters rows with `eq` condition on column `oz_auths`.`updated_at`.
	 *
	 * @param int|string $value the filter value
	 *
	 * @return static
	 */
	public function whereUpdatedAtIs(string|int $value): self
	{
		return $this->filterBy(
			Operator::from('eq'),
			\OZONE\Core\Db\OZAuth::COL_UPDATED_AT,
			$value
		);
	}

	/**
	 * Filters rows with `neq` condition on column `oz_auths`.`updated_at`.
	 *
	 * @param int|string $value the filter value
	 *
	 * @return static
	 */
	public function whereUpdatedAtIsNot(string|int $value): self
	{
		return $this->filterBy(
			Operator::from('neq'),
			\OZONE\Core\Db\OZAuth::COL_UPDATED_AT,
			$value
		);
	}

	/**
	 * Filters rows with `lt` condition on column `oz_auths`.`updated_at`.
	 *
	 * @param int|string $value the filter value
	 *
	 * @return static
	 */
	public function whereUpdatedAtIsLt(string|int $value): self
	{
		return $this->filterBy(
			Operator::from('lt'),
			\OZONE\Core\Db\OZAuth::COL_UPDATED_AT,
			$value
		);
	}

	/**
	 * Filters rows with `lte` condition on column `oz_auths`.`updated_at`.
	 *
	 * @param int|string $value the filter value
	 *
	 * @return static
	 */
	public function whereUpdatedAtIsLte(string|int $value): self
	{
		return $this->filterBy(
			Operator::from('lte'),
			\OZONE\Core\Db\OZAuth::COL_UPDATED_AT,
			$value
		);
	}

	/**
	 * Filters rows with `gt` condition on column `oz_auths`.`updated_at`.
	 *
	 * @param int|string $value the filter value
	 *
	 * @return static
	 */
	public function whereUpdatedAtIsGt(string|int $value): self
	{
		return $this->filterBy(
			Operator::from('gt'),
			\OZONE\Core\Db\OZAuth::COL_UPDATED_AT,
			$value
		);
	}

	/**
	 * Filters rows with `gte` condition on column `oz_auths`.`updated_at`.
	 *
	 * @param int|string $value the filter value
	 *
	 * @return static
	 */
	public function whereUpdatedAtIsGte(string|int $value): self
	{
		return $this->filterBy(
			Operator::from('gte'),
			\OZONE\Core\Db\OZAuth::COL_UPDATED_AT,
			$value
		);
	}

	/**
	 * Filters rows with `like` condition on column `oz_auths`.`updated_at`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 */
	public function whereUpdatedAtIsLike(string $value): self
	{
		return $this->filterBy(
			Operator::from('like'),
			\OZONE\Core\Db\OZAuth::COL_UPDATED_AT,
			$value
		);
	}

	/**
	 * Filters rows with `not_like` condition on column `oz_auths`.`updated_at`.
	 *
	 * @param string $value the filter value
	 *
	 * @return static
	 */
	public function whereUpdatedAtIsNotLike(string $value): self
	{
		return $this->filterBy(
			Operator::from('not_like'),
			\OZONE\Core\Db\OZAuth::COL_UPDATED_AT,
			$value
		);
	}

	/**
	 * Filters rows with `in` condition on column `oz_auths`.`updated_at`.
	 *
	 * @param array $value the filter value
	 *
	 * @return static
	 */
	public function whereUpdatedAtIsIn(array $value): self
	{
		return $this->filterBy(
			Operator::from('in'),
			\OZONE\Core\Db\OZAuth::COL_UPDATED_AT,
			$value
		);
	}

	/**
	 * Filters rows with `not_in` condition on column `oz_auths`.`updated_at`.
	 *
	 * @param array $value the filter value
	 *
	 * @return static
	 */
	public function whereUpdatedAtIsNotIn(array $value): self
	{
		return $this->filterBy(
			Operator::from('not_in'),
			\OZONE\Core\Db\OZAuth::COL_UPDATED_AT,
			$value
		);
	}

	/**
	 * Filters rows with `eq` condition on column `oz_auths`.`is_valid`.
	 *
	 * @param bool $value the filter value
	 *
	 * @return static
	 */
	public function whereIsValidIs(bool $value): self
	{
		return $this->filterBy(
			Operator::from('eq'),
			\OZONE\Core\Db\OZAuth::COL_IS_VALID,
			$value
		);
	}

	/**
	 * Filters rows with `neq` condition on column `oz_auths`.`is_valid`.
	 *
	 * @param bool $value the filter value
	 *
	 * @return static
	 */
	public function whereIsValidIsNot(bool $value): self
	{
		return $this->filterBy(
			Operator::from('neq'),
			\OZONE\Core\Db\OZAuth::COL_IS_VALID,
			$value
		);
	}

	/**
	 * Filters rows with `is_false` condition on column `oz_auths`.`is_valid`.
	 *
	 * @return static
	 */
	public function whereIsNotValid(): self
	{
		return $this->filterBy(
			Operator::from('is_false'),
			\OZONE\Core\Db\OZAuth::COL_IS_VALID
		);
	}

	/**
	 * Filters rows with `is_true` condition on column `oz_auths`.`is_valid`.
	 *
	 * @return static
	 */
	public function whereIsValid(): self
	{
		return $this->filterBy(
			Operator::from('is_true'),
			\OZONE\Core\Db\OZAuth::COL_IS_VALID
		);
	}
}
