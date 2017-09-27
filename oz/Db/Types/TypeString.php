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

	class TypeString implements Type
	{
		private $null    = false;
		private $default = null;
		private $min;
		private $max;
		private $pattern;

		/**
		 * TypeString constructor.
		 *
		 * @param int|null $min the minimum string length
		 * @param int|null $max the maximum string length
		 */
		public function __construct($min = null, $max = null)
		{
			if (isset($min)) $this->min($min);
			if (isset($max)) $this->max($max);
		}

		/**
		 * set the string pattern.
		 *
		 * @param string $pattern the pattern (regular expression)
		 *
		 * @return $this
		 * @throws \Exception
		 */
		public function pattern($pattern)
		{
			if (false === preg_match($pattern, null))
				throw new \Exception(sprintf('invalid regular expression: %s', $pattern));

			$this->pattern = $pattern;

			return $this;
		}

		/**
		 * set maximum string length.
		 *
		 * @param int $value the maximum string length
		 *
		 * @return $this
		 * @throws \Exception
		 * @internal param null|string $error_message the error message
		 *
		 */
		public function max($value)
		{
			if (!is_int($value) OR $value < 1)
				throw new \Exception(sprintf('"%s" is not a valid integer(>0).', $value));
			if (isset($this->min) AND $value < $this->min)
				throw new \Exception(sprintf('min=%s and max=%s is not a valid condition.', $this->min, $value));

			$this->max = $value;

			return $this;
		}

		/**
		 * set minimum string length.
		 *
		 * @param int $value the minimum string length
		 *
		 * @return $this
		 * @throws \Exception
		 */
		public function min($value)
		{
			if (!is_int($value) OR $value < 1)
				throw new \Exception(sprintf('"%s" is not a valid integer(>0).', $value));
			if (isset($this->max) AND $value > $this->max)
				throw new \Exception(sprintf('min=%s and max=%s is not a valid condition.', $value, $this->max));

			$this->min = $value;

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
		}

		/**
		 * {@inheritdoc}
		 */
		public function validate($value)
		{
			$debug = [
				'value'   => $value,
				'min'     => $this->min,
				'max'     => $this->max,
				'pattern' => $this->pattern,
				'default' => $this->default
			];

			if (is_null($value) AND $this->null)
				return $this->default;

			if (!is_string($value))
				throw new OZoneInvalidFieldException('OZ_INVALID_STRING_TYPE', $debug);

			if (isset($this->min) AND strlen($value) < $this->min)
				throw new OZoneInvalidFieldException('OZ_VALUE_LENGTH_LT_MIN', $debug);

			if (isset($this->max) AND strlen($value) > $this->max)
				throw new OZoneInvalidFieldException('OZ_VALUE_LENGTH_GT_MAX', $debug);

			if (isset($this->pattern) AND !preg_match($this->pattern, $value))
				throw new OZoneInvalidFieldException('OZ_VALUE_PATTERN_CHECK_FAILS', $debug);

			return $value;
		}

		/**
		 * {@inheritdoc}
		 */
		public static function getInstance(array $options)
		{
			$instance = new self;

			if (isset($options['min']))
				$instance->min($options['min']);

			if (isset($options['max']))
				$instance->max($options['max']);

			if (isset($options['pattern']))
				$instance->pattern($options['pattern']);

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
				'type'    => 'string',
				'min'     => $this->min,
				'max'     => $this->max,
				'pattern' => $this->pattern,
				'null'    => $this->null,
				'default' => $this->default
			];

			return $options;
		}

		/**
		 * {@inheritdoc}
		 */
		public function is($type_const)
		{
			return Type::TYPE_STRING === $type_const;
		}

	}
