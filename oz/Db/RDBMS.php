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

	interface RDBMS
	{
		/**
		 * the relational database management system constructor.
		 *
		 * @param array $config
		 */
		public function __construct(array $config);

		/**
		 * connect to the relational database management system.
		 *
		 * @return \PDO
		 */
		public function connect();

		/**
		 * get table definition query string.
		 *
		 * @param \OZONE\OZ\Db\Table $table
		 *
		 * @return string
		 */
		public function getTableDefinitionString(Table $table);

		/**
		 * get bool column definition query string.
		 *
		 * @param \OZONE\OZ\Db\Column $column
		 *
		 * @return string
		 */
		public function getBoolColumnDefinition(Column $column);

		/**
		 * get int column definition query string.
		 *
		 * @param \OZONE\OZ\Db\Column $column
		 *
		 * @return string
		 */
		public function getIntColumnDefinition(Column $column);

		/**
		 * get bigint column definition query string.
		 *
		 * @param \OZONE\OZ\Db\Column $column
		 *
		 * @return string
		 */
		public function getBigintColumnDefinition(Column $column);

		/**
		 * get float column definition query string.
		 *
		 * @param \OZONE\OZ\Db\Column $column
		 *
		 * @return string
		 */
		public function getFloatColumnDefinition(Column $column);

		/**
		 * get string column definition query string.
		 *
		 * @param \OZONE\OZ\Db\Column $column
		 *
		 * @return string
		 */
		public function getStringColumnDefinition(Column $column);

		/**
		 * get unique constraint definition query string.
		 *
		 * @param \OZONE\OZ\Db\Table $table           the table
		 * @param array              $columns         the columns
		 * @param string             $constraint_name the constraint name to use
		 *
		 * @return string
		 */
		public function getUniqueConstraintDefinition(Table $table, array $columns, $constraint_name);

		/**
		 * get primary key constraint definition query string.
		 *
		 * @param \OZONE\OZ\Db\Table $table           the table
		 * @param array              $columns         the columns
		 * @param string             $constraint_name the constraint name to use
		 *
		 * @return string
		 */
		public function getPrimaryKeyConstraintDefinition(Table $table, array $columns, $constraint_name);

		/**
		 * get foreign key constraint definition query string.
		 *
		 * @param \OZONE\OZ\Db\Table $table           the table
		 * @param \OZONE\OZ\Db\Table $reference_table the foreign key reference table
		 * @param array              $columns         the columns
		 * @param string             $constraint_name the constraint name to use
		 *
		 * @return string
		 */
		public function getForeignKeyConstraintDefinition(Table $table, Table $reference_table, array $columns, $constraint_name);
	}
