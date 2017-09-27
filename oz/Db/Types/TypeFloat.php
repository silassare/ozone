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

	class TypeFloat implements Type
	{
		private $null     = false;
		private $default  = null;
		private $unsigned = false;
		// the number of digits following the decimal point
		private $m = 53;
		private $min;
		private $max;

		/**
		 * TypeFloat constructor.
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

			if (!is_float($value) AND !is_int($value))
				throw new \Exception(sprintf('"%s" is not a valid float.', $value));

			if ($this->unsigned AND 0 > $value)
				throw new \Exception(sprintf('"%s" is not a valid unsigned float.', $value));

			if (isset($this->min) AND $value < $this->min)
				throw new \Exception(sprintf('min=%s and max=%s is not a valid condition.', $this->min, $value));

			$this->max = (float)$value;

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

			if (!is_float($value) AND !is_int($value))
				throw new \Exception(sprintf('"%s" is not a valid float.', $value));

			if ($this->unsigned AND 0 > $value)
				throw new \Exception(sprintf('"%s" is not a valid unsigned float.', $value));

			if (isset($this->max) AND $value > $this->max)
				throw new \Exception(sprintf('min=%s and max=%s is not a valid condition.', $value, $this->max));

			$this->min = (float)$value;

			return $this;
		}

		/**
		 * set the number of digits following the decimal point
		 *
		 * @param int $value the mantissa
		 *
		 * @return $this
		 * @throws \Exception
		 */
		public function mantissa($value)
		{
			if (!is_int($value) OR 0 > $value OR 53 < $value)
				throw new \Exception('The number of digits following the decimal point should be an integer between 0 and 53.');

			$this->m = $value;

			return $this;
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

			if (!is_float($value) AND !is_int($value))
				throw new OZoneInvalidFieldException('OZ_INVALID_FLOAT_TYPE', $debug);

			if ($this->unsigned AND 0 > $value)
				throw new OZoneInvalidFieldException('OZ_INVALID_UNSIGNED_FLOAT_TYPE', $debug);

			if (isset($this->min) AND $value < $this->min)
				throw new OZoneInvalidFieldException('OZ_VALUE_NUMBER_LT_MIN', $debug);

			if (isset($this->max) AND $value > $this->max)
				throw new OZoneInvalidFieldException('OZ_VALUE_NUMBER_GT_MAX', $debug);

			return (float)$value;
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

			if (isset($options['mantissa']))
				$instance->mantissa($options['mantissa']);

			if (isset($options['null']) AND $options['null'])
				$instance->nullable();

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
				'type'     => 'float',
				'min'      => $this->min,
				'max'      => $this->max,
				'unsigned' => $this->unsigned,
				'mantissa' => $this->m,
				'null'     => $this->null,
				'default'  => $this->default
			];

			return $options;
		}

		/**
		 * {@inheritdoc}
		 */
		public function is($type_const)
		{
			return Type::TYPE_BIGINT === $type_const;
		}
	}
