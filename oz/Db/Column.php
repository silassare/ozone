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
	use OZONE\OZ\Db\Types\TypeBigint;
	use OZONE\OZ\Db\Types\TypeBool;
	use OZONE\OZ\Db\Types\TypeFloat;
	use OZONE\OZ\Db\Types\TypeInt;
	use OZONE\OZ\Db\Types\TypeString;

	class Column
	{
		protected $name;
		protected $prefix;

		/**
		 * @var \OZONE\OZ\Db\Types\Type;
		 */
		protected      $type;
		private static $allowed_types = ['string', 'int', 'bigint', 'float', 'bool'];

		const NAME_REG   = '#^(?:[a-zA-Z][a-zA-Z0-9_]*[a-zA-Z0-9]|[a-zA-Z])$#';
		const PREFIX_REG = '#^(?:[a-zA-Z][a-zA-Z0-9_]*[a-zA-Z0-9]|[a-zA-Z])$#';

		/**
		 * Column constructor.
		 *
		 * @param string $name   the column name
		 * @param string $prefix the column prefix
		 *
		 * @throws \Exception
		 */
		public function __construct($name, $prefix = 'col')
		{
			if (!preg_match(Column::NAME_REG, $name))
				throw new \Exception(sprintf('invalid column name "%s".', $name));

			if (!preg_match(Column::PREFIX_REG, $prefix))
				throw new \Exception(sprintf('invalid column prefix name "%s".', $prefix));

			$this->name   = strtolower($name);
			$this->prefix = strtolower($prefix);
		}

		/**
		 * set column type.
		 *
		 * @param \OZONE\OZ\Db\Types\Type $type the column type object.
		 *
		 * @return $this
		 *
		 */
		public function setType(Type $type)
		{
			$this->type = $type;

			return $this;
		}

		/**
		 * set column options.
		 *
		 * @param array $options
		 *
		 * @return $this
		 * @throws \Exception
		 */
		public function setOptions(array $options)
		{
			if (!isset($options['type']))
				throw new \Exception(sprintf('you should define a column type for "%s".', $this->name));

			$type = $options['type'];

			if (!in_array($type, self::$allowed_types))
				throw new \Exception(sprintf('unsupported column type "%s" defined for "%s".', $type, $this->name));

			if ($type === 'int')
				$t = TypeInt::getInstance($options);
			elseif ($type === 'bigint')
				$t = TypeBigint::getInstance($options);
			elseif ($type === 'float')
				$t = TypeFloat::getInstance($options);
			elseif ($type === 'bool')
				$t = TypeBool::getInstance($options);
			else // if ($type === 'string')
				$t = TypeString::getInstance($options);

			$this->type = $t;

			return $this;
		}

		/**
		 * get column options.
		 *
		 * @return array
		 */
		public function getOptions()
		{
			return $this->type->getCleanOptions();
		}

		/**
		 * get type object.
		 *
		 * @return \OZONE\OZ\Db\Types\Type
		 */
		public function getTypeObject()
		{
			return $this->type;
		}

		/**
		 * get column name.
		 *
		 * @return string
		 */
		public function getName()
		{
			return $this->name;
		}

		/**
		 * get column prefix.
		 *
		 * @return string
		 */
		public function getPrefix()
		{
			return $this->prefix;
		}

		/**
		 * get column full name.
		 *
		 * @return string
		 */
		public function getFullName()
		{
			return $this->prefix . '_' . $this->name;
		}
	}
