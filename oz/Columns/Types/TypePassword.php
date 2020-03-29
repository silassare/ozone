<?php

/**
 * Copyright (c) 2017-present, Emile Silas Sare
 *
 * This file is part of OZone (O'Zone) package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace OZONE\OZ\Columns\Types;

use Gobl\DBAL\Types\Exceptions\TypesInvalidValueException;
use Gobl\DBAL\Types\TypeString;
use OZONE\OZ\Core\SettingsManager;

final class TypePassword extends TypeString
{
	/**
	 * TypePassword constructor.
	 *
	 * @inheritdoc
	 */
	public function __construct()
	{
		parent::__construct(1, 255);
	}

	/**
	 * @inheritdoc
	 */
	public function validate($value, $column_name, $table_name)
	{
		$min   = SettingsManager::get('oz.ofv.const', 'OZ_PASS_MIN_LENGTH');
		$max   = SettingsManager::get('oz.ofv.const', 'OZ_PASS_MAX_LENGTH');
		$debug = [
			'value' => $value,
			'min'   => $min,
			'max'   => $max,
		];

		$pass = (string) $value;
		$len  = \strlen($pass);

		if ($len < $min) {
			throw new TypesInvalidValueException('OZ_FIELD_PASS_TOO_SHORT', $debug);
		}

		if ($len > $max) {
			throw new TypesInvalidValueException('OZ_FIELD_PASS_TOO_LONG', $debug);
		}

		return $pass;
	}

	/**
	 * @inheritdoc
	 */
	public static function getInstance(array $options)
	{
		return new self();
	}
}
