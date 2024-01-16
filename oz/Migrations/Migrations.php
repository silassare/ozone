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

namespace OZONE\Core\Migrations;

use Gobl\CRUD\Exceptions\CRUDException;
use Gobl\DBAL\Db as GoblDb;
use Gobl\DBAL\Diff\Diff;
use Gobl\DBAL\Interfaces\MigrationInterface;
use Gobl\Exceptions\GoblException;
use Gobl\ORM\Exceptions\ORMException;
use OZONE\Core\App\Db;
use OZONE\Core\App\Settings;
use OZONE\Core\Cache\CacheManager;
use OZONE\Core\Db\OZMigration;
use OZONE\Core\Db\OZMigrationsQuery;
use OZONE\Core\Exceptions\RuntimeException;
use OZONE\Core\Migrations\Events\MigrationAfterRun;
use OZONE\Core\Migrations\Events\MigrationBeforeRun;
use OZONE\Core\Migrations\Events\MigrationCreated;
use OZONE\Core\Utils\Random;
use Throwable;

/**
 * Class Migrations.
 */
final class Migrations
{
	public const DB_NOT_INSTALLED_VERSION = 0;
	public const FIRST_VERSION            = 1;

	/**
	 * Migrations constructor.
	 */
	public function __construct() {}

	/**
	 * Gets the database migrations state.
	 */
	public static function getState(): MigrationsState
	{
		$src_version = self::getSourceCodeDbVersion();
		$db_version  = self::getCurrentDbVersion();

		if (self::DB_NOT_INSTALLED_VERSION === $db_version) {
			return MigrationsState::NOT_INSTALLED;
		}

		if ($db_version === $src_version) {
			return MigrationsState::INSTALLED;
		}

		if ($db_version < $src_version) {
			return MigrationsState::PENDING;
		}

		return MigrationsState::ROLLBACK;
	}

	/**
	 * Gets the database version supported by the source code.
	 *
	 * @return int
	 */
	public static function getSourceCodeDbVersion(): int
	{
		return Settings::get('oz.db.migrations', 'OZ_MIGRATION_VERSION', self::DB_NOT_INSTALLED_VERSION);
	}

	/**
	 * Gets the current database version.
	 *
	 * @return int
	 */
	public static function getCurrentDbVersion(): int
	{
		return self::cache()->factory('db_version', static function () {
			try {
				$qb    = new OZMigrationsQuery();
				$found = $qb->find(1)->fetchClass();

				if ($found) {
					return $found->getVersion();
				}
			} catch (Throwable $t) {
				oz_trace('Failed to get current database version.', null, $t);
			}

			return self::DB_NOT_INSTALLED_VERSION;
		})->get();
	}

	/**
	 * Runs a given migration.
	 *
	 * @param MigrationInterface $migration
	 *
	 * @throws CRUDException
	 * @throws GoblException
	 * @throws ORMException
	 */
	public function run(MigrationInterface $migration): void
	{
		(new MigrationBeforeRun($migration, false))->dispatch();

		$query = \trim($migration->up());
		if ($query) {
			db()->executeMulti($query);
		}

		$this->setCurrentDbVersion($migration->getVersion());

		(new MigrationAfterRun($migration, false))->dispatch();
	}

	/**
	 * Rolls back a given migration.
	 *
	 * @param MigrationInterface $migration
	 *
	 * @throws CRUDException
	 * @throws GoblException
	 * @throws ORMException
	 */
	public function rollbackMigration(MigrationInterface $migration): void
	{
		$version  = self::DB_NOT_INSTALLED_VERSION;
		$previous = $this->getPreviousMigration($migration->getVersion());

		if ($previous) {
			$version = $previous->getVersion();
		}

		(new MigrationBeforeRun($migration, true))->dispatch();

		$query = \trim($migration->down());

		if ($query) {
			db()->executeMulti($query);
		}

		$this->setCurrentDbVersion($version);

		(new MigrationAfterRun($migration, true))->dispatch();
	}

	/**
	 * Create a new migration.
	 *
	 * The {@see $force} parameter is useful when the diff algorithm fails to detect changes but some changes are made.
	 * Like for this case where the `min` and `max` values changes doesn't affect the SQL query generated:
	 *
	 * ```
	 * $nameV1 = [
	 *  'type' => 'string',
	 *  'min'  => 10,
	 *  'max'  => 20,
	 * ];
	 *
	 * $nameV2 = [
	 *  'type' => 'string',
	 *  'min'  => 5,
	 *  'max'  => 30,
	 * ];
	 * ```
	 *
	 * @param bool $force if true, a migration file will be created even if there are no changes
	 *
	 * @return null|string
	 */
	public function create(bool $force = false): ?string
	{
		$fm        = app()->getMigrationsDir();
		$latest    = $this->getLatestMigration();
		$db_actual = db();
		$config    = $db_actual->getConfig();

		$db_from = GoblDb::newInstanceOf($db_actual->getType(), $config);
		$db_to   = GoblDb::newInstanceOf($db_actual->getType(), $config);

		Db::loadDevelopmentSchemaTo($db_to);
		$db_to->lock();

		if ($latest) {
			$db_from->ns('Migrations')
				->schema($latest->getSchema());
			$version = $latest->getVersion() + 1;
		} else {
			$version = self::FIRST_VERSION;
		}

		$diff = new Diff($db_from, $db_to);

		if ($force || $diff->hasChanges()) {
			$outfile = $fm->resolve(\sprintf('%s.php', Random::fileName('migration')));

			$fm->wf($outfile, (string) $diff->generateMigrationFile($version));

			// clear cache
			self::clearCache();

			(new MigrationCreated($version))->dispatch();

			return $outfile;
		}

		return null;
	}

	/**
	 * Clears the migrations cache.
	 *
	 * @return bool
	 */
	public static function clearCache(): bool
	{
		return self::cache()->clear();
	}

	/**
	 * Gets all migrations.
	 *
	 * @return array
	 */
	public function migrations(): array
	{
		return self::cache()->factory('migrations', function () {
			$fm = app()->getMigrationsDir();

			$filter     = $fm->filter()
				->isFile()
				->isReadable()
				->name('#\.php$#');
			$migrations = [];

			$duplicates = [];

			foreach ($filter->find() as $file) {
				$path      = $file->getPathname();
				$migration = require $path;

				if ($migration instanceof MigrationInterface) {
					$version = $migration->getVersion();

					if (isset($duplicates[$version])) {
						throw new RuntimeException(
							\sprintf(
								'Duplicate migration version "%s" found in "%s" and "%s"',
								$version,
								$duplicates[$version],
								$path
							)
						);
					}

					$duplicates[$migration->getVersion()] = $file;
					$migrations[]                         = $migration;
				} else {
					throw new RuntimeException(
						\sprintf(
							'Invalid migration file "%s", it must return an instance of "%s" not "%s"',
							$file,
							MigrationInterface::class,
							\get_debug_type($migration),
						)
					);
				}
			}

			\usort(
				$migrations,
				static fn (MigrationInterface $a, MigrationInterface $b) => $a->getVersion() <=> $b->getVersion()
			);

			return $migrations;
		})
			->get();
	}

	/**
	 * Gets the latest migration.
	 *
	 * @return null|\Gobl\DBAL\Interfaces\MigrationInterface
	 */
	public function getLatestMigration(): ?MigrationInterface
	{
		$migrations = $this->migrations();
		$latest     = \end($migrations);

		return $latest instanceof MigrationInterface ? $latest : null;
	}

	/**
	 * Checks if there are pending migrations.
	 *
	 * @return bool
	 */
	public function hasPendingMigrations(): bool
	{
		$latest = $this->getLatestMigration();
		if ($latest) {
			return self::getCurrentDbVersion() < $latest->getVersion();
		}

		return false;
	}

	/**
	 * Gets the pending migrations.
	 *
	 * @return MigrationInterface[]
	 */
	public function getPendingMigrations(): array
	{
		$pending         = [];
		$current_version = self::getCurrentDbVersion();
		$migrations      = $this->migrations();

		foreach ($migrations as $migration) {
			if ($migration->getVersion() > $current_version) {
				$pending[] = $migration;
			}
		}

		return $pending;
	}

	/**
	 * Gets a migration by its version.
	 *
	 * @param int $version
	 *
	 * @return null|\Gobl\DBAL\Interfaces\MigrationInterface
	 */
	public function getMigration(int $version): ?MigrationInterface
	{
		$migrations = $this->migrations();

		foreach ($migrations as $migration) {
			if ($migration->getVersion() === $version) {
				return $migration;
			}
		}

		return null;
	}

	/**
	 * Gets the previous migration.
	 *
	 * @param int $version
	 *
	 * @return null|\Gobl\DBAL\Interfaces\MigrationInterface
	 */
	public function getPreviousMigration(int $version): ?MigrationInterface
	{
		$migrations = $this->migrations();
		$previous   = null;

		foreach ($migrations as $migration) {
			if ($migration->getVersion() === $version) {
				return $previous;
			}

			$previous = $migration;
		}

		return null;
	}

	/**
	 * Gets the migrations between two versions.
	 *
	 * @param int $from_version
	 * @param int $to_version
	 *
	 * @return MigrationInterface[]
	 */
	public function getMigrationBetween(int $from_version, int $to_version): array
	{
		$migrations = $this->migrations();
		$between    = [];

		foreach ($migrations as $migration) {
			if ($migration->getVersion() >= $from_version && $migration->getVersion() <= $to_version) {
				$between[] = $migration;
			}
		}

		return $between;
	}

	/**
	 * Gets the next migration.
	 *
	 * @param int $version
	 *
	 * @return null|\Gobl\DBAL\Interfaces\MigrationInterface
	 */
	public function getNextMigration(int $version): ?MigrationInterface
	{
		$migrations = $this->migrations();
		$next       = null;

		foreach ($migrations as $migration) {
			if ($next) {
				return $migration;
			}

			if ($migration->getVersion() === $version) {
				$next = $migration;
			}
		}

		return null;
	}

	/**
	 * Gets the migrations cache.
	 *
	 * @return CacheManager
	 */
	private static function cache(): CacheManager
	{
		return CacheManager::runtime(self::class);
	}

	/**
	 * Sets the current database version.
	 *
	 * @param int $version
	 *
	 * @throws CRUDException
	 * @throws GoblException
	 * @throws ORMException
	 */
	private function setCurrentDbVersion(int $version): void
	{
		if (self::DB_NOT_INSTALLED_VERSION !== $version) {
			$qb    = new OZMigrationsQuery();
			$found = $qb->find()->fetchClass();
			if ($found) {
				$found->setVersion($version)
					->save();
			} else {
				OZMigration::new()
					->setVersion($version)
					->setUpdatedAT(\time())
					->save();
			}
		}

		self::clearCache();
		Settings::set('oz.db.migrations', 'OZ_MIGRATION_VERSION', $version);
	}
}
