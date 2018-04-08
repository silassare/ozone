<?php
	/**
	 * Copyright (c) Emile Silas Sare <emile.silas@gmail.com>
	 *
	 * This file is part of the OZone package.
	 *
	 * For the full copyright and license information, please view the LICENSE
	 * file that was distributed with this source code.
	 */

	namespace OZONE\OZ\Db\Columns\Types;

	use Gobl\DBAL\Types\Exceptions\TypesInvalidValueException;
	use Gobl\DBAL\Types\TypeString;
	use OZONE\OZ\User\UsersUtils;

	final class TypeCC2 extends TypeString
	{
		private $authorized = false;

		/**
		 * TypeCC2 constructor.
		 */
		public function __construct()
		{
			parent::__construct(2, 2, '#^[a-zA-Z]{2}$#');
		}

		/**
		 * @return $this
		 */
		public function authorized()
		{
			$this->authorized = true;

			return $this;
		}

		/**
		 * {@inheritdoc}
		 */
		public function validate($value, $column_name, $table_name)
		{
			$success = true;

			$debug = [
				"value" => $value
			];

			try {
				$value = parent::validate($value, $column_name, $table_name);
			} catch (TypesInvalidValueException $e) {
				$success = false;
			}

			if (!$success) {
				throw new TypesInvalidValueException('OZ_FIELD_COUNTRY_UNKNOWN', $debug);
			}

			if (!empty($value)) {
				$value = strtoupper($value); //<-- important
				if ($this->authorized) {
					if (!UsersUtils::authorizedCountry($value)) {
						throw new TypesInvalidValueException('OZ_FIELD_COUNTRY_NOT_ALLOWED', $debug);
					}
				} elseif (!UsersUtils::getCountryObject($value)) {
					throw new TypesInvalidValueException('OZ_FIELD_COUNTRY_UNKNOWN', $debug);
				}
			}

			return $value;
		}

		/**
		 * {@inheritdoc}
		 */
		public static function getInstance(array $options)
		{
			$instance = new self;

			if (self::getOptionKey($options, 'authorized', false))
				$instance->authorized();

			if (self::getOptionKey($options, 'null', false))
				$instance->nullAble();

			if (array_key_exists('default', $options))
				$instance->setDefault($options['default']);

			return $instance;
		}

		/**
		 * {@inheritdoc}
		 */
		public function getCleanOptions()
		{
			$options               = parent::getCleanOptions();
			$options['authorized'] = $this->authorized;

			return $options;
		}
	}
