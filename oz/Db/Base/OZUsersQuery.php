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
 * Class OZUsersQuery.
 *
 * @extends \Gobl\ORM\ORMTableQuery<\OZONE\Core\Db\OZUser>
 *
 * @method $this whereIdIs(int|string $value)           Filters rows with `eq` condition on column `oz_users`.`id`.
 * @method $this whereIdIsNot(int|string $value)        Filters rows with `neq` condition on column `oz_users`.`id`.
 * @method $this whereIdIsLt(int|string $value)         Filters rows with `lt` condition on column `oz_users`.`id`.
 * @method $this whereIdIsLte(int|string $value)        Filters rows with `lte` condition on column `oz_users`.`id`.
 * @method $this whereIdIsGt(int|string $value)         Filters rows with `gt` condition on column `oz_users`.`id`.
 * @method $this whereIdIsGte(int|string $value)        Filters rows with `gte` condition on column `oz_users`.`id`.
 * @method $this whereIdIsLike(string $value)           Filters rows with `like` condition on column `oz_users`.`id`.
 * @method $this whereIdIsNotLike(string $value)        Filters rows with `not_like` condition on column `oz_users`.`id`.
 * @method $this whereIdIsIn(array $value)              Filters rows with `in` condition on column `oz_users`.`id`.
 * @method $this whereIdIsNotIn(array $value)           Filters rows with `not_in` condition on column `oz_users`.`id`.
 * @method $this wherePhoneIs(string $value)            Filters rows with `eq` condition on column `oz_users`.`phone`.
 * @method $this wherePhoneIsNot(string $value)         Filters rows with `neq` condition on column `oz_users`.`phone`.
 * @method $this wherePhoneIsLt(string $value)          Filters rows with `lt` condition on column `oz_users`.`phone`.
 * @method $this wherePhoneIsLte(string $value)         Filters rows with `lte` condition on column `oz_users`.`phone`.
 * @method $this wherePhoneIsGt(string $value)          Filters rows with `gt` condition on column `oz_users`.`phone`.
 * @method $this wherePhoneIsGte(string $value)         Filters rows with `gte` condition on column `oz_users`.`phone`.
 * @method $this wherePhoneIsLike(string $value)        Filters rows with `like` condition on column `oz_users`.`phone`.
 * @method $this wherePhoneIsNotLike(string $value)     Filters rows with `not_like` condition on column `oz_users`.`phone`.
 * @method $this wherePhoneIsNull()                     Filters rows with `is_null` condition on column `oz_users`.`phone`.
 * @method $this wherePhoneIsNotNull()                  Filters rows with `is_not_null` condition on column `oz_users`.`phone`.
 * @method $this wherePhoneIsIn(array $value)           Filters rows with `in` condition on column `oz_users`.`phone`.
 * @method $this wherePhoneIsNotIn(array $value)        Filters rows with `not_in` condition on column `oz_users`.`phone`.
 * @method $this whereEmailIs(string $value)            Filters rows with `eq` condition on column `oz_users`.`email`.
 * @method $this whereEmailIsNot(string $value)         Filters rows with `neq` condition on column `oz_users`.`email`.
 * @method $this whereEmailIsLt(string $value)          Filters rows with `lt` condition on column `oz_users`.`email`.
 * @method $this whereEmailIsLte(string $value)         Filters rows with `lte` condition on column `oz_users`.`email`.
 * @method $this whereEmailIsGt(string $value)          Filters rows with `gt` condition on column `oz_users`.`email`.
 * @method $this whereEmailIsGte(string $value)         Filters rows with `gte` condition on column `oz_users`.`email`.
 * @method $this whereEmailIsLike(string $value)        Filters rows with `like` condition on column `oz_users`.`email`.
 * @method $this whereEmailIsNotLike(string $value)     Filters rows with `not_like` condition on column `oz_users`.`email`.
 * @method $this whereEmailIsIn(array $value)           Filters rows with `in` condition on column `oz_users`.`email`.
 * @method $this whereEmailIsNotIn(array $value)        Filters rows with `not_in` condition on column `oz_users`.`email`.
 * @method $this wherePassIs(string $value)             Filters rows with `eq` condition on column `oz_users`.`pass`.
 * @method $this wherePassIsNot(string $value)          Filters rows with `neq` condition on column `oz_users`.`pass`.
 * @method $this wherePassIsLt(string $value)           Filters rows with `lt` condition on column `oz_users`.`pass`.
 * @method $this wherePassIsLte(string $value)          Filters rows with `lte` condition on column `oz_users`.`pass`.
 * @method $this wherePassIsGt(string $value)           Filters rows with `gt` condition on column `oz_users`.`pass`.
 * @method $this wherePassIsGte(string $value)          Filters rows with `gte` condition on column `oz_users`.`pass`.
 * @method $this wherePassIsLike(string $value)         Filters rows with `like` condition on column `oz_users`.`pass`.
 * @method $this wherePassIsNotLike(string $value)      Filters rows with `not_like` condition on column `oz_users`.`pass`.
 * @method $this wherePassIsIn(array $value)            Filters rows with `in` condition on column `oz_users`.`pass`.
 * @method $this wherePassIsNotIn(array $value)         Filters rows with `not_in` condition on column `oz_users`.`pass`.
 * @method $this whereNameIs(string $value)             Filters rows with `eq` condition on column `oz_users`.`name`.
 * @method $this whereNameIsNot(string $value)          Filters rows with `neq` condition on column `oz_users`.`name`.
 * @method $this whereNameIsLt(string $value)           Filters rows with `lt` condition on column `oz_users`.`name`.
 * @method $this whereNameIsLte(string $value)          Filters rows with `lte` condition on column `oz_users`.`name`.
 * @method $this whereNameIsGt(string $value)           Filters rows with `gt` condition on column `oz_users`.`name`.
 * @method $this whereNameIsGte(string $value)          Filters rows with `gte` condition on column `oz_users`.`name`.
 * @method $this whereNameIsLike(string $value)         Filters rows with `like` condition on column `oz_users`.`name`.
 * @method $this whereNameIsNotLike(string $value)      Filters rows with `not_like` condition on column `oz_users`.`name`.
 * @method $this whereNameIsIn(array $value)            Filters rows with `in` condition on column `oz_users`.`name`.
 * @method $this whereNameIsNotIn(array $value)         Filters rows with `not_in` condition on column `oz_users`.`name`.
 * @method $this whereGenderIs(string $value)           Filters rows with `eq` condition on column `oz_users`.`gender`.
 * @method $this whereGenderIsNot(string $value)        Filters rows with `neq` condition on column `oz_users`.`gender`.
 * @method $this whereGenderIsLt(string $value)         Filters rows with `lt` condition on column `oz_users`.`gender`.
 * @method $this whereGenderIsLte(string $value)        Filters rows with `lte` condition on column `oz_users`.`gender`.
 * @method $this whereGenderIsGt(string $value)         Filters rows with `gt` condition on column `oz_users`.`gender`.
 * @method $this whereGenderIsGte(string $value)        Filters rows with `gte` condition on column `oz_users`.`gender`.
 * @method $this whereGenderIsLike(string $value)       Filters rows with `like` condition on column `oz_users`.`gender`.
 * @method $this whereGenderIsNotLike(string $value)    Filters rows with `not_like` condition on column `oz_users`.`gender`.
 * @method $this whereGenderIsIn(array $value)          Filters rows with `in` condition on column `oz_users`.`gender`.
 * @method $this whereGenderIsNotIn(array $value)       Filters rows with `not_in` condition on column `oz_users`.`gender`.
 * @method $this whereBirthDateIs(int|string $value)    Filters rows with `eq` condition on column `oz_users`.`birth_date`.
 * @method $this whereBirthDateIsNot(int|string $value) Filters rows with `neq` condition on column `oz_users`.`birth_date`.
 * @method $this whereBirthDateIsLt(int|string $value)  Filters rows with `lt` condition on column `oz_users`.`birth_date`.
 * @method $this whereBirthDateIsLte(int|string $value) Filters rows with `lte` condition on column `oz_users`.`birth_date`.
 * @method $this whereBirthDateIsGt(int|string $value)  Filters rows with `gt` condition on column `oz_users`.`birth_date`.
 * @method $this whereBirthDateIsGte(int|string $value) Filters rows with `gte` condition on column `oz_users`.`birth_date`.
 * @method $this whereBirthDateIsLike(string $value)    Filters rows with `like` condition on column `oz_users`.`birth_date`.
 * @method $this whereBirthDateIsNotLike(string $value) Filters rows with `not_like` condition on column `oz_users`.`birth_date`.
 * @method $this whereBirthDateIsIn(array $value)       Filters rows with `in` condition on column `oz_users`.`birth_date`.
 * @method $this whereBirthDateIsNotIn(array $value)    Filters rows with `not_in` condition on column `oz_users`.`birth_date`.
 * @method $this wherePicIs(string $value)              Filters rows with `eq` condition on column `oz_users`.`pic`.
 * @method $this wherePicIsNot(string $value)           Filters rows with `neq` condition on column `oz_users`.`pic`.
 * @method $this wherePicIsLt(string $value)            Filters rows with `lt` condition on column `oz_users`.`pic`.
 * @method $this wherePicIsLte(string $value)           Filters rows with `lte` condition on column `oz_users`.`pic`.
 * @method $this wherePicIsGt(string $value)            Filters rows with `gt` condition on column `oz_users`.`pic`.
 * @method $this wherePicIsGte(string $value)           Filters rows with `gte` condition on column `oz_users`.`pic`.
 * @method $this wherePicIsLike(string $value)          Filters rows with `like` condition on column `oz_users`.`pic`.
 * @method $this wherePicIsNotLike(string $value)       Filters rows with `not_like` condition on column `oz_users`.`pic`.
 * @method $this wherePicIsNull()                       Filters rows with `is_null` condition on column `oz_users`.`pic`.
 * @method $this wherePicIsNotNull()                    Filters rows with `is_not_null` condition on column `oz_users`.`pic`.
 * @method $this wherePicIsIn(array $value)             Filters rows with `in` condition on column `oz_users`.`pic`.
 * @method $this wherePicIsNotIn(array $value)          Filters rows with `not_in` condition on column `oz_users`.`pic`.
 * @method $this whereCc2Is(string $value)              Filters rows with `eq` condition on column `oz_users`.`cc2`.
 * @method $this whereCc2IsNot(string $value)           Filters rows with `neq` condition on column `oz_users`.`cc2`.
 * @method $this whereCc2IsLt(string $value)            Filters rows with `lt` condition on column `oz_users`.`cc2`.
 * @method $this whereCc2IsLte(string $value)           Filters rows with `lte` condition on column `oz_users`.`cc2`.
 * @method $this whereCc2IsGt(string $value)            Filters rows with `gt` condition on column `oz_users`.`cc2`.
 * @method $this whereCc2IsGte(string $value)           Filters rows with `gte` condition on column `oz_users`.`cc2`.
 * @method $this whereCc2IsLike(string $value)          Filters rows with `like` condition on column `oz_users`.`cc2`.
 * @method $this whereCc2IsNotLike(string $value)       Filters rows with `not_like` condition on column `oz_users`.`cc2`.
 * @method $this whereCc2IsIn(array $value)             Filters rows with `in` condition on column `oz_users`.`cc2`.
 * @method $this whereCc2IsNotIn(array $value)          Filters rows with `not_in` condition on column `oz_users`.`cc2`.
 * @method $this whereDataIs(array $value)              Filters rows with `eq` condition on column `oz_users`.`data`.
 * @method $this whereDataIsNot(array $value)           Filters rows with `neq` condition on column `oz_users`.`data`.
 * @method $this whereDataIsLike(string $value)         Filters rows with `like` condition on column `oz_users`.`data`.
 * @method $this whereDataIsNotLike(string $value)      Filters rows with `not_like` condition on column `oz_users`.`data`.
 * @method $this whereCreatedAtIs(int|string $value)    Filters rows with `eq` condition on column `oz_users`.`created_at`.
 * @method $this whereCreatedAtIsNot(int|string $value) Filters rows with `neq` condition on column `oz_users`.`created_at`.
 * @method $this whereCreatedAtIsLt(int|string $value)  Filters rows with `lt` condition on column `oz_users`.`created_at`.
 * @method $this whereCreatedAtIsLte(int|string $value) Filters rows with `lte` condition on column `oz_users`.`created_at`.
 * @method $this whereCreatedAtIsGt(int|string $value)  Filters rows with `gt` condition on column `oz_users`.`created_at`.
 * @method $this whereCreatedAtIsGte(int|string $value) Filters rows with `gte` condition on column `oz_users`.`created_at`.
 * @method $this whereCreatedAtIsLike(string $value)    Filters rows with `like` condition on column `oz_users`.`created_at`.
 * @method $this whereCreatedAtIsNotLike(string $value) Filters rows with `not_like` condition on column `oz_users`.`created_at`.
 * @method $this whereCreatedAtIsIn(array $value)       Filters rows with `in` condition on column `oz_users`.`created_at`.
 * @method $this whereCreatedAtIsNotIn(array $value)    Filters rows with `not_in` condition on column `oz_users`.`created_at`.
 * @method $this whereUpdatedAtIs(int|string $value)    Filters rows with `eq` condition on column `oz_users`.`updated_at`.
 * @method $this whereUpdatedAtIsNot(int|string $value) Filters rows with `neq` condition on column `oz_users`.`updated_at`.
 * @method $this whereUpdatedAtIsLt(int|string $value)  Filters rows with `lt` condition on column `oz_users`.`updated_at`.
 * @method $this whereUpdatedAtIsLte(int|string $value) Filters rows with `lte` condition on column `oz_users`.`updated_at`.
 * @method $this whereUpdatedAtIsGt(int|string $value)  Filters rows with `gt` condition on column `oz_users`.`updated_at`.
 * @method $this whereUpdatedAtIsGte(int|string $value) Filters rows with `gte` condition on column `oz_users`.`updated_at`.
 * @method $this whereUpdatedAtIsLike(string $value)    Filters rows with `like` condition on column `oz_users`.`updated_at`.
 * @method $this whereUpdatedAtIsNotLike(string $value) Filters rows with `not_like` condition on column `oz_users`.`updated_at`.
 * @method $this whereUpdatedAtIsIn(array $value)       Filters rows with `in` condition on column `oz_users`.`updated_at`.
 * @method $this whereUpdatedAtIsNotIn(array $value)    Filters rows with `not_in` condition on column `oz_users`.`updated_at`.
 * @method $this whereIsValidIs(bool $value)            Filters rows with `eq` condition on column `oz_users`.`is_valid`.
 * @method $this whereIsValidIsNot(bool $value)         Filters rows with `neq` condition on column `oz_users`.`is_valid`.
 * @method $this whereIsNotValid()                      Filters rows with `is_false` condition on column `oz_users`.`is_valid`.
 * @method $this whereIsValid()                         Filters rows with `is_true` condition on column `oz_users`.`is_valid`.
 */
abstract class OZUsersQuery extends \Gobl\ORM\ORMTableQuery
{
	/**
	 * OZUsersQuery constructor.
	 */
	public function __construct()
	{
		parent::__construct(
			\OZONE\Core\Db\OZUser::TABLE_NAMESPACE,
			\OZONE\Core\Db\OZUser::TABLE_NAME
		);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @return static
	 */
	public static function createInstance(): static
	{
		return new \OZONE\Core\Db\OZUsersQuery();
	}
}
