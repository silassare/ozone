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

	class Table
	{
		protected $name;
		protected $prefix;
		protected $columns             = [];
		protected $constraints         = [
			Constraint::PRIMARY_KEY => [],
			Constraint::UNIQUE      => [],
			Constraint::FOREIGN_KEY => []
		];
		protected $constraints_counter = 1;

		const NAME_REG   = '#^(?:[a-zA-Z][a-zA-Z0-9_]*[a-zA-Z0-9]|[a-zA-Z])$#';
		const PREFIX_REG = '#^(?:[a-zA-Z][a-zA-Z0-9_]*[a-zA-Z0-9]|[a-zA-Z])$#';

		/**
		 * Table constructor.
		 *
		 * @param string $name   the table name
		 * @param string $prefix the table prefix
		 *
		 * @throws \Exception
		 */
		public function __construct($name, $prefix = null)
		{
			if (!preg_match(Table::NAME_REG, $name))
				throw new \Exception(sprintf('invalid table name "%s".', $name));

			if (!empty($prefix)) {
				if (!preg_match(Table::PREFIX_REG, $prefix))
					throw new \Exception(sprintf('invalid table prefix name "%s".', $prefix));
			}

			$this->name   = strtolower($name);
			$this->prefix = $prefix;
		}

		/**
		 * adds a given column to the current table.
		 *
		 * @param \OZONE\OZ\Db\Column $column the column to add
		 *
		 * @return $this
		 * @throws \Exception
		 */
		public function addColumn(Column $column)
		{
			$name = $column->getName();

			if (array_key_exists($name, $this->columns))
				throw new \Exception(sprintf('the column "%s" is already defined in table "%s".', $name, $this->name));

			$this->columns[$name] = $column;

			return $this;
		}

		/**
		 * define a unique constraint on columns.
		 *
		 * @param array  $columns         the columns
		 * @param string $constraint_name the constraint name
		 *
		 * @return $this
		 * @throws \Exception
		 */
		public function addUniqueConstraint(array $columns, $constraint_name = '')
		{
			if (count($columns) < 1)
				throw new \Exception('columns should not be empty.');

			if (empty($constraint_name))
				$constraint_name = sprintf('uc_%s_%d', $this->name, $this->constraints_counter++);

			$unique_keys = &$this->constraints[Constraint::UNIQUE];

			foreach ($columns as $column_name) {
				if (!$this->hasColumn($column_name))
					throw new \Exception(sprintf('the column "%s" is not defined in table "%s"', $column_name, $this->name));

				$unique_keys[$constraint_name] = $this->getColumn($column_name)
													  ->getFullName();
			}

			return $this;
		}

		/**
		 * define a primary key constraint on columns.
		 *
		 * @param array  $columns         the columns
		 * @param string $constraint_name the constraint name
		 *
		 * @return $this
		 * @throws \Exception
		 */
		public function addPrimaryKeyConstraint(array $columns, $constraint_name = null)
		{
			if (count($columns) < 1)
				throw new \Exception('columns should not be empty.');

			$primary_keys = &$this->constraints[Constraint::PRIMARY_KEY];

			if (count($primary_keys)) {
				$keys = array_keys($primary_keys);
				$name = $keys[0];
				if (!empty($constraint_name) AND $name != $constraint_name) {
					throw new \Exception(sprintf('they should be only one primary key in table "%s".', $this->name));
				} else {
					$constraint_name = $name;
				}
			}

			if (empty($constraint_name))
				$constraint_name = sprintf('pk_%s_%d', $this->name, $this->constraints_counter++);

			foreach ($columns as $column_name) {
				if (!$this->hasColumn($column_name))
					throw new \Exception(sprintf('the column "%s" is not defined in table "%s"', $column_name, $this->name));

				$cols_options = $this->getColumn($column_name)
									 ->getOptions();

				if ($cols_options['null'] === true)
					throw new \Exception(sprintf('all parts of a PRIMARY KEY must be NOT NULL; if you need NULL in a key, use UNIQUE instead; check column "%s" in table "%s".', $column_name, $this->name));

				$primary_keys[$constraint_name][] = $this->getColumn($column_name)
														 ->getFullName();
			}

			return $this;
		}

		/**
		 * define a foreign key constraint on columns.
		 *
		 * @param \OZONE\OZ\Db\Table $reference_table the reference table
		 * @param array              $columns         the columns
		 * @param string             $constraint_name the constraint name
		 *
		 * @return $this
		 * @throws \Exception
		 */
		public function addForeignKeyConstraint(Table $reference_table, array $columns, $constraint_name = null)
		{
			if (count($columns) < 1)
				throw new \Exception('columns should not be empty.');

			if (empty($constraint_name))
				$constraint_name = sprintf('fk_%s_%d', $this->name, $this->constraints_counter++);

			$foreign_keys = &$this->constraints[Constraint::FOREIGN_KEY];

			$foreign_keys[$constraint_name]['reference'] = $reference_table;

			foreach ($columns as $column_name => $reference) {
				if (!$this->hasColumn($column_name))
					throw new \Exception(sprintf('the column "%s" is not defined in table "%s"', $column_name, $this->name));

				if (!$reference_table->hasColumn($reference))
					throw new \Exception(sprintf('the column "%s" is not defined in table "%s"', $column_name, $reference_table->getName()));

				$column_name                                         = $this->getColumn($column_name)
																			->getFullName();
				$reference                                           = $reference_table->getColumn($reference)
																					   ->getFullName();
				$foreign_keys[$constraint_name]['map'][$column_name] = $reference;
			}

			return $this;
		}

		/**
		 * get table name.
		 *
		 * @return string
		 */
		public function getName()
		{
			return $this->name;
		}

		/**
		 * get table prefix.
		 *
		 * @return string
		 */
		public function getPrefix()
		{
			return $this->prefix;
		}

		/**
		 * get table full name.
		 *
		 * @return string
		 */
		public function getFullName()
		{
			if (empty($this->prefix))
				return $this->name;

			return $this->prefix . '_' . $this->name;
		}

		/**
		 * get columns.
		 *
		 * @return array
		 */
		public function getColumns()
		{
			return $this->columns;
		}

		/**
		 * get column with a given name.
		 *
		 * @param string $name the column name
		 *
		 * @return \OZONE\OZ\Db\Column
		 */
		public function getColumn($name)
		{
			if ($this->hasColumn($name))
				return $this->columns[$name];

			return null;
		}

		/**
		 * get constraints.
		 *
		 * @return array
		 */
		public function getConstraints()
		{
			return $this->constraints;
		}

		/**
		 * get unique constraints.
		 *
		 * @return array
		 */
		public function getUniqueConstraints()
		{
			return $this->constraints[Constraint::UNIQUE];
		}

		/**
		 * get primary key constraints.
		 *
		 * @return array
		 */
		public function getPrimaryKeyConstraints()
		{
			return $this->constraints[Constraint::PRIMARY_KEY];
		}

		/**
		 * get foreign key constraints.
		 *
		 * @return array
		 */
		public function getForeignKeyConstraints()
		{
			return $this->constraints[Constraint::FOREIGN_KEY];
		}

		/**
		 * check if a given column is defined.
		 *
		 * @param string $column_name the column name
		 *
		 * @return bool
		 */
		public function hasColumn($column_name)
		{
			return isset($this->columns[$column_name]);
		}

	}
