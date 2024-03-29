<?php

/**
 * Copyright (c) 2017-present, Emile Silas Sare
 *
 * This file is part of OZone package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace OZONE\OZ\Columns\Types;

use Gobl\DBAL\Types\Exceptions\TypesInvalidValueException;
use Gobl\DBAL\Types\TypeString;
use OZONE\OZ\Core\SettingsManager;

final class TypeGender extends TypeString
{
	/**
	 * TypeGender constructor.
	 *
	 * @inheritdoc
	 */
	public function __construct()
	{
		parent::__construct(1, 30);
	}

	/**
	 * @inheritdoc
	 */
	public function validate($value, $column_name, $table_name)
	{
		$debug = [
			'value' => $value,
		];

		if (!\in_array($value, SettingsManager::get('oz.users', 'OZ_USER_ALLOWED_GENDERS'))) {
			throw new TypesInvalidValueException('OZ_FIELD_GENDER_INVALID', $debug);
		}

		return $value;
	}

	/**
	 * @inheritdoc
	 */
	public static function getInstance(array $options)
	{
		$instance = new self();

		if (self::getOptionKey($options, 'null', false)) {
			$instance->nullAble();
		}

		if (\array_key_exists('default', $options)) {
			$instance->setDefault($options['default']);
		}

		return $instance;
	}
}
