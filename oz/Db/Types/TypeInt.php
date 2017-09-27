<?php
	/**
	 * Copyright (c) Emile Silas Sare <emile.silas@gmail.com>
	 *
	 * This file is part of the OZone package.
	 *
	 * For the full copyright and license information, please view the LICENSE
	 * file that was distributed with this source code.
	 */

	namespace OZONE\OZ\Db\Types;

	use OZONE\OZ\Exceptions\OZoneInvalidFieldException;

	class TypeInt implements Type
	{
		private $null           = false;
		private $default        = null;
		private $unsigned       = false;
		private $auto_increment = false;
		private $min;
		private $max;
		const INT_SIGNED_MIN   = -2147483648;
		const INT_SIGNED_MAX   = 2147483647;
		const INT_UNSIGNED_MIN = 0;
		const INT_UNSIGNED_MAX = 4294967295;

		/**
		 * TypeInt constructor.
		 *
		 * @param int|null $min      the minimum number
		 * @param int|null $max      the maximum number
		 * @param bool     $unsigned as unsigned number
		 */
		public function __construct($min = null, $max = null, $unsigned = false)
		{
			$this->unsigned = (bool)$unsigned;

			if (isset($min)) $this->min($min);
			if (isset($max)) $this->max($max);
		}

		/**
		 * set maximum number.
		 *
		 * @param int $value the maximum
		 *
		 * @return $this
		 * @throws \Exception
		 */
		public function max($value)
		{
			if (!is_numeric($value))
				throw new \Exception(sprintf('"%s" is not a valid number.', $value));

			$value += 0;

			if (!is_int($value))
				throw new \Exception(sprintf('"%s" is not a valid int.', $value));

			if ($this->unsigned AND TypeInt::INT_UNSIGNED_MIN > $value)
				throw new \Exception(sprintf('"%s" is not a valid unsigned int.', $value));

			if ($this->unsigned AND $value > TypeInt::INT_UNSIGNED_MAX)
				throw new \Exception('You should use "unsigned bigint" instead of "unsigned int".');

			if (!$this->unsigned AND $value > TypeInt::INT_SIGNED_MAX)
				throw new \Exception('You should use "signed bigint" instead of "signed int".');

			if (isset($this->min) AND $value < $this->min)
				throw new \Exception(sprintf('min=%s and max=%s is not a valid condition.', $this->min, $value));

			$this->max = $value;

			return $this;
		}

		/**
		 * set minimum number.
		 *
		 * @param int $value the minimum
		 *
		 * @return $this
		 * @throws \Exception
		 */
		public function min($value)
		{
			if (!is_numeric($value))
				throw new \Exception(sprintf('"%s" is not a valid number.', $value));

			$value += 0;

			if (!is_int($value))
				throw new \Exception(sprintf('"%s" is not a valid int.', $value));

			if ($this->unsigned AND TypeInt::INT_UNSIGNED_MIN > $value)
				throw new \Exception(sprintf('"%s" is not a valid unsigned int.', $value));

			if (!$this->unsigned AND $value < TypeInt::INT_SIGNED_MIN)
				throw new \Exception('You should use "signed bigint" instead of "signed int".');

			if (isset($this->max) AND $value > $this->max)
				throw new \Exception(sprintf('min=%s and max=%s is not a valid condition.', $value, $this->max));

			$this->min = $value;

			return $this;
		}

		/**
		 * Auto-increment allows a unique number to be generated
		 * automatically when a new record is inserted.
		 */
		public function autoIncrement()
		{
			$this->auto_increment = true;
		}

		/**
		 * {@inheritdoc}
		 */
		public function nullable()
		{
			$this->null = true;
		}

		/**
		 * {@inheritdoc}
		 */
		public function def($value)
		{
			$this->default = $value;

			return $this;
		}

		/**
		 * {@inheritdoc}
		 */
		public function validate($value)
		{
			$debug = ['value' => $value, 'min' => $this->min, 'max' => $this->max, 'default' => $this->default];

			if (is_null($value) AND $this->null)
				return $this->default;

			if (!is_numeric($value))
				throw new OZoneInvalidFieldException('OZ_INVALID_NUMBER_TYPE', $debug);

			$value += 0;

			if (!is_int($value))
				throw new OZoneInvalidFieldException('OZ_INVALID_INTEGER_TYPE', $debug);

			if ($this->unsigned AND 0 > $value)
				throw new OZoneInvalidFieldException('OZ_INVALID_UNSIGNED_INTEGER_TYPE', $debug);

			if (isset($this->min) AND $value < $this->min)
				throw new OZoneInvalidFieldException('OZ_VALUE_NUMBER_LT_MIN', $debug);

			if (isset($this->max) AND $value > $this->max)
				throw new OZoneInvalidFieldException('OZ_VALUE_NUMBER_GT_MAX', $debug);

			return $value;
		}

		/**
		 * {@inheritdoc}
		 */
		public static function getInstance(array $options)
		{
			$options = array_merge([
				'min'      => null,
				'max'      => null,
				'unsigned' => false
			], $options);

			$instance = new self($options['min'], $options['max'], $options['unsigned']);

			if (isset($options['null']) AND $options['null'])
				$instance->nullable();

			if (isset($options['auto_increment']) AND $options['auto_increment'])
				$instance->autoIncrement();

			if (array_key_exists('default', $options))
				$instance->def($options['default']);

			return $instance;
		}

		/**
		 * {@inheritdoc}
		 */
		public function getCleanOptions()
		{
			$options = [
				'type'           => 'int',
				'min'            => $this->min,
				'max'            => $this->max,
				'unsigned'       => $this->unsigned,
				'auto_increment' => $this->auto_increment,
				'null'           => $this->null,
				'default'        => $this->default
			];

			return $options;
		}

		/**
		 * {@inheritdoc}
		 */
		public function is($type_const)
		{
			return Type::TYPE_INT === $type_const;
		}
	}
