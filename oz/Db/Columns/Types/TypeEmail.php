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

	final class TypeEmail extends TypeString
	{
		private $registered = null;

		/**
		 * TypeEmail constructor.
		 *
		 * {@inheritdoc}
		 */
		public function __construct()
		{
			parent::__construct(1, 255);
		}

		/**
		 * To accept email that are not registered only
		 *
		 * @return $this
		 */
		public function registered()
		{
			$this->registered = true;

			return $this;
		}

		/**
		 * To accept email that are not registered only
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
			try {
				$value = parent::validate($value, $column_name, $table_name);
			} catch (TypesInvalidValueException $e) {
				$success = false;
			}

			$debug = [
				'email' => $value,
				"value" => $value
			];

			if (!$success) {
				throw new TypesInvalidValueException('OZ_FIELD_EMAIL_INVALID', $debug);
			}

			if (!empty($value)) {
				if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
					throw new TypesInvalidValueException('OZ_FIELD_EMAIL_INVALID', $debug);
				} elseif ($this->registered === false AND UsersUtils::searchUserWithEmail($value)) {
					throw new TypesInvalidValueException('OZ_FIELD_EMAIL_ALREADY_REGISTERED', $debug);
				} elseif ($this->registered === true AND !UsersUtils::searchUserWithEmail($value)) {
					throw new TypesInvalidValueException('OZ_FIELD_EMAIL_NOT_REGISTERED', $debug);
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
