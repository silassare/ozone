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

/**
 * Class OZDbStoresQuery.
 *
 * @extends \Gobl\ORM\ORMTableQuery<\OZONE\Core\Db\OZDbStore>
 *
 * @method $this whereIdIs(int|string $value)           Filters rows with `eq` condition on column `oz_db_stores`.`id`.
 * @method $this whereIdIsNot(int|string $value)        Filters rows with `neq` condition on column `oz_db_stores`.`id`.
 * @method $this whereIdIsLt(int|string $value)         Filters rows with `lt` condition on column `oz_db_stores`.`id`.
 * @method $this whereIdIsLte(int|string $value)        Filters rows with `lte` condition on column `oz_db_stores`.`id`.
 * @method $this whereIdIsGt(int|string $value)         Filters rows with `gt` condition on column `oz_db_stores`.`id`.
 * @method $this whereIdIsGte(int|string $value)        Filters rows with `gte` condition on column `oz_db_stores`.`id`.
 * @method $this whereIdIsLike(string $value)           Filters rows with `like` condition on column `oz_db_stores`.`id`.
 * @method $this whereIdIsNotLike(string $value)        Filters rows with `not_like` condition on column `oz_db_stores`.`id`.
 * @method $this whereIdIsIn(array $value)              Filters rows with `in` condition on column `oz_db_stores`.`id`.
 * @method $this whereIdIsNotIn(array $value)           Filters rows with `not_in` condition on column `oz_db_stores`.`id`.
 * @method $this whereGroupIs(string $value)            Filters rows with `eq` condition on column `oz_db_stores`.`group`.
 * @method $this whereGroupIsNot(string $value)         Filters rows with `neq` condition on column `oz_db_stores`.`group`.
 * @method $this whereGroupIsLt(string $value)          Filters rows with `lt` condition on column `oz_db_stores`.`group`.
 * @method $this whereGroupIsLte(string $value)         Filters rows with `lte` condition on column `oz_db_stores`.`group`.
 * @method $this whereGroupIsGt(string $value)          Filters rows with `gt` condition on column `oz_db_stores`.`group`.
 * @method $this whereGroupIsGte(string $value)         Filters rows with `gte` condition on column `oz_db_stores`.`group`.
 * @method $this whereGroupIsLike(string $value)        Filters rows with `like` condition on column `oz_db_stores`.`group`.
 * @method $this whereGroupIsNotLike(string $value)     Filters rows with `not_like` condition on column `oz_db_stores`.`group`.
 * @method $this whereGroupIsIn(array $value)           Filters rows with `in` condition on column `oz_db_stores`.`group`.
 * @method $this whereGroupIsNotIn(array $value)        Filters rows with `not_in` condition on column `oz_db_stores`.`group`.
 * @method $this whereKeyIs(string $value)              Filters rows with `eq` condition on column `oz_db_stores`.`key`.
 * @method $this whereKeyIsNot(string $value)           Filters rows with `neq` condition on column `oz_db_stores`.`key`.
 * @method $this whereKeyIsLt(string $value)            Filters rows with `lt` condition on column `oz_db_stores`.`key`.
 * @method $this whereKeyIsLte(string $value)           Filters rows with `lte` condition on column `oz_db_stores`.`key`.
 * @method $this whereKeyIsGt(string $value)            Filters rows with `gt` condition on column `oz_db_stores`.`key`.
 * @method $this whereKeyIsGte(string $value)           Filters rows with `gte` condition on column `oz_db_stores`.`key`.
 * @method $this whereKeyIsLike(string $value)          Filters rows with `like` condition on column `oz_db_stores`.`key`.
 * @method $this whereKeyIsNotLike(string $value)       Filters rows with `not_like` condition on column `oz_db_stores`.`key`.
 * @method $this whereKeyIsIn(array $value)             Filters rows with `in` condition on column `oz_db_stores`.`key`.
 * @method $this whereKeyIsNotIn(array $value)          Filters rows with `not_in` condition on column `oz_db_stores`.`key`.
 * @method $this whereValueIs(string $value)            Filters rows with `eq` condition on column `oz_db_stores`.`value`.
 * @method $this whereValueIsNot(string $value)         Filters rows with `neq` condition on column `oz_db_stores`.`value`.
 * @method $this whereValueIsLt(string $value)          Filters rows with `lt` condition on column `oz_db_stores`.`value`.
 * @method $this whereValueIsLte(string $value)         Filters rows with `lte` condition on column `oz_db_stores`.`value`.
 * @method $this whereValueIsGt(string $value)          Filters rows with `gt` condition on column `oz_db_stores`.`value`.
 * @method $this whereValueIsGte(string $value)         Filters rows with `gte` condition on column `oz_db_stores`.`value`.
 * @method $this whereValueIsLike(string $value)        Filters rows with `like` condition on column `oz_db_stores`.`value`.
 * @method $this whereValueIsNotLike(string $value)     Filters rows with `not_like` condition on column `oz_db_stores`.`value`.
 * @method $this whereValueIsNull()                     Filters rows with `is_null` condition on column `oz_db_stores`.`value`.
 * @method $this whereValueIsNotNull()                  Filters rows with `is_not_null` condition on column `oz_db_stores`.`value`.
 * @method $this whereValueIsIn(array $value)           Filters rows with `in` condition on column `oz_db_stores`.`value`.
 * @method $this whereValueIsNotIn(array $value)        Filters rows with `not_in` condition on column `oz_db_stores`.`value`.
 * @method $this whereLabelIs(string $value)            Filters rows with `eq` condition on column `oz_db_stores`.`label`.
 * @method $this whereLabelIsNot(string $value)         Filters rows with `neq` condition on column `oz_db_stores`.`label`.
 * @method $this whereLabelIsLt(string $value)          Filters rows with `lt` condition on column `oz_db_stores`.`label`.
 * @method $this whereLabelIsLte(string $value)         Filters rows with `lte` condition on column `oz_db_stores`.`label`.
 * @method $this whereLabelIsGt(string $value)          Filters rows with `gt` condition on column `oz_db_stores`.`label`.
 * @method $this whereLabelIsGte(string $value)         Filters rows with `gte` condition on column `oz_db_stores`.`label`.
 * @method $this whereLabelIsLike(string $value)        Filters rows with `like` condition on column `oz_db_stores`.`label`.
 * @method $this whereLabelIsNotLike(string $value)     Filters rows with `not_like` condition on column `oz_db_stores`.`label`.
 * @method $this whereLabelIsIn(array $value)           Filters rows with `in` condition on column `oz_db_stores`.`label`.
 * @method $this whereLabelIsNotIn(array $value)        Filters rows with `not_in` condition on column `oz_db_stores`.`label`.
 * @method $this whereDataIs(array $value)              Filters rows with `eq` condition on column `oz_db_stores`.`data`.
 * @method $this whereDataIsNot(array $value)           Filters rows with `neq` condition on column `oz_db_stores`.`data`.
 * @method $this whereDataIsLike(string $value)         Filters rows with `like` condition on column `oz_db_stores`.`data`.
 * @method $this whereDataIsNotLike(string $value)      Filters rows with `not_like` condition on column `oz_db_stores`.`data`.
 * @method $this whereCreatedAtIs(int|string $value)    Filters rows with `eq` condition on column `oz_db_stores`.`created_at`.
 * @method $this whereCreatedAtIsNot(int|string $value) Filters rows with `neq` condition on column `oz_db_stores`.`created_at`.
 * @method $this whereCreatedAtIsLt(int|string $value)  Filters rows with `lt` condition on column `oz_db_stores`.`created_at`.
 * @method $this whereCreatedAtIsLte(int|string $value) Filters rows with `lte` condition on column `oz_db_stores`.`created_at`.
 * @method $this whereCreatedAtIsGt(int|string $value)  Filters rows with `gt` condition on column `oz_db_stores`.`created_at`.
 * @method $this whereCreatedAtIsGte(int|string $value) Filters rows with `gte` condition on column `oz_db_stores`.`created_at`.
 * @method $this whereCreatedAtIsLike(string $value)    Filters rows with `like` condition on column `oz_db_stores`.`created_at`.
 * @method $this whereCreatedAtIsNotLike(string $value) Filters rows with `not_like` condition on column `oz_db_stores`.`created_at`.
 * @method $this whereCreatedAtIsIn(array $value)       Filters rows with `in` condition on column `oz_db_stores`.`created_at`.
 * @method $this whereCreatedAtIsNotIn(array $value)    Filters rows with `not_in` condition on column `oz_db_stores`.`created_at`.
 * @method $this whereUpdatedAtIs(int|string $value)    Filters rows with `eq` condition on column `oz_db_stores`.`updated_at`.
 * @method $this whereUpdatedAtIsNot(int|string $value) Filters rows with `neq` condition on column `oz_db_stores`.`updated_at`.
 * @method $this whereUpdatedAtIsLt(int|string $value)  Filters rows with `lt` condition on column `oz_db_stores`.`updated_at`.
 * @method $this whereUpdatedAtIsLte(int|string $value) Filters rows with `lte` condition on column `oz_db_stores`.`updated_at`.
 * @method $this whereUpdatedAtIsGt(int|string $value)  Filters rows with `gt` condition on column `oz_db_stores`.`updated_at`.
 * @method $this whereUpdatedAtIsGte(int|string $value) Filters rows with `gte` condition on column `oz_db_stores`.`updated_at`.
 * @method $this whereUpdatedAtIsLike(string $value)    Filters rows with `like` condition on column `oz_db_stores`.`updated_at`.
 * @method $this whereUpdatedAtIsNotLike(string $value) Filters rows with `not_like` condition on column `oz_db_stores`.`updated_at`.
 * @method $this whereUpdatedAtIsIn(array $value)       Filters rows with `in` condition on column `oz_db_stores`.`updated_at`.
 * @method $this whereUpdatedAtIsNotIn(array $value)    Filters rows with `not_in` condition on column `oz_db_stores`.`updated_at`.
 * @method $this whereIsValidIs(bool $value)            Filters rows with `eq` condition on column `oz_db_stores`.`is_valid`.
 * @method $this whereIsValidIsNot(bool $value)         Filters rows with `neq` condition on column `oz_db_stores`.`is_valid`.
 * @method $this whereIsNotValid()                      Filters rows with `is_false` condition on column `oz_db_stores`.`is_valid`.
 * @method $this whereIsValid()                         Filters rows with `is_true` condition on column `oz_db_stores`.`is_valid`.
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
	public static function new(): static
	{
		return new \OZONE\Core\Db\OZDbStoresQuery();
	}
}
