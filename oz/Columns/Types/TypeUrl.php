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

final class TypeUrl extends TypeString
{
	/**
	 * TypeUrl constructor.
	 *
	 * @inheritdoc
	 */
	public function __construct()
	{
		parent::__construct(1, 2000);
	}

	/**
	 * @param $value
	 * @param $column_name
	 * @param $table_name
	 *
	 * @throws \Exception
	 *
	 * @return string
	 */
	public function validate($value, $column_name, $table_name)
	{
		$debug = [
			'value' => $value,
		];

		try {
			$value = parent::validate($value, $column_name, $table_name);
		} catch (TypesInvalidValueException $e) {
			throw new TypesInvalidValueException('OZ_FIELD_URL_INVALID', $debug, $e);
		}

		if (!empty($value)) {
			if (!\filter_var($value, \FILTER_VALIDATE_URL)) {
				throw new TypesInvalidValueException('OZ_FIELD_URL_INVALID', $debug);
			}
		}

		return $value;
	}

	/**
	 * @inheritdoc
	 */
	public function getCleanOptions()
	{
		return parent::getCleanOptions();
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
