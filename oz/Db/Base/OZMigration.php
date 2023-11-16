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
 * Class OZMigration.
 *
 * @property null|string $id         Getter for column `oz_migrations`.`id`.
 * @property int         $version    Getter for column `oz_migrations`.`version`.
 * @property string      $created_at Getter for column `oz_migrations`.`created_at`.
 * @property string      $updated_at Getter for column `oz_migrations`.`updated_at`.
 */
abstract class OZMigration extends \Gobl\ORM\ORMEntity
{
	public const TABLE_NAME      = 'oz_migrations';
	public const TABLE_NAMESPACE = 'OZONE\\Core\\Db';
	public const COL_ID          = 'migration_id';
	public const COL_VERSION     = 'migration_version';
	public const COL_CREATED_AT  = 'migration_created_at';
	public const COL_UPDATED_AT  = 'migration_updated_at';

	/**
	 * OZMigration constructor.
	 *
	 * @param bool $is_new true for new entity false for entity fetched
	 *                     from the database, default is true
	 * @param bool $strict Enable/disable strict mode
	 */
	public function __construct(bool $is_new = true, bool $strict = true)
	{
		parent::__construct(
			self::TABLE_NAMESPACE,
			self::TABLE_NAME,
			$is_new,
			$strict
		);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @return static
	 */
	public static function new(bool $is_new = true, bool $strict = true): static
	{
		return new \OZONE\Core\Db\OZMigration($is_new, $strict);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @return \OZONE\Core\Db\OZMigrationsCrud
	 */
	public static function crud(): \OZONE\Core\Db\OZMigrationsCrud
	{
		return \OZONE\Core\Db\OZMigrationsCrud::new();
	}

	/**
	 * {@inheritDoc}
	 *
	 * @return \OZONE\Core\Db\OZMigrationsController
	 */
	public static function ctrl(): \OZONE\Core\Db\OZMigrationsController
	{
		return \OZONE\Core\Db\OZMigrationsController::new();
	}

	/**
	 * {@inheritDoc}
	 *
	 * @return \OZONE\Core\Db\OZMigrationsQuery
	 */
	public static function qb(): \OZONE\Core\Db\OZMigrationsQuery
	{
		return \OZONE\Core\Db\OZMigrationsQuery::new();
	}

	/**
	 * {@inheritDoc}
	 *
	 * @return \OZONE\Core\Db\OZMigrationsResults
	 */
	public static function results(\Gobl\DBAL\Queries\QBSelect $query): \OZONE\Core\Db\OZMigrationsResults
	{
		return \OZONE\Core\Db\OZMigrationsResults::new($query);
	}

	/**
	 * {@inheritDoc}
	 */
	public static function table(): \Gobl\DBAL\Table
	{
		return \Gobl\ORM\ORM::table(static::TABLE_NAMESPACE, static::TABLE_NAME);
	}

	/**
	 * Getter for column `oz_migrations`.`id`.
	 *
	 * @return null|string
	 */
	public function getID(): null|string
	{
		return $this->id;
	}

	/**
	 * Setter for column `oz_migrations`.`id`.
	 *
	 * @param null|int|string $id
	 *
	 * @return static
	 */
	public function setID(null|int|string $id): static
	{
		$this->id = $id;

		return $this;
	}

	/**
	 * Getter for column `oz_migrations`.`version`.
	 *
	 * @return int
	 */
	public function getVersion(): int
	{
		return $this->version;
	}

	/**
	 * Setter for column `oz_migrations`.`version`.
	 *
	 * @param int $version
	 *
	 * @return static
	 */
	public function setVersion(int $version): static
	{
		$this->version = $version;

		return $this;
	}

	/**
	 * Getter for column `oz_migrations`.`created_at`.
	 *
	 * @return string
	 */
	public function getCreatedAT(): string
	{
		return $this->created_at;
	}

	/**
	 * Setter for column `oz_migrations`.`created_at`.
	 *
	 * @param int|string $created_at
	 *
	 * @return static
	 */
	public function setCreatedAT(int|string $created_at): static
	{
		$this->created_at = $created_at;

		return $this;
	}

	/**
	 * Getter for column `oz_migrations`.`updated_at`.
	 *
	 * @return string
	 */
	public function getUpdatedAT(): string
	{
		return $this->updated_at;
	}

	/**
	 * Setter for column `oz_migrations`.`updated_at`.
	 *
	 * @param int|string $updated_at
	 *
	 * @return static
	 */
	public function setUpdatedAT(int|string $updated_at): static
	{
		$this->updated_at = $updated_at;

		return $this;
	}
}
