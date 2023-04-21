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

namespace OZONE\OZ\Core;

use Gobl\DBAL\Db;
use Gobl\DBAL\DbConfig;
use Gobl\DBAL\Interfaces\RDBMSInterface;
use Gobl\DBAL\Types\Utils\TypeUtils;
use Gobl\Gobl;
use OZONE\OZ\Columns\TypeProvider;
use OZONE\OZ\Exceptions\RuntimeException;
use OZONE\OZ\FS\FilesManager;
use OZONE\OZ\Hooks\Events\DbBeforeLockHook;
use OZONE\OZ\Hooks\Events\DbCollectHook;
use OZONE\OZ\Hooks\Events\DbReadyHook;
use OZONE\OZ\Migrations\MigrationsManager;
use PHPUtils\Events\Event;
use Throwable;

/**
 * Class DbManager.
 */
final class DbManager
{
	public const OZONE_DB_NAMESPACE = 'OZONE\\OZ\\Db';

	private static ?RDBMSInterface $db = null;

	/**
	 * Initialize.
	 */
	public static function init(): RDBMSInterface
	{
		if (!self::$db) {
			Gobl::setRootDir(OZ_CACHE_DIR);

			$config    = Configs::load('oz.db');
			$db_config = new DbConfig([
				'db_table_prefix' => Configs::get('oz.db', 'OZ_DB_TABLE_PREFIX'),
				'db_host'         => $config['OZ_DB_HOST'],
				'db_name'         => $config['OZ_DB_NAME'],
				'db_user'         => $config['OZ_DB_USER'],
				'db_pass'         => $config['OZ_DB_PASS'],
				'db_charset'      => $config['OZ_DB_CHARSET'],
				'db_collate'      => $config['OZ_DB_COLLATE'],
			]);

			$rdbms_type = $config['OZ_DB_RDBMS'];

			try {
				self::$db = Db::createInstanceOf($rdbms_type, $db_config);
			} catch (Throwable $t) {
				throw new RuntimeException(
					\sprintf('Unable to init "%s" RDBMS defined in "oz.db".', $rdbms_type),
					null,
					$t
				);
			}

			try {
				self::register();

				// trigger db ready hook
				Event::trigger(new DbReadyHook(self::$db));
			} catch (Throwable $t) {
				throw new RuntimeException('Unable to initialize database.', null, $t);
			}
		}

		return self::$db;
	}

	/**
	 * Gets instance.
	 */
	public static function getDb(): RDBMSInterface
	{
		return self::init();
	}

	/**
	 * Returns the database folder structure of the current project.
	 *
	 * @return array{'oz_db_namespace':string,'project_db_namespace':string,'oz_db_folder':string,'project_db_folder':string}
	 */
	public static function getProjectDbDirectoryStructure(): array
	{
		$project_namespace = Configs::get('oz.config', 'OZ_PROJECT_NAMESPACE');

		$info['oz_db_namespace']      = self::OZONE_DB_NAMESPACE;
		$info['project_db_namespace'] = \sprintf('%s\\Db', $project_namespace ?? 'NO_PROJECT');

		$fm = new FilesManager(OZ_OZONE_DIR);

		$info['oz_db_folder']      = $fm->cd('Db', true)
			->getRoot();
		$info['project_db_folder'] = $fm->cd(OZ_APP_DIR . 'Db', true)
			->getRoot();

		return $info;
	}

	/**
	 * Collects all tables from the project and OZone to a given db instance.
	 *
	 * @param \Gobl\DBAL\Interfaces\RDBMSInterface $db
	 * @param bool                                 $enable_orm
	 */
	public static function collectTablesTo(RDBMSInterface $db, bool $enable_orm = false): void
	{
		$structure = self::getProjectDbDirectoryStructure();

		$oz_database = include OZ_OZONE_DIR . 'oz_default' . DS . 'oz_database.php';
		$oz_ns       = $db->ns($structure['oz_db_namespace'])
			->addTables($oz_database);

		$project_tables = Configs::load('oz.db.tables');
		$project_ns     = $db->ns($structure['project_db_namespace'])
			->addTables($project_tables);

		if ($enable_orm) {
			$oz_ns->enableORM();
			$project_ns->enableORM();
		}

		Event::trigger(new DbCollectHook($db));
	}

	/**
	 * Register.
	 */
	private static function register(): void
	{
		TypeUtils::addTypeProvider(new TypeProvider());

		$mg         = new MigrationsManager();
		$db_version = $mg->getCurrentDbVersion();

		if (MigrationsManager::DB_NOT_INSTALLED_VERSION === $db_version) {
			self::collectTablesTo(self::$db, true);

			Event::trigger(new DbBeforeLockHook(self::$db));

			self::$db->lock();
		} else {
			$current = $mg->getMigration($db_version);
			if (!$current) {
				throw (new RuntimeException(\sprintf(
					'Unable to find migration, using db version "%s" defined in settings.',
					$db_version
				)))->suspectConfig('oz.db.migrations', 'OZ_MIGRATION_VERSION');
			}

			$tables   = $current->getTables();
			$ns_cache = [];

			self::$db->addTables($tables);

			// we need to enable ORM for each namespace in our migration tables definitions
			foreach ($tables as $table) {
				$ns = $table['namespace'];

				if (!isset($ns_cache[$ns])) {
					self::$db->ns($ns)
						->enableORM();
					$ns_cache[$ns] = 1;
				}
			}
		}
	}
}
