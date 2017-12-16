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
	use OZONE\OZ\Core\SettingsManager;
	use OZONE\OZ\Ofv\OFormUtils;

	final class TypeDate extends TypeString
	{
		private $birth_date = false;

		/**
		 * @return $this
		 */
		public function birthDate()
		{
			$this->birth_date = true;

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

			$min_age = SettingsManager::get('oz.ofv.const', 'OZ_USER_MIN_AGE');
			$max_age = SettingsManager::get('oz.ofv.const', 'OZ_USER_MAX_AGE');

			$data = ['input' => $value, 'min' => $min_age, 'max' => $max_age];

			if ($success) {
				if (OFormUtils::isBirthDate($value, $min_age, $max_age)) {
					$format = OFormUtils::parseDate($value);
					$value  = $format["DD-MM-YYYY"];
				} else {
					$success = false;
				}
			}

			if (!$success) {
				throw new TypesInvalidValueException('OZ_FIELD_BIRTH_DATE_INVALID', $data);
			}

			return $value;
		}

		/**
		 * {@inheritdoc}
		 */
		public static function getInstance(array $options)
		{
			$instance = new self;

			$instance->max(10);

			if (isset($options['birth_date']) AND $options['birth_date'])
				$instance->birthDate();

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
			$options['birth_date'] = $this->birth_date;

			return $options;
		}
	}
