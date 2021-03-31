<?php

/**
 * Copyright (c) 2017-present, Emile Silas Sare
 *
 * This file is part of OZone package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace OZONE\OZ\Core;

use Exception;
use Gobl\DBAL\Column;
use Gobl\DBAL\Db;
use Gobl\DBAL\QueryBuilder;
use Gobl\ORM\ORM;
use OZONE\OZ\Exceptions\InternalErrorException;
use OZONE\OZ\FS\FilesManager;
use ReflectionClass;
use ReflectionException;
use RuntimeException;

final class DbManager
{
	/**
	 * @var \Gobl\DBAL\Db
	 */
	private static $db = null;

	/**
	 * Initialize.
	 *
	 * @throws \OZONE\OZ\Exceptions\InternalErrorException
	 */
	public static function init()
	{
		$config    = SettingsManager::get('oz.db');
		$db_config = [
			'db_host'    => $config['OZ_DB_HOST'],
			'db_name'    => $config['OZ_DB_NAME'],
			'db_user'    => $config['OZ_DB_USER'],
			'db_pass'    => $config['OZ_DB_PASS'],
			'db_charset' => $config['OZ_DB_CHARSET'],
			'db_collate' => $config['OZ_DB_COLLATE'],
		];

		$rdbms_type = $config['OZ_DB_RDBMS'];

		try {
			self::$db = Db::instantiate($rdbms_type, $db_config);
		} catch (Exception $e) {
			throw new InternalErrorException(
				\sprintf('Unable to init RDBMS defined in "oz.db": %s.', $rdbms_type),
				null,
				$e
			);
		}

		try {
			self::register();
		} catch (Exception $e) {
			throw new InternalErrorException('Unable to initialize database.', null, $e);
		}
	}

	/**
	 * Gets instance.
	 *
	 * @throws \OZONE\OZ\Exceptions\InternalErrorException
	 *
	 * @return \Gobl\DBAL\Db
	 */
	public static function getDb()
	{
		if (self::$db === null) {
			self::init();
		}

		return self::$db;
	}

	/**
	 * Returns the database folder structure of the current project.
	 *
	 * @return array
	 */
	public static function getProjectDbDirectoryStructure()
	{
		$config                       = SettingsManager::get('oz.config');
		$info['oz_db_namespace']      = 'OZONE\\OZ\\Db';
		$info['project_db_namespace'] = (
			isset($config['OZ_PROJECT_NAMESPACE']) ? $config['OZ_PROJECT_NAMESPACE'] : 'NO_PROJECT'
		) . '\\Db';
		$fm                           = new FilesManager(OZ_OZONE_DIR);
		$info['oz_db_folder']         = $fm->cd('Db', true)
										   ->getRoot();
		$info['project_db_folder']    = $fm->cd(OZ_APP_DIR . 'Db', true)
										   ->getRoot();

		return $info;
	}

	/**
	 * Returns a new query builder instance.
	 *
	 * @throws \OZONE\OZ\Exceptions\BaseException
	 *
	 * @return \Gobl\DBAL\QueryBuilder
	 */
	public static function queryBuilder()
	{
		return new QueryBuilder(self::getDb());
	}

	/**
	 * Instantiate CRUD handler.
	 *
	 * @param \OZONE\OZ\Core\Context $context
	 * @param string                 $table_name
	 *
	 * @return null|\OZONE\OZ\Core\CRUDHandler
	 */
	public static function instantiateCRUDHandler(Context $context, $table_name)
	{
		$crud_handler = SettingsManager::get('oz.gobl.crud', $table_name);

		if ($crud_handler) {
			try {
				$rc = new ReflectionClass($crud_handler);

				if ($rc->isSubclassOf(CRUDHandler::class)) {
					return new $crud_handler($context);
				}

				throw new RuntimeException(\sprintf(
					'CRUD handler "%s" should extends "%s".',
					$table_name,
					CRUDHandler::class
				));
			} catch (ReflectionException $e) {
				throw new RuntimeException(\sprintf(
					'Unable to instantiate CRUD handler: "%s" -> "%s"',
					$table_name,
					$crud_handler
				), null, $e);
			}
		}

		return null;
	}

	/**
	 * Register.
	 *
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 * @throws \Gobl\ORM\Exceptions\ORMException
	 */
	private static function register()
	{
		$oz_database           = include OZ_OZONE_DIR . 'oz_default' . DS . 'oz_database.php';
		$structure             = self::getProjectDbDirectoryStructure();
		$columns_customs_types = SettingsManager::get('oz.db.columns.types');
		$tables                = SettingsManager::get('oz.db.tables');
		$tables_prefix         = SettingsManager::get('oz.db', 'OZ_DB_TABLE_PREFIX');

		foreach ($columns_customs_types as $type => $class) {
			Column::addCustomType($type, $class);
		}

		ORM::setDatabase($structure['oz_db_namespace'], self::$db);
		ORM::setDatabase($structure['project_db_namespace'], self::$db);

		self::$db->addTablesToNamespace($structure['oz_db_namespace'], $oz_database, $tables_prefix)
				 ->addTablesToNamespace($structure['project_db_namespace'], $tables, $tables_prefix);
	}
}
