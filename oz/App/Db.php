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

namespace OZONE\Core\App;

use Gobl\DBAL\Builders\NamespaceBuilder;
use Gobl\DBAL\Db as GoblDb;
use Gobl\DBAL\DbConfig;
use Gobl\DBAL\Exceptions\DBALException;
use Gobl\DBAL\Interfaces\MigrationInterface;
use Gobl\DBAL\Interfaces\RDBMSInterface;
use Gobl\DBAL\Types\Utils\TypeUtils;
use Gobl\Gobl;
use OZONE\Core\Columns\TypeProvider;
use OZONE\Core\Exceptions\RuntimeException;
use OZONE\Core\FS\FilesManager;
use OZONE\Core\Hooks\Events\DbReadyHook;
use OZONE\Core\Hooks\Events\DbSchemaCollectHook;
use OZONE\Core\Hooks\Events\DbSchemaReadyHook;
use OZONE\Core\Migrations\Migrations;
use OZONE\Core\OZone;
use OZONE\Core\Plugins\Plugins;
use OZONE\Core\Scopes\Interfaces\ScopeInterface;
use Throwable;

/**
 * Class Db.
 */
final class Db
{
	private static ?RDBMSInterface $db = null;

	/**
	 * Initialize the database.
	 *
	 * This method should be called only after all boot hooks are registered.
	 */
	public static function init(): RDBMSInterface
	{
		if (!self::$db) {
			OZone::dieIfBootHookReceiversAreNotNotified(
				\sprintf(
					'%s should be called only after all boot hooks are registered.' .
					' A call to %s before all boot hooks are registered may cause '
					. 'some boot hooks to not be notified and lead to inconsistent state.',
					__METHOD__,
					__METHOD__
				)
			);

			try {
				$migration = self::initStepPrepare();

				self::$db = self::new($migration);

				self::initStepRegister(null !== $migration);
			} catch (Throwable $t) {
				throw new RuntimeException('Unable to initialize database.', null, $t);
			}
		}

		return self::$db;
	}

	/**
	 * Gets instance.
	 */
	public static function get(): RDBMSInterface
	{
		return self::init();
	}

	/**
	 * Creates a new RDBMS instance.
	 *
	 * The instance is created using the configuration defined in "oz.db".
	 * If a migration is provided, the migration schema is loaded.
	 *
	 * @param null|MigrationInterface $migration The migration instance to autoload
	 *
	 * @return RDBMSInterface
	 */
	public static function new(?MigrationInterface $migration = null): RDBMSInterface
	{
		$config           = Settings::load('oz.db');
		$rdbms_type       = $config['OZ_DB_RDBMS'];
		$migration_config = $migration?->getConfigs();

		$db_config = new DbConfig([
			'db_table_prefix' => $migration_config['db_table_prefix'] ?? Settings::get('oz.db', 'OZ_DB_TABLE_PREFIX'),
			'db_host'         => $config['OZ_DB_HOST'],
			'db_name'         => $config['OZ_DB_NAME'],
			'db_user'         => $config['OZ_DB_USER'],
			'db_pass'         => $config['OZ_DB_PASS'],
			'db_charset'      => $migration_config['db_charset'] ?? $config['OZ_DB_CHARSET'],
			'db_collate'      => $migration_config['db_collate'] ?? $config['OZ_DB_COLLATE'],
		]);

		try {
			$db = GoblDb::newInstanceOf($rdbms_type, $db_config);

			if ($migration) {
				$db->loadSchema($migration->getSchema());
			}

			return $db;
		} catch (Throwable $t) {
			throw new RuntimeException(
				\sprintf('Unable to create a new RDBMS instance of type "%s" as defined in "oz.db".', $rdbms_type),
				null,
				$t
			);
		}
	}

	/**
	 * Creates a new RDBMS instance with the actual development schema loaded.
	 *
	 * @return RDBMSInterface
	 */
	public static function dev(): RDBMSInterface
	{
		$db = self::new();

		self::loadDevSchemaInto($db);

		return $db;
	}

	/**
	 * Returns the OZone db namespace.
	 */
	public static function getOZoneDbNamespace(): string
	{
		return Plugins::ozone()->getDbNamespace();
	}

	/**
	 * Returns the project db namespace.
	 */
	public static function getProjectDbNamespace(): string
	{
		$project_namespace = Settings::get('oz.config', 'OZ_PROJECT_NAMESPACE');

		return \sprintf('%s\Db', $project_namespace ?? 'NO_PROJECT');
	}

	/**
	 * Returns an instance of the files manager with the scope set to the db folder.
	 *
	 * If no scope is provided, the current app scope is used.
	 *
	 * @param null|ScopeInterface $scope
	 *
	 * @return FilesManager
	 */
	public static function dir(?ScopeInterface $scope = null): FilesManager
	{
		if (null === $scope) {
			$scope = app();
		}

		return $scope->getSourcesDir()->cd('Db', true);
	}

	/**
	 * Collects all tables from the project and O'Zone into a given db instance.
	 *
	 * @param RDBMSInterface $db
	 */
	public static function loadDevSchemaInto(RDBMSInterface $db): void
	{
		/** @var callable(NamespaceBuilder):void $factory */
		$factory = include OZ_OZONE_DIR . 'oz_default' . DS . 'oz_schema.php';
		$factory($db->ns(self::getOZoneDbNamespace()));

		(new DbSchemaCollectHook($db))->dispatch();

		// the project schema is the last to be loaded as its
		// may require some tables from OZone or plugins
		$db->ns(self::getProjectDbNamespace())
			->schema(Settings::load('oz.db.schema'));

		(new DbSchemaReadyHook($db))->dispatch();
	}

	/**
	 * Initialize step prepare.
	 *
	 * @return null|MigrationInterface
	 */
	private static function initStepPrepare(): ?MigrationInterface
	{
		Gobl::setProjectCacheDir(
			app()
				->getCacheDir()
				->getRoot()
		);

		TypeUtils::addTypeProvider(new TypeProvider());

		$mg      = new Migrations();
		$version = $mg::getSourceCodeDbVersion();

		if (Migrations::DB_NOT_INSTALLED_VERSION === $version) {
			return null;
		}

		$current = $mg->getMigration($version);

		if (!$current) {
			throw (new RuntimeException(
				\sprintf(
					'Unable to find migration, using db version "%s" defined in settings.',
					$version
				)
			))->suspectConfig('oz.db.migrations', 'OZ_MIGRATION_VERSION');
		}

		return $current;
	}

	/**
	 * Initialize step register.
	 *
	 * @throws DBALException
	 */
	private static function initStepRegister(bool $migration_loaded): void
	{
		if ($migration_loaded) {
			(new DbSchemaReadyHook(self::$db))->dispatch();
		} else {
			self::loadDevSchemaInto(self::$db);
		}

		self::$db
			->ns(self::getOZoneDbNamespace())
			->enableORM(self::dir(Plugins::ozone()->getScope())->getRoot());

		self::$db
			->ns(self::getProjectDbNamespace())
			->enableORM(self::dir()->getRoot());

		self::$db->lock();

		(new DbReadyHook(self::$db))->dispatch();
	}
}
