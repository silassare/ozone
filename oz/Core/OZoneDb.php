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

	use OZONE\OZ\Db\Db;
	use OZONE\OZ\Exceptions\OZoneInternalError;
	use OZONE\OZ\Loader\ClassLoader;

	defined('OZ_SELF_SECURITY_CHECK') or die;

	final class OZoneDb
	{
		/**
		 * database instance.
		 *
		 * @var \OZONE\OZ\Db\Db
		 */
		private static $database;

		/**
		 * relational database management system.
		 *
		 * @var \OZONE\OZ\Db\RDBMS
		 */
		private static $rdbms;

		/**
		 * PDO database connection instance.
		 *
		 * @var \PDO
		 */
		private static $db_connection;

		/**
		 * used to generate bind param id.
		 *
		 * @var int
		 */
		private static $bind_unique_id = 0;

		/**
		 * OZoneDb constructor.
		 */
		private function __construct()
		{
			if (empty(self::$database)) {
				$this->initRDBMS();
				$oz_db_init = include OZ_OZONE_DIR . 'oz_default' . DS . 'oz_db_init.php';
				$tables     = OZoneSettings::get('oz.db.tables');

				self::$database->addTablesFromOptions($oz_db_init)
							   ->addTablesFromOptions($tables);
			}
		}

		/**
		 * OZoneDb destructor.
		 */
		public function __destruct()
		{
			if (isset(self::$database)) {
				self::$db_connection = null;
				self::$rdbms         = null;
				self::$database      = null;
			}
		}

		/**
		 * get the project rdbms.
		 *
		 * @return \OZONE\OZ\Db\RDBMS
		 */
		public function getRDBMS()
		{
			return self::$rdbms;
		}

		/**
		 * get the project database.
		 *
		 * @return \OZONE\OZ\Db\Db
		 */
		public function getDb()
		{
			return self::$database;
		}

		/**
		 * get database connection.
		 *
		 * @return \PDO
		 */
		public function getConnection()
		{
			if (empty(self::$db_connection)) {
				self::$db_connection = self::$rdbms->connect();
			}

			return self::$db_connection;
		}

		/**
		 * get instance of \OZONE\OZ\Core\OZoneDb
		 *
		 * @return \OZONE\OZ\Core\OZoneDb
		 */
		public static function getInstance()
		{
			return new self();
		}

		/**
		 * init rdbms.
		 *
		 * @throws \OZONE\OZ\Exceptions\OZoneInternalError    When database connection fails.
		 */
		private function initRDBMS()
		{
			$config      = OZoneSettings::get('oz.config');
			$rdbms_class = $config['OZ_APP_DB_RDBMS'];

			try {
				$oz_db_class = 'OZONE\OZ\Db\RDBMS';
				if (ClassLoader::exists($rdbms_class)) {
					/** @var \OZONE\OZ\Db\RDBMS $rdbms */
					$rdbms = ClassLoader::instantiateClass($rdbms_class, [$config]);

					if (!is_subclass_of($rdbms, $oz_db_class)) {
						throw new \Exception(sprintf('your rdbms class "%s" should implements "%s".', $rdbms_class, $oz_db_class));
					}

					self::$rdbms    = $rdbms;
					self::$database = new Db($rdbms);
				} else {
					throw new \Exception(sprintf('rdbms class "%s" defined in "oz.db" not found.', $rdbms_class));
				}
			} catch (\Exception $e) {
				throw new OZoneInternalError('OZ_DB_IS_DOWN', [$e->getMessage()]);
			}
		}

		/**
		 * Execute a given sql query and params
		 *
		 * @param string     $sql    Your sql query
		 * @param array|null $params Your sql params
		 *
		 * @return \PDOStatement
		 */
		public function execute($sql, array $params = null)
		{
			$stmt = $this->getConnection()
						 ->prepare($sql);

			if ($params !== null) {
				foreach ($params as $key => $value) {
					$param_type = \PDO::PARAM_STR;

					if (is_int($value)) {
						$param_type = \PDO::PARAM_INT;
					} elseif (is_bool($value)) {
						$param_type = \PDO::PARAM_BOOL;
					}

					$stmt->bindValue(is_int($key) ? $key + 1 : $key, $value, $param_type);
				}
			}

			$stmt->execute();

			return $stmt;
		}

		/**
		 * execute a query and return affected row count.
		 *
		 * @param string     $sql    Your sql query
		 * @param array|null $params Your sql params
		 *
		 * @return int    Affected row count.
		 */
		private function query($sql, array $params = null)
		{
			$stmt = $this->execute($sql, $params);

			return $stmt->rowCount();
		}

		/**
		 * execute selection queries.
		 *
		 * @param string     $sql    Your sql select query
		 * @param array|null $params Your sql select params
		 *
		 * @return \PDOStatement
		 */
		public function select($sql, array $params = null)
		{
			return $this->execute($sql, $params);
		}

		/**
		 * execute deletion queries.
		 *
		 * @param string     $sql    Your sql select query
		 * @param array|null $params Your sql select params
		 *
		 * @return int    Affected row count
		 */
		public function delete($sql, array $params = null)
		{
			return $this->query($sql, $params);
		}

		/**
		 * execute insertion queries.
		 *
		 * @param string     $sql    Your sql select query
		 * @param array|null $params Your sql select params
		 *
		 * @return int    The last inserted row id
		 */
		public function insert($sql, array $params = null)
		{
			$stmt    = $this->execute($sql, $params);
			$last_id = $this->getConnection()
							->lastInsertId();

			$stmt->closeCursor();

			return $last_id;
		}

		/**
		 * execute update queries.
		 *
		 * @param string     $sql    Your sql select query
		 * @param array|null $params Your sql select params
		 *
		 * @return int    Affected row count
		 */
		public function update($sql, array $params = null)
		{
			return $this->query($sql, $params);
		}

		/**
		 * mask database columns names with external columns names.
		 *
		 * @param array      $data        Database row object
		 * @param array      $columns     Columns to conserve
		 * @param array|null $mask_extend Extends 'oz.db.columns.mask' settings
		 *
		 * @return array
		 * @throws \OZONE\OZ\Exceptions\OZoneInternalError
		 */
		public static function maskColumnsName(array $data, array $columns, array $mask_extend = null)
		{
			$out = [];

			$mask_default = OZoneSettings::get('oz.db.columns.mask');

			$mask = is_array($mask_extend) ? array_merge($mask_default, $mask_extend) : $mask_default;

			foreach ($columns as $column) {
				if (!array_key_exists($column, $mask)) {
					throw new OZoneInternalError("$column is missing in your oz.db.columns.mask");
				}

				$field_out_name = $mask[$column];

				if (array_key_exists($column, $data)) {
					$out[$field_out_name] = $data[$column];
				}
			}

			return $out;
		}

		/**
		 * try to remove database columns names mask with external columns names.
		 *
		 * @param array $data
		 *
		 * @return array
		 * @throws \OZONE\OZ\Exceptions\OZoneInternalError
		 */
		public static function tryRemoveColumnsNameMask(array $data)
		{
			$out = [];

			$mask_default = OZoneSettings::get('oz.db.columns.mask');

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
		 * fetch all results in a given statement and mask database columns names with external columns names.
		 *
		 * @param \PDOStatement $stm           Database row object
		 * @param array         $columns       Columns to conserve
		 * @param array|null    $mask_extend   Extends 'oz.db.columns.mask' settings
		 * @param string|null   $column_as_key The unique column name to use for result association
		 * @param callable|null $formatter     The formatter to call for each row
		 *
		 * @return array
		 * @throws \OZONE\OZ\Exceptions\OZoneInternalError
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
						throw new OZoneInternalError("you should provide a valid callable as formatter");
					}
				}

				if (!empty($column_as_key)) {
					if (!isset($row[$column_as_key])) {
						throw new OZoneInternalError("$column_as_key is not defined in fetched row");
					}

					$data_key = $row[$column_as_key];

					if (empty($data_key)) {
						throw new OZoneInternalError("can't use $column_as_key as result key, empty value found", ['row' => $row]);
					}

					$out[$data_key] = $data;
				} else {
					$out[] = $data;
				}
			}

			return $out;
		}

		/**
		 * get a unique bind param id.
		 *
		 * @return int
		 */
		private static function getBindUniqueId()
		{
			return self::$bind_unique_id++;
		}

		/**
		 * @param array $list         values to bind
		 * @param array &$bind_values where to store bind value
		 *
		 * @return string
		 * @throws \Exception
		 */
		public static function getQueryBindForArray(array $list, array &$bind_values)
		{
			if (!count($list)) {
				throw new \Exception('your list should not be empty array');
			}

			$list      = array_values($list);
			$bind_keys = [];

			foreach ($list as $i => $value) {
				$bind_key               = '_' . self::getBindUniqueId() . '_';
				$bind_keys[]            = ':' . $bind_key;
				$bind_values[$bind_key] = $value;
			}

			return '(' . implode(',', $bind_keys) . ')';
		}
	}
