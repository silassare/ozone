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

	final class TypeGender extends TypeString
	{
		/**
		 * {@inheritdoc}
		 */
		public function validate($value)
		{
			$data = [$value];

			if (!in_array($value, SettingsManager::get('oz.users', 'OZ_USER_ALLOWED_GENDERS'))) {
				throw new TypesInvalidValueException('OZ_FIELD_GENDER_INVALID',$data);
			}

			return $value;
		}

		/**
		 * {@inheritdoc}
		 */
		public static function getInstance(array $options)
		{
			$instance = new self;
			$instance->max(30);

			return $instance;
		}
	}
