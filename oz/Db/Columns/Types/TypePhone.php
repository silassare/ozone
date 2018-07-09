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
		 * TypePhone constructor.
		 *
		 * {@inheritdoc}
		 */
		public function __construct()
		{
			parent::__construct(1, 15, '#^\+\d{6,15}$#');
		}

		/**
		 * To accept phone number that are not registered only
		 *
		 * @return $this
		 */
		public function registered()
		{
			$this->registered = true;

			return $this;
		}

		/**
		 * To accept phone number that are not registered only
		 *
		 * @return $this
		 */
		public function notRegistered()
		{
			$this->registered = false;

			return $this;
		}

		/**
		 * {@inheritdoc}
		 */
		public function validate($value, $column_name, $table_name)
		{
			$success = true;

			if (is_string($value)) {
				$value = str_replace(" ", "", $value);
			}

			try {
				$value = parent::validate($value, $column_name, $table_name);
			} catch (TypesInvalidValueException $e) {
				$success = false;
			}

			$debug = [
				'phone' => $value,
				"value" => $value
			];

			if (!$success) {
				throw new TypesInvalidValueException('OZ_FIELD_PHONE_INVALID', $debug);
			}

			if (!empty($value)) {
				if ($this->registered === false AND UsersUtils::searchUserWithPhone($value)) {
					throw new TypesInvalidValueException('OZ_FIELD_PHONE_ALREADY_REGISTERED', $debug);
				} elseif ($this->registered === true AND !UsersUtils::searchUserWithPhone($value)) {
					throw new TypesInvalidValueException('OZ_FIELD_PHONE_NOT_REGISTERED', $debug);
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

			if (isset($options['registered'])) {
				$registered = $options['registered'];
				if ($registered === true) {
					$instance->registered();
				} else {
					$instance->notRegistered();
				}
			}

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
			$options['registered'] = $this->registered;

			return $options;
		}
	}
