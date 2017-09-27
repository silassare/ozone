<?php
	/**
	 * Copyright (c) Emile Silas Sare <emile.silas@gmail.com>
	 *
	 * This file is part of the OZone package.
	 *
	 * For the full copyright and license information, please view the LICENSE
	 * file that was distributed with this source code.
	 */

	namespace OZONE\OZ\Db;

	use OZONE\OZ\Core\OZoneSettings;

	class Db
	{
		/**
		 * database tables.
		 */
		private $tables = [];

		/**
		 * relational database management system.
		 *
		 * @var \OZONE\OZ\Db\RDBMS
		 */
		private $rdbms;

		/**
		 * DbBuilder constructor.
		 *
		 * @param \OZONE\OZ\Db\RDBMS $rdbms
		 */
		public function __construct(RDBMS $rdbms)
		{
			$this->rdbms = $rdbms;
		}

		/**
		 * check if a given string is a column reference.
		 *
		 * @param $str
		 *
		 * @return bool
		 */
		public static function isColumnReference($str)
		{
			return is_array(self::parseColumnReference($str));
		}

		/**
		 * parse a column reference.
		 *
		 * @param $str
		 *
		 * @return array|null
		 */
		public static function parseColumnReference($str)
		{
			if (is_string($str)) {
				$parts = explode('.', $str);
				if (count($parts) === 2) {
					$clone = ($str[0] === ':') ? false : true;
					$tbl   = ($clone === false) ? substr($parts[0], 1) : $parts[0];
					$col   = $parts[1];

					return [
						'clone'  => $clone,
						'table'  => $tbl,
						'column' => $col
					];
				}
			}

			return null;
		}

		/**
		 * resolve reference column.
		 *
		 * you don't need to define param circle
		 * it is for internal use only
		 * to prevent cyclic search that may cause infinite loop
		 *
		 * @param string $ref_name the reference column name
		 * @param array  $tables   tables config array
		 * @param array  $circle   contains all references
		 *
		 * @return array|null
		 * @throws \Exception
		 */
		public function resolveReferenceColumn($ref_name, array $tables = [], array $circle = [])
		{
			if (in_array($ref_name, $circle)) {
				$circle[] = $ref_name;
				throw new \Exception(sprintf('possible cyclic reference found for column "%s": "%s".', $circle[0], implode(' > ', $circle)));
			}

			$circle[] = $ref_name;
			$info     = self::parseColumnReference($ref_name);

			if ($info) {
				$_col      = null;
				$clone     = $info['clone'];
				$ref_table = $info['table'];
				$ref_col   = $info['column'];

				if (isset($this->tables[$ref_table])) {
					/** @var $tbl \OZONE\OZ\Db\Table */
					$tbl = $this->tables[$ref_table];
					if ($tbl->hasColumn($ref_col)) {
						$_col = $tbl->getColumn($ref_col)
									->getOptions();
					}
				} elseif (isset($tables[$ref_table])) {
					$cols = $tables[$ref_table]['columns'];
					if (is_array($cols) AND isset($cols[$ref_col])) {
						$_col  = $cols[$ref_col];
						$type  = null;
						$opt_a = [];
						$opt_b = null;

						if (is_string($_col)) {
							$type = $_col;
						} elseif (is_array($_col) AND isset($_col['type'])) {
							$type  = $_col['type'];
							$opt_a = $_col;
						}

						if ($type AND self::isColumnReference($type)) {
							$opt_b = $this->resolveReferenceColumn($type, $tables, $circle);
						}

						if (is_array($opt_b)) {
							$_col = array_merge($opt_b, $opt_a, ['type' => $opt_b['type']]);
						}
					}
				}

				if (is_array($_col)) {
					if (!$clone) {
						$_col['auto_increment'] = false;
					}

					return $_col;
				}
			}

			return null;
		}

		/**
		 * add table from options.
		 *
		 * @param array $tables tables options
		 *
		 * @return $this
		 * @throws \Exception
		 */
		public function addTablesFromOptions(array $tables)
		{
			$tbl_prefix = OZoneSettings::get('oz.db', 'OZ_DB_TABLE_PREFIX');

			// we add tables and columns first
			foreach ($tables as $table_name => $table) {
				if (!isset($table['columns']) OR !is_array($table['columns']) OR !count($table['columns']))
					throw new \Exception(sprintf('you should define columns for table "%s".', $table_name));

				$columns    = $table['columns'];
				$col_prefix = isset($table['column_prefix']) ? $table['column_prefix'] : null;
				$tbl        = new Table($table_name, $tbl_prefix);

				foreach ($columns as $column_name => $value) {
					$column = is_array($value) ? $value : ['type' => $value];

					if (isset($column['type']) AND self::isColumnReference($column['type'])) {
						$options        = $this->resolveReferenceColumn($column['type'], $tables);
						$column         = is_array($value) ? array_merge($options, $value) : $options;
						$column['type'] = $options['type'];
					}

					if (is_array($column)) {
						$col = new Column($column_name, $col_prefix);
						$col->setOptions($column);
					} else {
						throw new \Exception(sprintf('invalid column "%s" options in table "%s".', $column_name, $table_name));
					}

					$tbl->addColumn($col);
				}
				$this->tables[$table_name] = $tbl;
			}

			// we add constraints after
			foreach ($tables as $table_name => $table) {
				$tbl = $this->tables[$table_name];

				if (!isset($table['constraints']))
					continue;

				$constraints = $table['constraints'];

				foreach ($constraints as $constraint) {
					$name = isset($constraint['name']) ? $constraint['name'] : null;
					if (!isset($constraint['type']))
						throw new \Exception(sprintf('you should declare constraint "type" in table "%s".', $table_name));
					if (!isset($constraint['columns']) OR !is_array($constraint['columns']))
						throw new \Exception(sprintf('you should declare constraint "columns" list in table "%s".', $table_name));

					$type = $constraint['type'];
					switch ($type) {
						case 'unique':

							$tbl->addUniqueConstraint($constraint['columns'], $name);
							break;
						case 'primary_key':

							$tbl->addPrimaryKeyConstraint($constraint['columns'], $name);
							break;
						case 'foreign_key':
							if (!isset($constraint['reference']))
								throw new \Exception(sprintf('you should declare foreign key "reference" table in table "%s".', $table_name));

							$c_ref = $constraint['reference'];

							if (!isset($this->tables[$c_ref]))
								throw new \Exception(sprintf('reference table "%s" for foreign key in table "%s" is not defined.', $c_ref, $table_name));

							$c_ref_tbl = $this->tables[$c_ref];
							$tbl->addForeignKeyConstraint($c_ref_tbl, $constraint['columns'], $name);

							break;
						default:
							throw new \Exception(sprintf('unknown constraint type "%s" defined in table "%s".', $type, $table_name));
					}
				}
			}

			return $this;
		}

		/**
		 * generate database file.
		 *
		 * @return string
		 */
		public function generateDataBaseQuery()
		{
			$parts = [];

			foreach ($this->tables as $table_name => $table) {
				$parts[] = $this->rdbms->getTableDefinitionString($table);
			}

			return implode(PHP_EOL, $parts);
		}
	}
