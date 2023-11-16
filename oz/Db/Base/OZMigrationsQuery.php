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
 * Class OZMigrationsQuery.
 *
 * @extends \Gobl\ORM\ORMTableQuery<\OZONE\Core\Db\OZMigration>
 *
 * @method $this whereIdIs(int|string $value)           Filters rows with `eq` condition on column `oz_migrations`.`id`.
 * @method $this whereIdIsNot(int|string $value)        Filters rows with `neq` condition on column `oz_migrations`.`id`.
 * @method $this whereIdIsLt(int|string $value)         Filters rows with `lt` condition on column `oz_migrations`.`id`.
 * @method $this whereIdIsLte(int|string $value)        Filters rows with `lte` condition on column `oz_migrations`.`id`.
 * @method $this whereIdIsGt(int|string $value)         Filters rows with `gt` condition on column `oz_migrations`.`id`.
 * @method $this whereIdIsGte(int|string $value)        Filters rows with `gte` condition on column `oz_migrations`.`id`.
 * @method $this whereIdIsLike(string $value)           Filters rows with `like` condition on column `oz_migrations`.`id`.
 * @method $this whereIdIsNotLike(string $value)        Filters rows with `not_like` condition on column `oz_migrations`.`id`.
 * @method $this whereIdIsIn(array $value)              Filters rows with `in` condition on column `oz_migrations`.`id`.
 * @method $this whereIdIsNotIn(array $value)           Filters rows with `not_in` condition on column `oz_migrations`.`id`.
 * @method $this whereVersionIs(int $value)             Filters rows with `eq` condition on column `oz_migrations`.`version`.
 * @method $this whereVersionIsNot(int $value)          Filters rows with `neq` condition on column `oz_migrations`.`version`.
 * @method $this whereVersionIsLt(int $value)           Filters rows with `lt` condition on column `oz_migrations`.`version`.
 * @method $this whereVersionIsLte(int $value)          Filters rows with `lte` condition on column `oz_migrations`.`version`.
 * @method $this whereVersionIsGt(int $value)           Filters rows with `gt` condition on column `oz_migrations`.`version`.
 * @method $this whereVersionIsGte(int $value)          Filters rows with `gte` condition on column `oz_migrations`.`version`.
 * @method $this whereVersionIsLike(string $value)      Filters rows with `like` condition on column `oz_migrations`.`version`.
 * @method $this whereVersionIsNotLike(string $value)   Filters rows with `not_like` condition on column `oz_migrations`.`version`.
 * @method $this whereVersionIsIn(array $value)         Filters rows with `in` condition on column `oz_migrations`.`version`.
 * @method $this whereVersionIsNotIn(array $value)      Filters rows with `not_in` condition on column `oz_migrations`.`version`.
 * @method $this whereCreatedAtIs(int|string $value)    Filters rows with `eq` condition on column `oz_migrations`.`created_at`.
 * @method $this whereCreatedAtIsNot(int|string $value) Filters rows with `neq` condition on column `oz_migrations`.`created_at`.
 * @method $this whereCreatedAtIsLt(int|string $value)  Filters rows with `lt` condition on column `oz_migrations`.`created_at`.
 * @method $this whereCreatedAtIsLte(int|string $value) Filters rows with `lte` condition on column `oz_migrations`.`created_at`.
 * @method $this whereCreatedAtIsGt(int|string $value)  Filters rows with `gt` condition on column `oz_migrations`.`created_at`.
 * @method $this whereCreatedAtIsGte(int|string $value) Filters rows with `gte` condition on column `oz_migrations`.`created_at`.
 * @method $this whereCreatedAtIsLike(string $value)    Filters rows with `like` condition on column `oz_migrations`.`created_at`.
 * @method $this whereCreatedAtIsNotLike(string $value) Filters rows with `not_like` condition on column `oz_migrations`.`created_at`.
 * @method $this whereCreatedAtIsIn(array $value)       Filters rows with `in` condition on column `oz_migrations`.`created_at`.
 * @method $this whereCreatedAtIsNotIn(array $value)    Filters rows with `not_in` condition on column `oz_migrations`.`created_at`.
 * @method $this whereUpdatedAtIs(int|string $value)    Filters rows with `eq` condition on column `oz_migrations`.`updated_at`.
 * @method $this whereUpdatedAtIsNot(int|string $value) Filters rows with `neq` condition on column `oz_migrations`.`updated_at`.
 * @method $this whereUpdatedAtIsLt(int|string $value)  Filters rows with `lt` condition on column `oz_migrations`.`updated_at`.
 * @method $this whereUpdatedAtIsLte(int|string $value) Filters rows with `lte` condition on column `oz_migrations`.`updated_at`.
 * @method $this whereUpdatedAtIsGt(int|string $value)  Filters rows with `gt` condition on column `oz_migrations`.`updated_at`.
 * @method $this whereUpdatedAtIsGte(int|string $value) Filters rows with `gte` condition on column `oz_migrations`.`updated_at`.
 * @method $this whereUpdatedAtIsLike(string $value)    Filters rows with `like` condition on column `oz_migrations`.`updated_at`.
 * @method $this whereUpdatedAtIsNotLike(string $value) Filters rows with `not_like` condition on column `oz_migrations`.`updated_at`.
 * @method $this whereUpdatedAtIsIn(array $value)       Filters rows with `in` condition on column `oz_migrations`.`updated_at`.
 * @method $this whereUpdatedAtIsNotIn(array $value)    Filters rows with `not_in` condition on column `oz_migrations`.`updated_at`.
 */
abstract class OZMigrationsQuery extends \Gobl\ORM\ORMTableQuery
{
	/**
	 * OZMigrationsQuery constructor.
	 */
	public function __construct()
	{
		parent::__construct(
			\OZONE\Core\Db\OZMigration::TABLE_NAMESPACE,
			\OZONE\Core\Db\OZMigration::TABLE_NAME
		);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @return static
	 */
	public static function new(): static
	{
		return new \OZONE\Core\Db\OZMigrationsQuery();
	}
}
