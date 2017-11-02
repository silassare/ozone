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
		public function validate($value)
		{
			$success = true;
			try {
				$value = parent::validate($value);
			} catch (TypesInvalidValueException $e) {
				$success = false;
			}

			$data = [$value];

			if (!$success) {
				throw new TypesInvalidValueException('OZ_FIELD_COUNTRY_UNKNOWN', $data);
			}

			$cc2 = strtoupper($value); //<-- important
			if ($this->authorized) {
				if (!UsersUtils::authorizedCountry($cc2)) {
					throw new TypesInvalidValueException('OZ_FIELD_COUNTRY_NOT_ALLOWED', $data);
				}
			} elseif (!UsersUtils::getCountryObject($cc2)) {
				throw new TypesInvalidValueException('OZ_FIELD_COUNTRY_UNKNOWN', $data);
			}

			return $cc2;
		}

		/**
		 * {@inheritdoc}
		 */
		public static function getInstance(array $options)
		{
			$instance = new self;

			$instance->min(2);
			$instance->max(2);
			$instance->pattern('#^[a-zA-Z]{2}$#');

			if (isset($options['authorized']) AND $options['authorized'])
				$instance->authorized();

			if (isset($options['null']) AND $options['null'])
				$instance->nullAble();

			if (array_key_exists('default', $options))
				$instance->def($options['default']);

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
