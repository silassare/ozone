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

	final class TypePhone extends TypeString
	{
		private $registered = null;

		/**
		 * @param bool $state true to accept registered only, false otherwise
		 *
		 * @return $this
		 */
		public function registered($state = true)
		{
			$this->registered = (bool)$state;

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
				throw new TypesInvalidValueException('OZ_FIELD_PHONE_INVALID', $data);
			} elseif ($this->registered === false AND UsersUtils::searchUserWithPhone($value)) {
				throw new TypesInvalidValueException('OZ_FIELD_PHONE_ALREADY_REGISTERED', $data);
			} elseif ($this->registered === true AND !UsersUtils::searchUserWithPhone($value)) {
				throw new TypesInvalidValueException('OZ_FIELD_PHONE_NOT_REGISTERED', $data);
			}

			return $value;
		}

		/**
		 * {@inheritdoc}
		 */
		public static function getInstance(array $options)
		{
			$instance = new self;

			$instance->max(15);
			$instance->pattern('#^\+\d{6,15}$#');

			if (isset($options['registered']))
				$instance->registered($options['registered']);

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
			$options['registered'] = $this->registered;

			return $options;
		}
	}
