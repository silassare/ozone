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
 * Class OZRolesQuery.
 *
 * @extends \Gobl\ORM\ORMTableQuery<\OZONE\Core\Db\OZRole>
 *
 * @method $this whereIdIs(int|string $value)           Filters rows with `eq` condition on column `oz_roles`.`id`.
 * @method $this whereIdIsNot(int|string $value)        Filters rows with `neq` condition on column `oz_roles`.`id`.
 * @method $this whereIdIsLt(int|string $value)         Filters rows with `lt` condition on column `oz_roles`.`id`.
 * @method $this whereIdIsLte(int|string $value)        Filters rows with `lte` condition on column `oz_roles`.`id`.
 * @method $this whereIdIsGt(int|string $value)         Filters rows with `gt` condition on column `oz_roles`.`id`.
 * @method $this whereIdIsGte(int|string $value)        Filters rows with `gte` condition on column `oz_roles`.`id`.
 * @method $this whereIdIsLike(string $value)           Filters rows with `like` condition on column `oz_roles`.`id`.
 * @method $this whereIdIsNotLike(string $value)        Filters rows with `not_like` condition on column `oz_roles`.`id`.
 * @method $this whereIdIsIn(array $value)              Filters rows with `in` condition on column `oz_roles`.`id`.
 * @method $this whereIdIsNotIn(array $value)           Filters rows with `not_in` condition on column `oz_roles`.`id`.
 * @method $this whereUserIdIs(int|string $value)       Filters rows with `eq` condition on column `oz_roles`.`user_id`.
 * @method $this whereUserIdIsNot(int|string $value)    Filters rows with `neq` condition on column `oz_roles`.`user_id`.
 * @method $this whereUserIdIsLt(int|string $value)     Filters rows with `lt` condition on column `oz_roles`.`user_id`.
 * @method $this whereUserIdIsLte(int|string $value)    Filters rows with `lte` condition on column `oz_roles`.`user_id`.
 * @method $this whereUserIdIsGt(int|string $value)     Filters rows with `gt` condition on column `oz_roles`.`user_id`.
 * @method $this whereUserIdIsGte(int|string $value)    Filters rows with `gte` condition on column `oz_roles`.`user_id`.
 * @method $this whereUserIdIsLike(string $value)       Filters rows with `like` condition on column `oz_roles`.`user_id`.
 * @method $this whereUserIdIsNotLike(string $value)    Filters rows with `not_like` condition on column `oz_roles`.`user_id`.
 * @method $this whereUserIdIsIn(array $value)          Filters rows with `in` condition on column `oz_roles`.`user_id`.
 * @method $this whereUserIdIsNotIn(array $value)       Filters rows with `not_in` condition on column `oz_roles`.`user_id`.
 * @method $this whereNameIs(string $value)             Filters rows with `eq` condition on column `oz_roles`.`name`.
 * @method $this whereNameIsNot(string $value)          Filters rows with `neq` condition on column `oz_roles`.`name`.
 * @method $this whereNameIsLt(string $value)           Filters rows with `lt` condition on column `oz_roles`.`name`.
 * @method $this whereNameIsLte(string $value)          Filters rows with `lte` condition on column `oz_roles`.`name`.
 * @method $this whereNameIsGt(string $value)           Filters rows with `gt` condition on column `oz_roles`.`name`.
 * @method $this whereNameIsGte(string $value)          Filters rows with `gte` condition on column `oz_roles`.`name`.
 * @method $this whereNameIsLike(string $value)         Filters rows with `like` condition on column `oz_roles`.`name`.
 * @method $this whereNameIsNotLike(string $value)      Filters rows with `not_like` condition on column `oz_roles`.`name`.
 * @method $this whereNameIsIn(array $value)            Filters rows with `in` condition on column `oz_roles`.`name`.
 * @method $this whereNameIsNotIn(array $value)         Filters rows with `not_in` condition on column `oz_roles`.`name`.
 * @method $this whereDataIs(array $value)              Filters rows with `eq` condition on column `oz_roles`.`data`.
 * @method $this whereDataIsNot(array $value)           Filters rows with `neq` condition on column `oz_roles`.`data`.
 * @method $this whereDataIsLike(string $value)         Filters rows with `like` condition on column `oz_roles`.`data`.
 * @method $this whereDataIsNotLike(string $value)      Filters rows with `not_like` condition on column `oz_roles`.`data`.
 * @method $this whereCreatedAtIs(int|string $value)    Filters rows with `eq` condition on column `oz_roles`.`created_at`.
 * @method $this whereCreatedAtIsNot(int|string $value) Filters rows with `neq` condition on column `oz_roles`.`created_at`.
 * @method $this whereCreatedAtIsLt(int|string $value)  Filters rows with `lt` condition on column `oz_roles`.`created_at`.
 * @method $this whereCreatedAtIsLte(int|string $value) Filters rows with `lte` condition on column `oz_roles`.`created_at`.
 * @method $this whereCreatedAtIsGt(int|string $value)  Filters rows with `gt` condition on column `oz_roles`.`created_at`.
 * @method $this whereCreatedAtIsGte(int|string $value) Filters rows with `gte` condition on column `oz_roles`.`created_at`.
 * @method $this whereCreatedAtIsLike(string $value)    Filters rows with `like` condition on column `oz_roles`.`created_at`.
 * @method $this whereCreatedAtIsNotLike(string $value) Filters rows with `not_like` condition on column `oz_roles`.`created_at`.
 * @method $this whereCreatedAtIsIn(array $value)       Filters rows with `in` condition on column `oz_roles`.`created_at`.
 * @method $this whereCreatedAtIsNotIn(array $value)    Filters rows with `not_in` condition on column `oz_roles`.`created_at`.
 * @method $this whereUpdatedAtIs(int|string $value)    Filters rows with `eq` condition on column `oz_roles`.`updated_at`.
 * @method $this whereUpdatedAtIsNot(int|string $value) Filters rows with `neq` condition on column `oz_roles`.`updated_at`.
 * @method $this whereUpdatedAtIsLt(int|string $value)  Filters rows with `lt` condition on column `oz_roles`.`updated_at`.
 * @method $this whereUpdatedAtIsLte(int|string $value) Filters rows with `lte` condition on column `oz_roles`.`updated_at`.
 * @method $this whereUpdatedAtIsGt(int|string $value)  Filters rows with `gt` condition on column `oz_roles`.`updated_at`.
 * @method $this whereUpdatedAtIsGte(int|string $value) Filters rows with `gte` condition on column `oz_roles`.`updated_at`.
 * @method $this whereUpdatedAtIsLike(string $value)    Filters rows with `like` condition on column `oz_roles`.`updated_at`.
 * @method $this whereUpdatedAtIsNotLike(string $value) Filters rows with `not_like` condition on column `oz_roles`.`updated_at`.
 * @method $this whereUpdatedAtIsIn(array $value)       Filters rows with `in` condition on column `oz_roles`.`updated_at`.
 * @method $this whereUpdatedAtIsNotIn(array $value)    Filters rows with `not_in` condition on column `oz_roles`.`updated_at`.
 * @method $this whereIsValidIs(bool $value)            Filters rows with `eq` condition on column `oz_roles`.`is_valid`.
 * @method $this whereIsValidIsNot(bool $value)         Filters rows with `neq` condition on column `oz_roles`.`is_valid`.
 * @method $this whereIsNotValid()                      Filters rows with `is_false` condition on column `oz_roles`.`is_valid`.
 * @method $this whereIsValid()                         Filters rows with `is_true` condition on column `oz_roles`.`is_valid`.
 */
abstract class OZRolesQuery extends \Gobl\ORM\ORMTableQuery
{
	/**
	 * OZRolesQuery constructor.
	 */
	public function __construct()
	{
		parent::__construct(
			\OZONE\Core\Db\OZRole::TABLE_NAMESPACE,
			\OZONE\Core\Db\OZRole::TABLE_NAME
		);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @return static
	 */
	public static function createInstance(): static
	{
		return new \OZONE\Core\Db\OZRolesQuery();
	}
}
