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
 * Class OZCountriesQuery.
 *
 * @extends \Gobl\ORM\ORMTableQuery<\OZONE\Core\Db\OZCountry>
 *
 * @method $this whereCc2Is(string $value)                Filters rows with `eq` condition on column `oz_countries`.`cc2`.
 * @method $this whereCc2IsNot(string $value)             Filters rows with `neq` condition on column `oz_countries`.`cc2`.
 * @method $this whereCc2IsLt(string $value)              Filters rows with `lt` condition on column `oz_countries`.`cc2`.
 * @method $this whereCc2IsLte(string $value)             Filters rows with `lte` condition on column `oz_countries`.`cc2`.
 * @method $this whereCc2IsGt(string $value)              Filters rows with `gt` condition on column `oz_countries`.`cc2`.
 * @method $this whereCc2IsGte(string $value)             Filters rows with `gte` condition on column `oz_countries`.`cc2`.
 * @method $this whereCc2IsLike(string $value)            Filters rows with `like` condition on column `oz_countries`.`cc2`.
 * @method $this whereCc2IsNotLike(string $value)         Filters rows with `not_like` condition on column `oz_countries`.`cc2`.
 * @method $this whereCc2IsIn(array $value)               Filters rows with `in` condition on column `oz_countries`.`cc2`.
 * @method $this whereCc2IsNotIn(array $value)            Filters rows with `not_in` condition on column `oz_countries`.`cc2`.
 * @method $this whereCallingCodeIs(string $value)        Filters rows with `eq` condition on column `oz_countries`.`calling_code`.
 * @method $this whereCallingCodeIsNot(string $value)     Filters rows with `neq` condition on column `oz_countries`.`calling_code`.
 * @method $this whereCallingCodeIsLt(string $value)      Filters rows with `lt` condition on column `oz_countries`.`calling_code`.
 * @method $this whereCallingCodeIsLte(string $value)     Filters rows with `lte` condition on column `oz_countries`.`calling_code`.
 * @method $this whereCallingCodeIsGt(string $value)      Filters rows with `gt` condition on column `oz_countries`.`calling_code`.
 * @method $this whereCallingCodeIsGte(string $value)     Filters rows with `gte` condition on column `oz_countries`.`calling_code`.
 * @method $this whereCallingCodeIsLike(string $value)    Filters rows with `like` condition on column `oz_countries`.`calling_code`.
 * @method $this whereCallingCodeIsNotLike(string $value) Filters rows with `not_like` condition on column `oz_countries`.`calling_code`.
 * @method $this whereCallingCodeIsIn(array $value)       Filters rows with `in` condition on column `oz_countries`.`calling_code`.
 * @method $this whereCallingCodeIsNotIn(array $value)    Filters rows with `not_in` condition on column `oz_countries`.`calling_code`.
 * @method $this whereNameIs(string $value)               Filters rows with `eq` condition on column `oz_countries`.`name`.
 * @method $this whereNameIsNot(string $value)            Filters rows with `neq` condition on column `oz_countries`.`name`.
 * @method $this whereNameIsLt(string $value)             Filters rows with `lt` condition on column `oz_countries`.`name`.
 * @method $this whereNameIsLte(string $value)            Filters rows with `lte` condition on column `oz_countries`.`name`.
 * @method $this whereNameIsGt(string $value)             Filters rows with `gt` condition on column `oz_countries`.`name`.
 * @method $this whereNameIsGte(string $value)            Filters rows with `gte` condition on column `oz_countries`.`name`.
 * @method $this whereNameIsLike(string $value)           Filters rows with `like` condition on column `oz_countries`.`name`.
 * @method $this whereNameIsNotLike(string $value)        Filters rows with `not_like` condition on column `oz_countries`.`name`.
 * @method $this whereNameIsIn(array $value)              Filters rows with `in` condition on column `oz_countries`.`name`.
 * @method $this whereNameIsNotIn(array $value)           Filters rows with `not_in` condition on column `oz_countries`.`name`.
 * @method $this whereNameRealIs(string $value)           Filters rows with `eq` condition on column `oz_countries`.`name_real`.
 * @method $this whereNameRealIsNot(string $value)        Filters rows with `neq` condition on column `oz_countries`.`name_real`.
 * @method $this whereNameRealIsLt(string $value)         Filters rows with `lt` condition on column `oz_countries`.`name_real`.
 * @method $this whereNameRealIsLte(string $value)        Filters rows with `lte` condition on column `oz_countries`.`name_real`.
 * @method $this whereNameRealIsGt(string $value)         Filters rows with `gt` condition on column `oz_countries`.`name_real`.
 * @method $this whereNameRealIsGte(string $value)        Filters rows with `gte` condition on column `oz_countries`.`name_real`.
 * @method $this whereNameRealIsLike(string $value)       Filters rows with `like` condition on column `oz_countries`.`name_real`.
 * @method $this whereNameRealIsNotLike(string $value)    Filters rows with `not_like` condition on column `oz_countries`.`name_real`.
 * @method $this whereNameRealIsIn(array $value)          Filters rows with `in` condition on column `oz_countries`.`name_real`.
 * @method $this whereNameRealIsNotIn(array $value)       Filters rows with `not_in` condition on column `oz_countries`.`name_real`.
 * @method $this whereDataIs(array $value)                Filters rows with `eq` condition on column `oz_countries`.`data`.
 * @method $this whereDataIsNot(array $value)             Filters rows with `neq` condition on column `oz_countries`.`data`.
 * @method $this whereDataIsLike(string $value)           Filters rows with `like` condition on column `oz_countries`.`data`.
 * @method $this whereDataIsNotLike(string $value)        Filters rows with `not_like` condition on column `oz_countries`.`data`.
 * @method $this whereCreatedAtIs(int|string $value)      Filters rows with `eq` condition on column `oz_countries`.`created_at`.
 * @method $this whereCreatedAtIsNot(int|string $value)   Filters rows with `neq` condition on column `oz_countries`.`created_at`.
 * @method $this whereCreatedAtIsLt(int|string $value)    Filters rows with `lt` condition on column `oz_countries`.`created_at`.
 * @method $this whereCreatedAtIsLte(int|string $value)   Filters rows with `lte` condition on column `oz_countries`.`created_at`.
 * @method $this whereCreatedAtIsGt(int|string $value)    Filters rows with `gt` condition on column `oz_countries`.`created_at`.
 * @method $this whereCreatedAtIsGte(int|string $value)   Filters rows with `gte` condition on column `oz_countries`.`created_at`.
 * @method $this whereCreatedAtIsLike(string $value)      Filters rows with `like` condition on column `oz_countries`.`created_at`.
 * @method $this whereCreatedAtIsNotLike(string $value)   Filters rows with `not_like` condition on column `oz_countries`.`created_at`.
 * @method $this whereCreatedAtIsIn(array $value)         Filters rows with `in` condition on column `oz_countries`.`created_at`.
 * @method $this whereCreatedAtIsNotIn(array $value)      Filters rows with `not_in` condition on column `oz_countries`.`created_at`.
 * @method $this whereUpdatedAtIs(int|string $value)      Filters rows with `eq` condition on column `oz_countries`.`updated_at`.
 * @method $this whereUpdatedAtIsNot(int|string $value)   Filters rows with `neq` condition on column `oz_countries`.`updated_at`.
 * @method $this whereUpdatedAtIsLt(int|string $value)    Filters rows with `lt` condition on column `oz_countries`.`updated_at`.
 * @method $this whereUpdatedAtIsLte(int|string $value)   Filters rows with `lte` condition on column `oz_countries`.`updated_at`.
 * @method $this whereUpdatedAtIsGt(int|string $value)    Filters rows with `gt` condition on column `oz_countries`.`updated_at`.
 * @method $this whereUpdatedAtIsGte(int|string $value)   Filters rows with `gte` condition on column `oz_countries`.`updated_at`.
 * @method $this whereUpdatedAtIsLike(string $value)      Filters rows with `like` condition on column `oz_countries`.`updated_at`.
 * @method $this whereUpdatedAtIsNotLike(string $value)   Filters rows with `not_like` condition on column `oz_countries`.`updated_at`.
 * @method $this whereUpdatedAtIsIn(array $value)         Filters rows with `in` condition on column `oz_countries`.`updated_at`.
 * @method $this whereUpdatedAtIsNotIn(array $value)      Filters rows with `not_in` condition on column `oz_countries`.`updated_at`.
 * @method $this whereIsValidIs(bool $value)              Filters rows with `eq` condition on column `oz_countries`.`is_valid`.
 * @method $this whereIsValidIsNot(bool $value)           Filters rows with `neq` condition on column `oz_countries`.`is_valid`.
 * @method $this whereIsNotValid()                        Filters rows with `is_false` condition on column `oz_countries`.`is_valid`.
 * @method $this whereIsValid()                           Filters rows with `is_true` condition on column `oz_countries`.`is_valid`.
 */
abstract class OZCountriesQuery extends \Gobl\ORM\ORMTableQuery
{
	/**
	 * OZCountriesQuery constructor.
	 */
	public function __construct()
	{
		parent::__construct(
			\OZONE\Core\Db\OZCountry::TABLE_NAMESPACE,
			\OZONE\Core\Db\OZCountry::TABLE_NAME
		);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @return static
	 */
	public static function new(): static
	{
		return new \OZONE\Core\Db\OZCountriesQuery();
	}
}
