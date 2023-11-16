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
 * Class OZSessionsQuery.
 *
 * @extends \Gobl\ORM\ORMTableQuery<\OZONE\Core\Db\OZSession>
 *
 * @method $this whereIdIs(string $value)                      Filters rows with `eq` condition on column `oz_sessions`.`id`.
 * @method $this whereIdIsNot(string $value)                   Filters rows with `neq` condition on column `oz_sessions`.`id`.
 * @method $this whereIdIsLt(string $value)                    Filters rows with `lt` condition on column `oz_sessions`.`id`.
 * @method $this whereIdIsLte(string $value)                   Filters rows with `lte` condition on column `oz_sessions`.`id`.
 * @method $this whereIdIsGt(string $value)                    Filters rows with `gt` condition on column `oz_sessions`.`id`.
 * @method $this whereIdIsGte(string $value)                   Filters rows with `gte` condition on column `oz_sessions`.`id`.
 * @method $this whereIdIsLike(string $value)                  Filters rows with `like` condition on column `oz_sessions`.`id`.
 * @method $this whereIdIsNotLike(string $value)               Filters rows with `not_like` condition on column `oz_sessions`.`id`.
 * @method $this whereIdIsIn(array $value)                     Filters rows with `in` condition on column `oz_sessions`.`id`.
 * @method $this whereIdIsNotIn(array $value)                  Filters rows with `not_in` condition on column `oz_sessions`.`id`.
 * @method $this whereUserIdIs(int|string $value)              Filters rows with `eq` condition on column `oz_sessions`.`user_id`.
 * @method $this whereUserIdIsNot(int|string $value)           Filters rows with `neq` condition on column `oz_sessions`.`user_id`.
 * @method $this whereUserIdIsLt(int|string $value)            Filters rows with `lt` condition on column `oz_sessions`.`user_id`.
 * @method $this whereUserIdIsLte(int|string $value)           Filters rows with `lte` condition on column `oz_sessions`.`user_id`.
 * @method $this whereUserIdIsGt(int|string $value)            Filters rows with `gt` condition on column `oz_sessions`.`user_id`.
 * @method $this whereUserIdIsGte(int|string $value)           Filters rows with `gte` condition on column `oz_sessions`.`user_id`.
 * @method $this whereUserIdIsLike(string $value)              Filters rows with `like` condition on column `oz_sessions`.`user_id`.
 * @method $this whereUserIdIsNotLike(string $value)           Filters rows with `not_like` condition on column `oz_sessions`.`user_id`.
 * @method $this whereUserIdIsNull()                           Filters rows with `is_null` condition on column `oz_sessions`.`user_id`.
 * @method $this whereUserIdIsNotNull()                        Filters rows with `is_not_null` condition on column `oz_sessions`.`user_id`.
 * @method $this whereUserIdIsIn(array $value)                 Filters rows with `in` condition on column `oz_sessions`.`user_id`.
 * @method $this whereUserIdIsNotIn(array $value)              Filters rows with `not_in` condition on column `oz_sessions`.`user_id`.
 * @method $this whereRequestSourceKeyIs(string $value)        Filters rows with `eq` condition on column `oz_sessions`.`request_source_key`.
 * @method $this whereRequestSourceKeyIsNot(string $value)     Filters rows with `neq` condition on column `oz_sessions`.`request_source_key`.
 * @method $this whereRequestSourceKeyIsLt(string $value)      Filters rows with `lt` condition on column `oz_sessions`.`request_source_key`.
 * @method $this whereRequestSourceKeyIsLte(string $value)     Filters rows with `lte` condition on column `oz_sessions`.`request_source_key`.
 * @method $this whereRequestSourceKeyIsGt(string $value)      Filters rows with `gt` condition on column `oz_sessions`.`request_source_key`.
 * @method $this whereRequestSourceKeyIsGte(string $value)     Filters rows with `gte` condition on column `oz_sessions`.`request_source_key`.
 * @method $this whereRequestSourceKeyIsLike(string $value)    Filters rows with `like` condition on column `oz_sessions`.`request_source_key`.
 * @method $this whereRequestSourceKeyIsNotLike(string $value) Filters rows with `not_like` condition on column `oz_sessions`.`request_source_key`.
 * @method $this whereRequestSourceKeyIsIn(array $value)       Filters rows with `in` condition on column `oz_sessions`.`request_source_key`.
 * @method $this whereRequestSourceKeyIsNotIn(array $value)    Filters rows with `not_in` condition on column `oz_sessions`.`request_source_key`.
 * @method $this whereExpireIs(int|string $value)              Filters rows with `eq` condition on column `oz_sessions`.`expire`.
 * @method $this whereExpireIsNot(int|string $value)           Filters rows with `neq` condition on column `oz_sessions`.`expire`.
 * @method $this whereExpireIsLt(int|string $value)            Filters rows with `lt` condition on column `oz_sessions`.`expire`.
 * @method $this whereExpireIsLte(int|string $value)           Filters rows with `lte` condition on column `oz_sessions`.`expire`.
 * @method $this whereExpireIsGt(int|string $value)            Filters rows with `gt` condition on column `oz_sessions`.`expire`.
 * @method $this whereExpireIsGte(int|string $value)           Filters rows with `gte` condition on column `oz_sessions`.`expire`.
 * @method $this whereExpireIsLike(string $value)              Filters rows with `like` condition on column `oz_sessions`.`expire`.
 * @method $this whereExpireIsNotLike(string $value)           Filters rows with `not_like` condition on column `oz_sessions`.`expire`.
 * @method $this whereExpireIsIn(array $value)                 Filters rows with `in` condition on column `oz_sessions`.`expire`.
 * @method $this whereExpireIsNotIn(array $value)              Filters rows with `not_in` condition on column `oz_sessions`.`expire`.
 * @method $this whereLastSeenIs(int|string $value)            Filters rows with `eq` condition on column `oz_sessions`.`last_seen`.
 * @method $this whereLastSeenIsNot(int|string $value)         Filters rows with `neq` condition on column `oz_sessions`.`last_seen`.
 * @method $this whereLastSeenIsLt(int|string $value)          Filters rows with `lt` condition on column `oz_sessions`.`last_seen`.
 * @method $this whereLastSeenIsLte(int|string $value)         Filters rows with `lte` condition on column `oz_sessions`.`last_seen`.
 * @method $this whereLastSeenIsGt(int|string $value)          Filters rows with `gt` condition on column `oz_sessions`.`last_seen`.
 * @method $this whereLastSeenIsGte(int|string $value)         Filters rows with `gte` condition on column `oz_sessions`.`last_seen`.
 * @method $this whereLastSeenIsLike(string $value)            Filters rows with `like` condition on column `oz_sessions`.`last_seen`.
 * @method $this whereLastSeenIsNotLike(string $value)         Filters rows with `not_like` condition on column `oz_sessions`.`last_seen`.
 * @method $this whereLastSeenIsIn(array $value)               Filters rows with `in` condition on column `oz_sessions`.`last_seen`.
 * @method $this whereLastSeenIsNotIn(array $value)            Filters rows with `not_in` condition on column `oz_sessions`.`last_seen`.
 * @method $this whereDataIs(array $value)                     Filters rows with `eq` condition on column `oz_sessions`.`data`.
 * @method $this whereDataIsNot(array $value)                  Filters rows with `neq` condition on column `oz_sessions`.`data`.
 * @method $this whereDataIsLike(string $value)                Filters rows with `like` condition on column `oz_sessions`.`data`.
 * @method $this whereDataIsNotLike(string $value)             Filters rows with `not_like` condition on column `oz_sessions`.`data`.
 * @method $this whereIsValidIs(bool $value)                   Filters rows with `eq` condition on column `oz_sessions`.`is_valid`.
 * @method $this whereIsValidIsNot(bool $value)                Filters rows with `neq` condition on column `oz_sessions`.`is_valid`.
 * @method $this whereIsNotValid()                             Filters rows with `is_false` condition on column `oz_sessions`.`is_valid`.
 * @method $this whereIsValid()                                Filters rows with `is_true` condition on column `oz_sessions`.`is_valid`.
 * @method $this whereCreatedAtIs(int|string $value)           Filters rows with `eq` condition on column `oz_sessions`.`created_at`.
 * @method $this whereCreatedAtIsNot(int|string $value)        Filters rows with `neq` condition on column `oz_sessions`.`created_at`.
 * @method $this whereCreatedAtIsLt(int|string $value)         Filters rows with `lt` condition on column `oz_sessions`.`created_at`.
 * @method $this whereCreatedAtIsLte(int|string $value)        Filters rows with `lte` condition on column `oz_sessions`.`created_at`.
 * @method $this whereCreatedAtIsGt(int|string $value)         Filters rows with `gt` condition on column `oz_sessions`.`created_at`.
 * @method $this whereCreatedAtIsGte(int|string $value)        Filters rows with `gte` condition on column `oz_sessions`.`created_at`.
 * @method $this whereCreatedAtIsLike(string $value)           Filters rows with `like` condition on column `oz_sessions`.`created_at`.
 * @method $this whereCreatedAtIsNotLike(string $value)        Filters rows with `not_like` condition on column `oz_sessions`.`created_at`.
 * @method $this whereCreatedAtIsIn(array $value)              Filters rows with `in` condition on column `oz_sessions`.`created_at`.
 * @method $this whereCreatedAtIsNotIn(array $value)           Filters rows with `not_in` condition on column `oz_sessions`.`created_at`.
 * @method $this whereUpdatedAtIs(int|string $value)           Filters rows with `eq` condition on column `oz_sessions`.`updated_at`.
 * @method $this whereUpdatedAtIsNot(int|string $value)        Filters rows with `neq` condition on column `oz_sessions`.`updated_at`.
 * @method $this whereUpdatedAtIsLt(int|string $value)         Filters rows with `lt` condition on column `oz_sessions`.`updated_at`.
 * @method $this whereUpdatedAtIsLte(int|string $value)        Filters rows with `lte` condition on column `oz_sessions`.`updated_at`.
 * @method $this whereUpdatedAtIsGt(int|string $value)         Filters rows with `gt` condition on column `oz_sessions`.`updated_at`.
 * @method $this whereUpdatedAtIsGte(int|string $value)        Filters rows with `gte` condition on column `oz_sessions`.`updated_at`.
 * @method $this whereUpdatedAtIsLike(string $value)           Filters rows with `like` condition on column `oz_sessions`.`updated_at`.
 * @method $this whereUpdatedAtIsNotLike(string $value)        Filters rows with `not_like` condition on column `oz_sessions`.`updated_at`.
 * @method $this whereUpdatedAtIsIn(array $value)              Filters rows with `in` condition on column `oz_sessions`.`updated_at`.
 * @method $this whereUpdatedAtIsNotIn(array $value)           Filters rows with `not_in` condition on column `oz_sessions`.`updated_at`.
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
	public static function new(): static
	{
		return new \OZONE\Core\Db\OZSessionsQuery();
	}
}
