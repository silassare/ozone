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
use Gobl\ORM\ORM;
use OZONE\OZ\Columns\TypeProvider;
use OZONE\OZ\Exceptions\RuntimeException;
use OZONE\OZ\FS\FilesManager;
use Throwable;

/**
 * Class DbManager.
 */
final class DbManager
{
	public const OZONE_DB_NAMESPACE = 'OZONE\\OZ\\Db';

	private static RDBMSInterface $db;

	/**
	 * Initialize.
	 */
	public static function init(): void
	{
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
			self::$db = Db::createInstanceWithName($rdbms_type, $db_config);
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

	/**
	 * Gets instance.
	 */
	public static function getDb(): RDBMSInterface
	{
		if (!isset(self::$db)) {
			self::init();
		}

		return self::$db;
	}

	/**
	 * Returns the database folder structure of the current project.
	 *
	 * @return array
	 */
	public static function getProjectDbDirectoryStructure(): array
	{
		$config                       = Configs::load('oz.config');
		$info['oz_db_namespace']      = self::OZONE_DB_NAMESPACE;
		$info['project_db_namespace'] = \sprintf('%s\\Db', $config['OZ_PROJECT_NAMESPACE'] ?? 'NO_PROJECT');
		$fm                           = new FilesManager(OZ_OZONE_DIR);
		$info['oz_db_folder']         = $fm->cd('Db', true)
			->getRoot();
		$info['project_db_folder']    = $fm->cd(OZ_APP_DIR . 'Db', true)
			->getRoot();

		return $info;
	}

	/**
	 * Register.
	 *
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 * @throws \Gobl\ORM\Exceptions\ORMException
	 */
	private static function register(): void
	{
		$oz_database = include OZ_OZONE_DIR . 'oz_default' . DS . 'oz_database.php';
		$structure   = self::getProjectDbDirectoryStructure();
		$tables      = Configs::load('oz.db.tables');

		TypeUtils::addTypeProvider(new TypeProvider());

		ORM::setDatabase($structure['oz_db_namespace'], self::$db);
		ORM::setDatabase($structure['project_db_namespace'], self::$db);

		self::$db->addTablesToNamespace($structure['oz_db_namespace'], $oz_database)
			->addTablesToNamespace($structure['project_db_namespace'], $tables);
	}
}
