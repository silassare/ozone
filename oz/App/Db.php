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
use Gobl\DBAL\Interfaces\RDBMSInterface;
use Gobl\DBAL\Types\Utils\TypeUtils;
use Gobl\Gobl;
use OZONE\Core\Columns\TypeProvider;
use OZONE\Core\Exceptions\RuntimeException;
use OZONE\Core\Hooks\Events\DbReadyHook;
use OZONE\Core\Hooks\Events\DbSchemaCollectHook;
use OZONE\Core\Hooks\Events\DbSchemaReadyHook;
use OZONE\Core\Migrations\Migrations;
use OZONE\Core\OZone;
use OZONE\Core\Plugins\Plugins;
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
			OZone::dieIfBookHookReceiversAreNotNotified(
				\sprintf(
					'%s should be called only after all boot hooks are registered.' .
					' A call to %s before all boot hooks are registered may cause '
					. 'some boot hooks to not be notified and lead to inconsistent state.',
					__METHOD__,
					__METHOD__
				)
			);

			Gobl::setProjectCacheDir(
				app()
					->getCacheDir()
					->getRoot()
			);

			$config    = Settings::load('oz.db');
			$db_config = new DbConfig([
				'db_table_prefix' => Settings::get('oz.db', 'OZ_DB_TABLE_PREFIX'),
				'db_host'         => $config['OZ_DB_HOST'],
				'db_name'         => $config['OZ_DB_NAME'],
				'db_user'         => $config['OZ_DB_USER'],
				'db_pass'         => $config['OZ_DB_PASS'],
				'db_charset'      => $config['OZ_DB_CHARSET'],
				'db_collate'      => $config['OZ_DB_COLLATE'],
			]);

			$rdbms_type = $config['OZ_DB_RDBMS'];

			try {
				self::$db = GoblDb::newInstanceOf($rdbms_type, $db_config);
			} catch (Throwable $t) {
				throw new RuntimeException(
					\sprintf('Unable to init "%s" RDBMS defined in "oz.db".', $rdbms_type),
					null,
					$t
				);
			}

			try {
				self::register();
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
	 * Returns the OZone db namespace.
	 */
	public static function getOZoneDbNamespace(): string
	{
		return Plugins::default()->getDbNamespace();
	}

	/**
	 * Returns the project db namespace.
	 */
	public static function getProjectDbNamespace(): string
	{
		$project_namespace = Settings::get('oz.config', 'OZ_PROJECT_NAMESPACE');

		return \sprintf('%s\\Db', $project_namespace ?? 'NO_PROJECT');
	}

	/**
	 * Returns the OZone db folder.
	 */
	public static function getOZoneDbFolder(): string
	{
		$fm = Plugins::default()->getScope()->getPrivateDir();

		return $fm->cd('Db', true)->getRoot();
	}

	/**
	 * Returns the project db folder.
	 */
	public static function getProjectDbFolder(): string
	{
		return app()
			->getPrivateDir()
			->cd('Db', true)
			->getRoot();
	}

	/**
	 * Collects all tables from the project and OZone to a given db instance.
	 *
	 * @param RDBMSInterface $db
	 */
	public static function loadDevelopmentSchemaTo(RDBMSInterface $db): void
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
	 * Register.
	 *
	 * @throws DBALException
	 */
	private static function register(): void
	{
		TypeUtils::addTypeProvider(new TypeProvider());

		$mg      = new Migrations();
		$version = $mg::getSourceCodeDbVersion();

		if (Migrations::DB_NOT_INSTALLED_VERSION === $version) {
			self::loadDevelopmentSchemaTo(self::$db);
		} else {
			$current = $mg->getMigration($version);
			if (!$current) {
				throw (new RuntimeException(
					\sprintf(
						'Unable to find migration, using db version "%s" defined in settings.',
						$version
					)
				))->suspectConfig('oz.db.migrations', 'OZ_MIGRATION_VERSION');
			}

			self::$db->loadSchema($current->getSchema());

			(new DbSchemaReadyHook(self::$db))->dispatch();
		}
		self::$db
			->ns(self::getOZoneDbNamespace())
			->enableORM(self::getOZoneDbFolder());

		self::$db
			->ns(self::getProjectDbNamespace())
			->enableORM(self::getProjectDbFolder());

		self::$db->lock();

		(new DbReadyHook(self::$db))->dispatch();
	}
}
