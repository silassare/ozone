<?php
	/**
	 * Copyright (c) Emile Silas Sare <emile.silas@gmail.com>
	 *
	 * This file is part of the OZone package.
	 *
	 * For the full copyright and license information, please view the LICENSE
	 * file that was distributed with this source code.
	 */

	namespace OZONE\OZ\AppManager;

	defined('OZ_SELF_SECURITY_CHECK') or die;

	final class TableDescriptor
	{

		private $tableName        = null;
		private $tableNamePlurals = null;

		public function __construct($tableName)
		{
			$this->tableName        = $tableName;//message
			$this->tableNamePlurals = $tableNamePlurals;//messages

			$this->describe();
		}

		private function describe()
		{
		}

		public function getTableName($plurals = true)
		{
			if ($plurals) {
				return $this->tableNamePlurals;
			}

			return $this->tableName;
		}

		public function getControllerClassName()
		{
			return 'SxMessage';
		}

		public function getEditableFormFieldsName()
		{
			return ['c_name', 'sid', 'desc', 'cc2'];
		}

		public function getEditableFormFieldsRules()
		{
			return ['c_name' => null, 'desc' => null];
		}
	}