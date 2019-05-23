<?php
	/**
	 * Copyright (c) 2017-present, Emile Silas Sare
	 *
	 * This file is part of OZone (O'Zone) package.
	 *
	 * For the full copyright and license information, please view the LICENSE
	 * file that was distributed with this source code.
	 */

	namespace OZONE\OZ\Db\Columns\Types;

	use Gobl\DBAL\Types\Exceptions\TypesInvalidValueException;
	use Gobl\DBAL\Types\TypeBigint;

	final class TypeTimestamp extends TypeBigint
	{
		private $auto = false;

		/**
		 * TypeTimestamp constructor.
		 *
		 * @inheritdoc
		 */
		public function __construct()
		{
			parent::__construct();

			$this->min(0)
				 ->unsigned();
		}

		/**
		 * @inheritdoc
		 */
		public function getDefault()
		{
			$default = parent::getDefault();
			if (is_null($default) AND $this->auto) {
				$default = time();
			}

			return $default;
		}

		/**
		 * @return $this
		 */
		public function auto()
		{
			$this->auto = true;

			return $this;
		}

		/**
		 * @inheritdoc
		 */
		public function validate($value, $column_name, $table_name)
		{
			$debug = [
				"value" => $value
			];

			if (empty($value) AND $value != 0 AND $this->auto) {
				return time();
			}

			try {
				$value = parent::validate($value, $column_name, $table_name);
			} catch (TypesInvalidValueException $e) {
				throw new TypesInvalidValueException('OZ_TIMESTAMP_IS_INVALID', $debug);
			}

			return $value;
		}

		/**
		 * @inheritdoc
		 */
		public static function getInstance(array $options)
		{
			$instance = new self;

			if (self::getOptionKey($options, 'auto', false))
				$instance->auto();

			if (self::getOptionKey($options, 'null', false))
				$instance->nullAble();

			if (array_key_exists('default', $options))
				$instance->setDefault($options['default']);

			return $instance;
		}

		/**
		 * @inheritdoc
		 */
		public function getCleanOptions()
		{
			$options         = parent::getCleanOptions();
			$options['auto'] = $this->auto;

			return $options;
		}
	}
