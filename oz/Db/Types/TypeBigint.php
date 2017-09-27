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

	class TypeBigint implements Type
	{
		private $null           = false;
		private $default        = null;
		private $unsigned       = false;
		private $auto_increment = false;
		private $min;
		private $max;
		const BIGINT_REG          = '#[-+]?(?:[1-9][0-9]*|0)#';
		const BIGINT_UNSIGNED_REG = '#[+]?(?:[1-9][0-9]*|0)#';

		/**
		 * TypeBigint constructor.
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

			if (!preg_match(self::BIGINT_REG, "$value"))
				throw new \Exception(sprintf('"%s" is not a valid bigint.', $value));

			if ($this->unsigned AND preg_match(self::BIGINT_UNSIGNED_REG, "$value"))
				throw new \Exception(sprintf('"%s" is not a valid unsigned bigint.', $value));

			if (isset($this->min) AND !self::isLt($this->min, $value))
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

			if (!preg_match(self::BIGINT_REG, "$value"))
				throw new \Exception(sprintf('"%s" is not a valid bigint.', $value));

			if ($this->unsigned AND preg_match(self::BIGINT_UNSIGNED_REG, "$value"))
				throw new \Exception(sprintf('"%s" is not a valid unsigned bigint.', $value));

			if (isset($this->max) AND !self::isLt($value, $this->max))
				throw new \Exception(sprintf('min=%s and max=%s is not a valid condition.', $value, $this->max));

			$this->min = $value;

			return $this;
		}

		/**
		 * check if the first argument is smallest.
		 *
		 * @param mixed $a
		 * @param mixed $b
		 *
		 * @return bool
		 */
		private static function isLt($a, $b)
		{
			$_a = $a + 0;
			$_b = $b + 0;

			// make sure to have bcmath
			// TODO find a way to avoid using bcmath

			if ($_a < PHP_INT_MIN AND $_b < PHP_INT_MIN) {
				$c = bccomp($a, $b) <= 0 ? $a : $b;
			} elseif ($_a > PHP_INT_MAX AND $_b > PHP_INT_MAX) {
				$c = bccomp($a, $b) <= 0 ? $b : $a;
			} else {
				$c = $_a <= $_b ? $a : $b;
			}

			return ($c === $a);
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

			if (!preg_match(self::BIGINT_REG, "$value"))
				throw new OZoneInvalidFieldException('OZ_INVALID_BIGINT_TYPE', $debug);

			if ($this->unsigned AND preg_match(self::BIGINT_UNSIGNED_REG, "$value"))
				throw new OZoneInvalidFieldException('OZ_INVALID_UNSIGNED_BIGINT_TYPE', $debug);

			if (isset($this->min) AND !self::isLt($this->min, $value))
				throw new OZoneInvalidFieldException('OZ_VALUE_NUMBER_LT_MIN', $debug);

			if (isset($this->max) AND !self::isLt($value, $this->max))
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
				'type'           => 'bigint',
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
			return Type::TYPE_BIGINT === $type_const;
		}
	}
