<?php
	/**
	 * Copyright (c) Emile Silas Sare <emile.silas@gmail.com>
	 *
	 * This file is part of the OZone package.
	 *
	 * For the full copyright and license information, please view the LICENSE
	 * file that was distributed with this source code.
	 */

	namespace OZONE\OZ\Core;

	use Gobl\DBAL\Column;
	use Gobl\DBAL\Db;
	use Gobl\DBAL\Drivers\MySQL;
	use Gobl\DBAL\QueryBuilder;
	use Gobl\ORM\ORM;
	use OZONE\OZ\Exceptions\InternalErrorException;
	use OZONE\OZ\FS\FilesManager;

	defined('OZ_SELF_SECURITY_CHECK') or die;

	final class DbManager extends Db
	{
		/**
		 * Instance of ozone db.
		 *
		 * @var \OZONE\OZ\Core\DbManager
		 */
		private static $instance = null;

		/**
		 * OZone db rdbms class setting shortcuts map
		 *
		 * @var array
		 */
		private static $oz_rdbms_map = [
			'mysql' => MySQL::class
		];

		private static $initialized = false;

		/**
		 * DbManager constructor.
		 */
		protected function __construct()
		{
			parent::__construct($this->initRDBMS());

			ORM::setDatabase($this);
		}

		/**
		 * Gets instance.
		 *
		 * @return \OZONE\OZ\Core\DbManager
		 */
		public static function getInstance()
		{
			if (self::$instance === null) {
				self::$instance = new self();
			}

			return self::$instance;
		}

		/**
		 * Init OZone project database.
		 */
		public static function init()
		{
			if (!self::$initialized) {
				self::$initialized = true;

				$oz_database           = include OZ_OZONE_DIR . 'oz_default' . DS . 'oz_database.php';
				$structure             = self::getProjectDbDirectoryStructure();
				$columns_customs_types = SettingsManager::get('oz.db.columns.types');
				$tables                = SettingsManager::get('oz.db.tables');
				$tables_prefix         = SettingsManager::get('oz.db', 'OZ_DB_TABLE_PREFIX');

				foreach ($columns_customs_types as $type => $class) {
					Column::addCustomType($type, $class);
				}

				self::getInstance()
					->addTablesFromOptions($oz_database, $structure['oz_db_namespace'], $tables_prefix)
					->addTablesFromOptions($tables, $structure['project_db_namespace'], $tables_prefix);
			}
		}

		/**
		 * DbManager destructor.
		 */
		public function __destruct()
		{
			self::$instance = null;
		}

		/**
		 * Gets database connection.
		 *
		 * @return \PDO
		 * @throws \OZONE\OZ\Exceptions\InternalErrorException
		 */
		public function getConnection()
		{
			try {
				return parent::getConnection();
			} catch (\Exception $e) {
				throw new InternalErrorException('OZ_DB_IS_DOWN', [$e->getMessage()]);
			}
		}

		/**
		 * Returns a new query builder instance.
		 *
		 * @return \Gobl\DBAL\QueryBuilder
		 */
		public static function queryBuilder()
		{
			return new QueryBuilder(self::getInstance());
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
			$info['project_db_namespace'] = $config['OZ_PROJECT_NAMESPACE'] . '\\Db';
			$fm                           = new FilesManager(OZ_OZONE_DIR);
			$info['oz_db_folder']         = $fm->cd('Db', true)
											   ->getRoot();
			$info['project_db_folder']    = $fm->cd(OZ_APP_DIR . 'Db', true)
											   ->getRoot();

			return $info;
		}

		/**
		 * Initialize rdbms.
		 *
		 * @return \Gobl\DBAL\RDBMS
		 * @throws \OZONE\OZ\Exceptions\InternalErrorException
		 */
		private function initRDBMS()
		{
			$config    = SettingsManager::get('oz.db');
			$db_config = [
				'db_host'    => $config['OZ_DB_HOST'],
				'db_name'    => $config['OZ_DB_NAME'],
				'db_user'    => $config['OZ_DB_USER'],
				'db_pass'    => $config['OZ_DB_PASS'],
				'db_charset' => $config['OZ_DB_CHARSET']
			];

			$oz_rdbms = $config['OZ_DB_RDBMS'];

			if (isset(self::$oz_rdbms_map[$oz_rdbms])) {
				$rdbms_class = self::$oz_rdbms_map[$oz_rdbms];

				return new $rdbms_class($db_config);
			} else {
				throw new InternalErrorException(sprintf('Invalid rdbms "%s" defined in "oz.db".', $oz_rdbms));
			}
		}

		/**
		 * Mask database columns names with external columns names.
		 *
		 * @param array      $data        Database row object
		 * @param array      $columns     Columns to conserve
		 * @param array|null $mask_extend Extends 'oz.db.columns.mask' settings
		 *
		 * @return array
		 * @throws \OZONE\OZ\Exceptions\InternalErrorException
		 */
		public static function maskColumnsName(array $data, array $columns, array $mask_extend = null)
		{
			$out = [];

			$mask_default = SettingsManager::get('oz.db.columns.mask');

			$mask = is_array($mask_extend) ? array_merge($mask_default, $mask_extend) : $mask_default;

			foreach ($columns as $column) {
				if (!array_key_exists($column, $mask)) {
					throw new InternalErrorException(sprintf('The column "%s" is missing in "oz.db.columns.mask" settings.', $column));
				}

				$field_out_name = $mask[$column];

				if (array_key_exists($column, $data)) {
					$out[$field_out_name] = $data[$column];
				}
			}

			return $out;
		}

		/**
		 * Try to remove database columns names mask with external columns names.
		 *
		 * @param array $data
		 *
		 * @return array
		 * @throws \OZONE\OZ\Exceptions\InternalErrorException
		 */
		public static function tryRemoveColumnsNameMask(array $data)
		{
			$out = [];

			$mask_default = SettingsManager::get('oz.db.columns.mask');

			$unmask = array_flip($mask_default);

			foreach ($data as $column => $value) {
				$key = $column;

				if (isset($unmask[$column])) {
					$key = $unmask[$column];
				}

				$out[$key] = $value;
			}

			return $out;
		}

		/**
		 * Fetch all results in a given statement and mask database columns names with external columns names.
		 *
		 * @param \PDOStatement $stm           Database row object
		 * @param array         $columns       Columns to conserve
		 * @param array|null    $mask_extend   Extends 'oz.db.columns.mask' settings
		 * @param string|null   $column_as_key The unique column name to use for result association
		 * @param callable|null $formatter     The formatter to call for each row
		 *
		 * @return array
		 * @throws \OZONE\OZ\Exceptions\InternalErrorException
		 */
		public static function fetchAllWithMask(\PDOStatement $stm, array $columns, array $mask_extend = null, $column_as_key = null, $formatter = null)
		{
			$out = [];

			while ($row = $stm->fetch()) {
				$data = self::maskColumnsName($row, $columns, $mask_extend);

				if (!empty($formatter)) {
					if (is_callable($formatter)) {
						$data = call_user_func_array($formatter, [$data, $row]);
					} else {
						throw new InternalErrorException('You should provide a valid callable as formatter.');
					}
				}

				if (!empty($column_as_key)) {
					if (!isset($row[$column_as_key])) {
						throw new InternalErrorException(sprintf('"%s" is not defined in fetched row.', $column_as_key));
					}

					$data_key = $row[$column_as_key];

					if (empty($data_key)) {
						throw new InternalErrorException(sprintf('Cannot use "%s" as result key, empty value found.', $column_as_key), ['row' => $row]);
					}

					$out[$data_key] = $data;
				} else {
					$out[] = $data;
				}
			}

			return $out;
		}
	}
