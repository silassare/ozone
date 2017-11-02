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

	final class TypePassword extends TypeString
	{
		/**
		 * {@inheritdoc}
		 */
		public function validate($value)
		{
			$data = [$value];

			$pass = (string)$value;
			$len  = strlen($pass);
			if ($len < SettingsManager::get('oz.ofv.const', 'OZ_PASS_MIN_LENGTH')) {
				throw new TypesInvalidValueException('OZ_FIELD_PASS_TOO_SHORT', $data);
			} elseif ($len > SettingsManager::get('oz.ofv.const', 'OZ_PASS_MAX_LENGTH')) {
				throw new TypesInvalidValueException('OZ_FIELD_PASS_TOO_LONG', $data);
			}

			return $pass;
		}

		/**
		 * {@inheritdoc}
		 */
		public static function getInstance(array $options)
		{
			$instance = new self;
			$instance->max(255);

			return $instance;
		}
	}
