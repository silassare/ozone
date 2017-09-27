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

	interface QueryBuilder
	{
		public function __construct(RDBMS $rdbms);
		public function select(array $columns);
		public function from(array $tables);
		public function where(array $rules);
		public function join(Table $a, Table $b, array $column);
		public function list($table);
		public function count($table);
	}
