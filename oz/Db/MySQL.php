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

	use OZONE\OZ\Db\Types\Type;
	use OZONE\OZ\FS\OZoneTemplates;

	class MySQL implements RDBMS
	{
		private $config = [];

		/**
		 * MySQL constructor.
		 *
		 * @param array $config
		 */
		public function __construct(array $config)
		{
			$this->config = $config;
		}

		/**
		 * {@inheritdoc}
		 */
		public function connect()
		{
			$host     = $this->config['OZ_APP_DB_HOST'];
			$dbname   = $this->config['OZ_APP_DB_NAME'];
			$user     = $this->config['OZ_APP_DB_USER'];
			$password = $this->config['OZ_APP_DB_PASS'];

			$pdo_options[\PDO::ATTR_ERRMODE] = \PDO::ERRMODE_EXCEPTION;

			$db = new \PDO('mysql:host=' . $host . ';dbname=' . $dbname, $user, $password, $pdo_options);

			return $db;
		}

		/**
		 * {@inheritdoc}
		 */
		public function getTableDefinitionString(Table $table)
		{
			$columns = $table->getColumns();
			$mysql   = [];

			foreach ($columns as $column) {
				/** @var \OZONE\OZ\Db\Column $column */
				$type = $column->getTypeObject();

				if ($type->is(Type::TYPE_INT))
					$mysql[] = $this->getIntColumnDefinition($column);
				elseif ($type->is(Type::TYPE_BIGINT))
					$mysql[] = $this->getBigintColumnDefinition($column);
				elseif ($type->is(Type::TYPE_FLOAT))
					$mysql[] = $this->getFloatColumnDefinition($column);
				elseif ($type->is(Type::TYPE_STRING))
					$mysql[] = $this->getStringColumnDefinition($column);
				elseif ($type->is(Type::TYPE_BOOL))
					$mysql[] = $this->getBoolColumnDefinition($column);
			}

			$table_alter = [];
			$uc          = $table->getUniqueConstraints();
			$pk          = $table->getPrimaryKeyConstraints();
			$fk          = $table->getForeignKeyConstraints();

			foreach ($pk as $name => $columns) {
				$mysql[] = $this->getPrimaryKeyConstraintDefinition($table, $columns, $name);
			}

			foreach ($uc as $name => $columns) {
				$table_alter[] = $this->getUniqueConstraintDefinition($table, $columns, $name);
			}

			foreach ($fk as $name => $rule) {
				$reference     = $rule['reference'];
				$map           = $rule['map'];
				$table_alter[] = $this->getForeignKeyConstraintDefinition($table, $reference, $map, $name);
			}

			$inject = [
				'table_name'  => $table->getFullName(),
				'table_body'  => implode(',' . PHP_EOL, $mysql),
				'table_alter' => implode(PHP_EOL, $table_alter)
			];

			return OZoneTemplates::compute('oz:gen/db/mysql.table.create.otpl', $inject);
		}

		/**
		 * {@inheritdoc}
		 */
		public function getBoolColumnDefinition(Column $column)
		{
			$column_name = $column->getFullName();
			$options     = $column->getOptions();
			$null        = $options['null'];
			$default     = $options['default'];

			$mysql[] = "`$column_name` tinyint(1)";

			if (!$null) {
				$mysql[] = 'NOT NULL';

				if (!is_null($default)) {
					$mysql[] = sprintf('DEFAULT %s', self::quote($default));
				}
			} elseif ($null AND is_null($options['default'])) {
				$mysql[] = 'DEFAULT NULL';
			}

			$mysql[] = $default;

			return implode(' ', $mysql);
		}

		/**
		 * {@inheritdoc}
		 */
		public function getIntColumnDefinition(Column $column)
		{
			$column_name    = $column->getFullName();
			$options        = $column->getOptions();
			$null           = $options['null'];
			$unsigned       = $options['unsigned'];
			$default        = $options['default'];
			$auto_increment = $options['auto_increment'] ? 'AUTO_INCREMENT' : '';

			$min = isset($options['min']) ? $options['min'] : -INF;
			$max = isset($options['max']) ? $options['max'] : INF;

			$mysql[] = "`$column_name`";

			if ($unsigned) {
				if ($max <= 255) {
					$mysql[] = "tinyint";
				} elseif ($max <= 65535) {
					$mysql[] = "smallint";
				} else {
					$mysql[] = 'int(11)';
				}

				$mysql[] = 'unsigned';
			} else {
				if ($min >= -128 AND $max <= 127) {
					$mysql[] = "tinyint";
				} elseif ($min >= -32768 AND $max <= 32767) {
					$mysql[] = "smallint";
				} else {
					$mysql[] = 'integer(11)';
				}
			}

			if (!$null) {
				$mysql[] = 'NOT NULL';

				if (!is_null($default)) {
					$mysql[] = sprintf('DEFAULT %s', self::quote($default));
				}
			} elseif ($null AND is_null($options['default'])) {
				$mysql[] = 'DEFAULT NULL';
			}

			$mysql[] = $auto_increment;

			return implode(' ', $mysql);
		}

		/**
		 * {@inheritdoc}
		 */
		public function getBigintColumnDefinition(Column $column)
		{
			$column_name    = $column->getFullName();
			$options        = $column->getOptions();
			$null           = $options['null'];
			$unsigned       = $options['unsigned'];
			$default        = $options['default'];
			$auto_increment = $options['auto_increment'] ? 'AUTO_INCREMENT' : '';

			$mysql[] = "`$column_name` bigint(20)";

			if ($unsigned) {
				$mysql[] = 'unsigned';
			}

			if (!$null) {
				$mysql[] = 'NOT NULL';

				if (!is_null($default)) {
					$mysql[] = sprintf('DEFAULT %s', self::quote($default));
				}
			} elseif ($null AND is_null($options['default'])) {
				$mysql[] = 'DEFAULT NULL';
			}

			$mysql[] = $auto_increment;

			return implode(' ', $mysql);
		}

		/**
		 * {@inheritdoc}
		 */
		public function getFloatColumnDefinition(Column $column)
		{
			$column_name = $column->getFullName();
			$options     = $column->getOptions();
			$null        = $options['null'];
			$unsigned    = $options['unsigned'];
			$default     = $options['default'];
			$mantissa    = isset($options['mantissa']) ? $options['mantissa'] : 53;

			$mysql[] = "`$column_name` float($mantissa)";

			if ($unsigned) {
				$mysql[] = 'unsigned';
			}

			if (!$null) {
				$mysql[] = 'NOT NULL';

				if (!is_null($default)) {
					$mysql[] = sprintf('DEFAULT %s', self::quote($default));
				}
			} elseif ($null AND is_null($options['default'])) {
				$mysql[] = 'DEFAULT NULL';
			}

			return implode(' ', $mysql);
		}

		/**
		 * {@inheritdoc}
		 */
		public function getStringColumnDefinition(Column $column)
		{
			$column_name = $column->getFullName();
			$options     = $column->getOptions();
			$null        = $options['null'];
			$default     = $options['default'];
			$min         = isset($options['min']) ? $options['min'] : 0;
			$max         = isset($options['max']) ? $options['max'] : INF;
			// char(c) c in range(0,255);
			// varchar(c) c in range(0,65535);
			$c       = $max;
			$mysql[] = "`$column_name`";

			if ($c <= 255 AND $min === $max) {
				$mysql[] = "char($c)";
			} elseif ($c <= 65535) {
				$mysql[] = "varchar($c)";
			} else {
				$mysql[] = 'text';
			}

			if (!$null) {
				$mysql[] = 'NOT NULL';

				if (!is_null($default)) {
					$mysql[] = sprintf('DEFAULT %s', self::quote($default));
				}
			} elseif ($null AND is_null($options['default'])) {
				$mysql[] = 'DEFAULT NULL';
			}

			return implode(' ', $mysql);
		}

		/**
		 * {@inheritdoc}
		 */
		public function getUniqueConstraintDefinition(Table $table, array $columns, $constraint_name)
		{
			$table_name = $table->getFullName();
			$columns    = self::quoteCols($columns);
			$mysql      = "ALTER TABLE `$table_name` ADD CONSTRAINT $constraint_name UNIQUE ($columns);";

			return $mysql;
		}

		/**
		 * {@inheritdoc}
		 */
		public function getPrimaryKeyConstraintDefinition(Table $table, array $columns, $constraint_name)
		{
			$columns = self::quoteCols($columns);
			$mysql   = "CONSTRAINT $constraint_name PRIMARY KEY ($columns)";

			return $mysql;
		}

		/**
		 * {@inheritdoc}
		 */
		public function getForeignKeyConstraintDefinition(Table $table, Table $reference_table, array $columns, $constraint_name)
		{
			$table_name           = $table->getFullName();
			$reference_table_name = $reference_table->getFullName();
			$columns_list         = self::quoteCols(array_keys($columns));
			$references           = self::quoteCols(array_values($columns));

			$mysql = "ALTER TABLE `$table_name` ADD CONSTRAINT $constraint_name FOREIGN KEY ($columns_list) REFERENCES $reference_table_name ($references);";

			return $mysql;
		}

		/**
		 * quote columns name in a given list.
		 *
		 * @param array $list
		 *
		 * @return string
		 */
		private static function quoteCols(array $list)
		{
			return '`' . implode('` , `', $list) . '`';
		}

		/**
		 * wrap string, int... in single quote.
		 *
		 * @param mixed $value
		 *
		 * @return string
		 */
		private static function quote($value)
		{
			return "'" . str_replace("'", "''", $value) . "'";
		}
	}
