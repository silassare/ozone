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
 * Class OZAuthsQuery.
 *
 * @extends \Gobl\ORM\ORMTableQuery<\OZONE\Core\Db\OZAuth>
 *
 * @method $this whereRefIs(string $value)                                 Filters rows with `eq` condition on column `oz_auths`.`ref`.
 * @method $this whereRefIsNot(string $value)                              Filters rows with `neq` condition on column `oz_auths`.`ref`.
 * @method $this whereRefIsLt(string $value)                               Filters rows with `lt` condition on column `oz_auths`.`ref`.
 * @method $this whereRefIsLte(string $value)                              Filters rows with `lte` condition on column `oz_auths`.`ref`.
 * @method $this whereRefIsGt(string $value)                               Filters rows with `gt` condition on column `oz_auths`.`ref`.
 * @method $this whereRefIsGte(string $value)                              Filters rows with `gte` condition on column `oz_auths`.`ref`.
 * @method $this whereRefIsLike(string $value)                             Filters rows with `like` condition on column `oz_auths`.`ref`.
 * @method $this whereRefIsNotLike(string $value)                          Filters rows with `not_like` condition on column `oz_auths`.`ref`.
 * @method $this whereRefIsIn(array $value)                                Filters rows with `in` condition on column `oz_auths`.`ref`.
 * @method $this whereRefIsNotIn(array $value)                             Filters rows with `not_in` condition on column `oz_auths`.`ref`.
 * @method $this whereLabelIs(string $value)                               Filters rows with `eq` condition on column `oz_auths`.`label`.
 * @method $this whereLabelIsNot(string $value)                            Filters rows with `neq` condition on column `oz_auths`.`label`.
 * @method $this whereLabelIsLt(string $value)                             Filters rows with `lt` condition on column `oz_auths`.`label`.
 * @method $this whereLabelIsLte(string $value)                            Filters rows with `lte` condition on column `oz_auths`.`label`.
 * @method $this whereLabelIsGt(string $value)                             Filters rows with `gt` condition on column `oz_auths`.`label`.
 * @method $this whereLabelIsGte(string $value)                            Filters rows with `gte` condition on column `oz_auths`.`label`.
 * @method $this whereLabelIsLike(string $value)                           Filters rows with `like` condition on column `oz_auths`.`label`.
 * @method $this whereLabelIsNotLike(string $value)                        Filters rows with `not_like` condition on column `oz_auths`.`label`.
 * @method $this whereLabelIsIn(array $value)                              Filters rows with `in` condition on column `oz_auths`.`label`.
 * @method $this whereLabelIsNotIn(array $value)                           Filters rows with `not_in` condition on column `oz_auths`.`label`.
 * @method $this whereRefreshKeyIs(string $value)                          Filters rows with `eq` condition on column `oz_auths`.`refresh_key`.
 * @method $this whereRefreshKeyIsNot(string $value)                       Filters rows with `neq` condition on column `oz_auths`.`refresh_key`.
 * @method $this whereRefreshKeyIsLt(string $value)                        Filters rows with `lt` condition on column `oz_auths`.`refresh_key`.
 * @method $this whereRefreshKeyIsLte(string $value)                       Filters rows with `lte` condition on column `oz_auths`.`refresh_key`.
 * @method $this whereRefreshKeyIsGt(string $value)                        Filters rows with `gt` condition on column `oz_auths`.`refresh_key`.
 * @method $this whereRefreshKeyIsGte(string $value)                       Filters rows with `gte` condition on column `oz_auths`.`refresh_key`.
 * @method $this whereRefreshKeyIsLike(string $value)                      Filters rows with `like` condition on column `oz_auths`.`refresh_key`.
 * @method $this whereRefreshKeyIsNotLike(string $value)                   Filters rows with `not_like` condition on column `oz_auths`.`refresh_key`.
 * @method $this whereRefreshKeyIsIn(array $value)                         Filters rows with `in` condition on column `oz_auths`.`refresh_key`.
 * @method $this whereRefreshKeyIsNotIn(array $value)                      Filters rows with `not_in` condition on column `oz_auths`.`refresh_key`.
 * @method $this whereProviderIs(string $value)                            Filters rows with `eq` condition on column `oz_auths`.`provider`.
 * @method $this whereProviderIsNot(string $value)                         Filters rows with `neq` condition on column `oz_auths`.`provider`.
 * @method $this whereProviderIsLt(string $value)                          Filters rows with `lt` condition on column `oz_auths`.`provider`.
 * @method $this whereProviderIsLte(string $value)                         Filters rows with `lte` condition on column `oz_auths`.`provider`.
 * @method $this whereProviderIsGt(string $value)                          Filters rows with `gt` condition on column `oz_auths`.`provider`.
 * @method $this whereProviderIsGte(string $value)                         Filters rows with `gte` condition on column `oz_auths`.`provider`.
 * @method $this whereProviderIsLike(string $value)                        Filters rows with `like` condition on column `oz_auths`.`provider`.
 * @method $this whereProviderIsNotLike(string $value)                     Filters rows with `not_like` condition on column `oz_auths`.`provider`.
 * @method $this whereProviderIsIn(array $value)                           Filters rows with `in` condition on column `oz_auths`.`provider`.
 * @method $this whereProviderIsNotIn(array $value)                        Filters rows with `not_in` condition on column `oz_auths`.`provider`.
 * @method $this wherePayloadIs(array $value)                              Filters rows with `eq` condition on column `oz_auths`.`payload`.
 * @method $this wherePayloadIsNot(array $value)                           Filters rows with `neq` condition on column `oz_auths`.`payload`.
 * @method $this wherePayloadIsLike(string $value)                         Filters rows with `like` condition on column `oz_auths`.`payload`.
 * @method $this wherePayloadIsNotLike(string $value)                      Filters rows with `not_like` condition on column `oz_auths`.`payload`.
 * @method $this whereCodeHashIs(string $value)                            Filters rows with `eq` condition on column `oz_auths`.`code_hash`.
 * @method $this whereCodeHashIsNot(string $value)                         Filters rows with `neq` condition on column `oz_auths`.`code_hash`.
 * @method $this whereCodeHashIsLt(string $value)                          Filters rows with `lt` condition on column `oz_auths`.`code_hash`.
 * @method $this whereCodeHashIsLte(string $value)                         Filters rows with `lte` condition on column `oz_auths`.`code_hash`.
 * @method $this whereCodeHashIsGt(string $value)                          Filters rows with `gt` condition on column `oz_auths`.`code_hash`.
 * @method $this whereCodeHashIsGte(string $value)                         Filters rows with `gte` condition on column `oz_auths`.`code_hash`.
 * @method $this whereCodeHashIsLike(string $value)                        Filters rows with `like` condition on column `oz_auths`.`code_hash`.
 * @method $this whereCodeHashIsNotLike(string $value)                     Filters rows with `not_like` condition on column `oz_auths`.`code_hash`.
 * @method $this whereCodeHashIsIn(array $value)                           Filters rows with `in` condition on column `oz_auths`.`code_hash`.
 * @method $this whereCodeHashIsNotIn(array $value)                        Filters rows with `not_in` condition on column `oz_auths`.`code_hash`.
 * @method $this whereTokenHashIs(string $value)                           Filters rows with `eq` condition on column `oz_auths`.`token_hash`.
 * @method $this whereTokenHashIsNot(string $value)                        Filters rows with `neq` condition on column `oz_auths`.`token_hash`.
 * @method $this whereTokenHashIsLt(string $value)                         Filters rows with `lt` condition on column `oz_auths`.`token_hash`.
 * @method $this whereTokenHashIsLte(string $value)                        Filters rows with `lte` condition on column `oz_auths`.`token_hash`.
 * @method $this whereTokenHashIsGt(string $value)                         Filters rows with `gt` condition on column `oz_auths`.`token_hash`.
 * @method $this whereTokenHashIsGte(string $value)                        Filters rows with `gte` condition on column `oz_auths`.`token_hash`.
 * @method $this whereTokenHashIsLike(string $value)                       Filters rows with `like` condition on column `oz_auths`.`token_hash`.
 * @method $this whereTokenHashIsNotLike(string $value)                    Filters rows with `not_like` condition on column `oz_auths`.`token_hash`.
 * @method $this whereTokenHashIsIn(array $value)                          Filters rows with `in` condition on column `oz_auths`.`token_hash`.
 * @method $this whereTokenHashIsNotIn(array $value)                       Filters rows with `not_in` condition on column `oz_auths`.`token_hash`.
 * @method $this whereStateIs(\OZONE\Core\Auth\AuthState|string $value)    Filters rows with `eq` condition on column `oz_auths`.`state`.
 * @method $this whereStateIsNot(\OZONE\Core\Auth\AuthState|string $value) Filters rows with `neq` condition on column `oz_auths`.`state`.
 * @method $this whereStateIsLt(\OZONE\Core\Auth\AuthState|string $value)  Filters rows with `lt` condition on column `oz_auths`.`state`.
 * @method $this whereStateIsLte(\OZONE\Core\Auth\AuthState|string $value) Filters rows with `lte` condition on column `oz_auths`.`state`.
 * @method $this whereStateIsGt(\OZONE\Core\Auth\AuthState|string $value)  Filters rows with `gt` condition on column `oz_auths`.`state`.
 * @method $this whereStateIsGte(\OZONE\Core\Auth\AuthState|string $value) Filters rows with `gte` condition on column `oz_auths`.`state`.
 * @method $this whereStateIsLike(string $value)                           Filters rows with `like` condition on column `oz_auths`.`state`.
 * @method $this whereStateIsNotLike(string $value)                        Filters rows with `not_like` condition on column `oz_auths`.`state`.
 * @method $this whereStateIsIn(array $value)                              Filters rows with `in` condition on column `oz_auths`.`state`.
 * @method $this whereStateIsNotIn(array $value)                           Filters rows with `not_in` condition on column `oz_auths`.`state`.
 * @method $this whereTryMaxIs(int $value)                                 Filters rows with `eq` condition on column `oz_auths`.`try_max`.
 * @method $this whereTryMaxIsNot(int $value)                              Filters rows with `neq` condition on column `oz_auths`.`try_max`.
 * @method $this whereTryMaxIsLt(int $value)                               Filters rows with `lt` condition on column `oz_auths`.`try_max`.
 * @method $this whereTryMaxIsLte(int $value)                              Filters rows with `lte` condition on column `oz_auths`.`try_max`.
 * @method $this whereTryMaxIsGt(int $value)                               Filters rows with `gt` condition on column `oz_auths`.`try_max`.
 * @method $this whereTryMaxIsGte(int $value)                              Filters rows with `gte` condition on column `oz_auths`.`try_max`.
 * @method $this whereTryMaxIsLike(string $value)                          Filters rows with `like` condition on column `oz_auths`.`try_max`.
 * @method $this whereTryMaxIsNotLike(string $value)                       Filters rows with `not_like` condition on column `oz_auths`.`try_max`.
 * @method $this whereTryMaxIsIn(array $value)                             Filters rows with `in` condition on column `oz_auths`.`try_max`.
 * @method $this whereTryMaxIsNotIn(array $value)                          Filters rows with `not_in` condition on column `oz_auths`.`try_max`.
 * @method $this whereTryCountIs(int $value)                               Filters rows with `eq` condition on column `oz_auths`.`try_count`.
 * @method $this whereTryCountIsNot(int $value)                            Filters rows with `neq` condition on column `oz_auths`.`try_count`.
 * @method $this whereTryCountIsLt(int $value)                             Filters rows with `lt` condition on column `oz_auths`.`try_count`.
 * @method $this whereTryCountIsLte(int $value)                            Filters rows with `lte` condition on column `oz_auths`.`try_count`.
 * @method $this whereTryCountIsGt(int $value)                             Filters rows with `gt` condition on column `oz_auths`.`try_count`.
 * @method $this whereTryCountIsGte(int $value)                            Filters rows with `gte` condition on column `oz_auths`.`try_count`.
 * @method $this whereTryCountIsLike(string $value)                        Filters rows with `like` condition on column `oz_auths`.`try_count`.
 * @method $this whereTryCountIsNotLike(string $value)                     Filters rows with `not_like` condition on column `oz_auths`.`try_count`.
 * @method $this whereTryCountIsIn(array $value)                           Filters rows with `in` condition on column `oz_auths`.`try_count`.
 * @method $this whereTryCountIsNotIn(array $value)                        Filters rows with `not_in` condition on column `oz_auths`.`try_count`.
 * @method $this whereLifetimeIs(int $value)                               Filters rows with `eq` condition on column `oz_auths`.`lifetime`.
 * @method $this whereLifetimeIsNot(int $value)                            Filters rows with `neq` condition on column `oz_auths`.`lifetime`.
 * @method $this whereLifetimeIsLt(int $value)                             Filters rows with `lt` condition on column `oz_auths`.`lifetime`.
 * @method $this whereLifetimeIsLte(int $value)                            Filters rows with `lte` condition on column `oz_auths`.`lifetime`.
 * @method $this whereLifetimeIsGt(int $value)                             Filters rows with `gt` condition on column `oz_auths`.`lifetime`.
 * @method $this whereLifetimeIsGte(int $value)                            Filters rows with `gte` condition on column `oz_auths`.`lifetime`.
 * @method $this whereLifetimeIsLike(string $value)                        Filters rows with `like` condition on column `oz_auths`.`lifetime`.
 * @method $this whereLifetimeIsNotLike(string $value)                     Filters rows with `not_like` condition on column `oz_auths`.`lifetime`.
 * @method $this whereLifetimeIsIn(array $value)                           Filters rows with `in` condition on column `oz_auths`.`lifetime`.
 * @method $this whereLifetimeIsNotIn(array $value)                        Filters rows with `not_in` condition on column `oz_auths`.`lifetime`.
 * @method $this whereExpireIs(int|string $value)                          Filters rows with `eq` condition on column `oz_auths`.`expire`.
 * @method $this whereExpireIsNot(int|string $value)                       Filters rows with `neq` condition on column `oz_auths`.`expire`.
 * @method $this whereExpireIsLt(int|string $value)                        Filters rows with `lt` condition on column `oz_auths`.`expire`.
 * @method $this whereExpireIsLte(int|string $value)                       Filters rows with `lte` condition on column `oz_auths`.`expire`.
 * @method $this whereExpireIsGt(int|string $value)                        Filters rows with `gt` condition on column `oz_auths`.`expire`.
 * @method $this whereExpireIsGte(int|string $value)                       Filters rows with `gte` condition on column `oz_auths`.`expire`.
 * @method $this whereExpireIsLike(string $value)                          Filters rows with `like` condition on column `oz_auths`.`expire`.
 * @method $this whereExpireIsNotLike(string $value)                       Filters rows with `not_like` condition on column `oz_auths`.`expire`.
 * @method $this whereExpireIsIn(array $value)                             Filters rows with `in` condition on column `oz_auths`.`expire`.
 * @method $this whereExpireIsNotIn(array $value)                          Filters rows with `not_in` condition on column `oz_auths`.`expire`.
 * @method $this whereOptionsIs(array $value)                              Filters rows with `eq` condition on column `oz_auths`.`options`.
 * @method $this whereOptionsIsNot(array $value)                           Filters rows with `neq` condition on column `oz_auths`.`options`.
 * @method $this whereOptionsIsLike(string $value)                         Filters rows with `like` condition on column `oz_auths`.`options`.
 * @method $this whereOptionsIsNotLike(string $value)                      Filters rows with `not_like` condition on column `oz_auths`.`options`.
 * @method $this whereCreatedAtIs(int|string $value)                       Filters rows with `eq` condition on column `oz_auths`.`created_at`.
 * @method $this whereCreatedAtIsNot(int|string $value)                    Filters rows with `neq` condition on column `oz_auths`.`created_at`.
 * @method $this whereCreatedAtIsLt(int|string $value)                     Filters rows with `lt` condition on column `oz_auths`.`created_at`.
 * @method $this whereCreatedAtIsLte(int|string $value)                    Filters rows with `lte` condition on column `oz_auths`.`created_at`.
 * @method $this whereCreatedAtIsGt(int|string $value)                     Filters rows with `gt` condition on column `oz_auths`.`created_at`.
 * @method $this whereCreatedAtIsGte(int|string $value)                    Filters rows with `gte` condition on column `oz_auths`.`created_at`.
 * @method $this whereCreatedAtIsLike(string $value)                       Filters rows with `like` condition on column `oz_auths`.`created_at`.
 * @method $this whereCreatedAtIsNotLike(string $value)                    Filters rows with `not_like` condition on column `oz_auths`.`created_at`.
 * @method $this whereCreatedAtIsIn(array $value)                          Filters rows with `in` condition on column `oz_auths`.`created_at`.
 * @method $this whereCreatedAtIsNotIn(array $value)                       Filters rows with `not_in` condition on column `oz_auths`.`created_at`.
 * @method $this whereUpdatedAtIs(int|string $value)                       Filters rows with `eq` condition on column `oz_auths`.`updated_at`.
 * @method $this whereUpdatedAtIsNot(int|string $value)                    Filters rows with `neq` condition on column `oz_auths`.`updated_at`.
 * @method $this whereUpdatedAtIsLt(int|string $value)                     Filters rows with `lt` condition on column `oz_auths`.`updated_at`.
 * @method $this whereUpdatedAtIsLte(int|string $value)                    Filters rows with `lte` condition on column `oz_auths`.`updated_at`.
 * @method $this whereUpdatedAtIsGt(int|string $value)                     Filters rows with `gt` condition on column `oz_auths`.`updated_at`.
 * @method $this whereUpdatedAtIsGte(int|string $value)                    Filters rows with `gte` condition on column `oz_auths`.`updated_at`.
 * @method $this whereUpdatedAtIsLike(string $value)                       Filters rows with `like` condition on column `oz_auths`.`updated_at`.
 * @method $this whereUpdatedAtIsNotLike(string $value)                    Filters rows with `not_like` condition on column `oz_auths`.`updated_at`.
 * @method $this whereUpdatedAtIsIn(array $value)                          Filters rows with `in` condition on column `oz_auths`.`updated_at`.
 * @method $this whereUpdatedAtIsNotIn(array $value)                       Filters rows with `not_in` condition on column `oz_auths`.`updated_at`.
 * @method $this whereIsValidIs(bool $value)                               Filters rows with `eq` condition on column `oz_auths`.`is_valid`.
 * @method $this whereIsValidIsNot(bool $value)                            Filters rows with `neq` condition on column `oz_auths`.`is_valid`.
 * @method $this whereIsNotValid()                                         Filters rows with `is_false` condition on column `oz_auths`.`is_valid`.
 * @method $this whereIsValid()                                            Filters rows with `is_true` condition on column `oz_auths`.`is_valid`.
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
}
