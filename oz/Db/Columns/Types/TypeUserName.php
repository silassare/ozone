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
	use OZONE\OZ\Utils\StringUtils;

	final class TypeUserName extends TypeString
	{
		/**
		 * TypeUserName constructor.
		 *
		 * {@inheritdoc}
		 *
		 * @throws \OZONE\OZ\Exceptions\InternalErrorException
		 */
		public function __construct()
		{
			parent::__construct();

			$max     = intval(SettingsManager::get('oz.ofv.const', 'OZ_USER_NAME_MAX_LENGTH'));
			$pattern = SettingsManager::get('oz.ofv.const', 'OZ_USER_NAME_REG');

			$this->length(1, max(3, $max));

			if (isset($pattern))
				$this->pattern($pattern);
		}

		/**
		 * {@inheritdoc}
		 * @throws \OZONE\OZ\Exceptions\InternalErrorException
		 */
		public function validate($value, $column_name, $table_name)
		{
			$success = true;
			$data    = [
				"value" => $value
			];

			try {
				$value = parent::validate($value, $column_name, $table_name);
			} catch (TypesInvalidValueException $e) {
				$success = false;
			}

			if (!$success) {
				throw new TypesInvalidValueException('OZ_FIELD_USER_NAME_INVALID', $data);
			}

			if (!empty($value)) {
				$unwanted = SettingsManager::get('oz.ofv.const', 'OZ_UNWANTED_CHAR_REG');
				$value    = preg_replace($unwanted, ' ', $value);
				$value    = trim($value);

				$contains_key_words = preg_match(SettingsManager::get('oz.ofv.const', 'OZ_EXCLUDE_KEY_WORDS'), $value);

				if (!$contains_key_words) {
					$value = StringUtils::clean($value);
				} else {
					$error_msg = 'OZ_FIELD_USER_NAME_INVALID';

					if ($contains_key_words) {
						$error_msg = 'OZ_FIELD_USER_NAME_CONTAINS_KEYWORDS';
					} elseif (strlen($value) < SettingsManager::get('oz.ofv.const', 'OZ_USER_NAME_MIN_LENGTH')) {
						$error_msg = 'OZ_FIELD_USER_NAME_TOO_SHORT';
					} elseif (strlen($value) > SettingsManager::get('oz.ofv.const', 'OZ_USER_NAME_MAX_LENGTH')) {
						$error_msg = 'OZ_FIELD_USER_NAME_TOO_LONG';
					}

					throw new TypesInvalidValueException($error_msg, $data);
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

			if (self::getOptionKey($options, 'null', false))
				$instance->nullAble();

			if (array_key_exists('default', $options))
				$instance->setDefault($options['default']);

			return $instance;
		}
	}
